<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Models\AlumnoNoApto;
use App\Models\AlumnoProgresoMoodle;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DetectarNoAptosCommand extends Command
{
    protected $signature = 'reportes-moodle:detectar-no-aptos
        {--dry-run : Listar sin crear registros}';

    protected $description = 'Detecta alumnos suspendidos (nota<50 + sin cuestionario final) en cursos cuya fecha_fin ya pasó. Crea registros en alumno_no_aptos (idempotente).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN - no se crearán registros');
        }

        $hoy = CarbonImmutable::now('Europe/Madrid')->startOfDay();

        // Examinamos cursos cuyo fecha_fin sea entre hace 90 días y ayer, vengan de un
        // grupo formativo o de una matrícula individual (autónomo 2x1 / particular).
        // El comando es idempotente gracias al UNIQUE (alumno_id, origen_clave).
        $desde = $hoy->subDays(90)->toDateString();
        $ayer = $hoy->toDateString();

        $snapshots = AlumnoProgresoMoodle::query()
            ->where(function ($q) use ($desde, $ayer) {
                $q->whereHas('pivot.grupoFormativo', function ($g) use ($desde, $ayer) {
                    $g->where('estado', 'en_curso')
                      ->whereDate('fecha_fin', '<', $ayer)
                      ->whereDate('fecha_fin', '>=', $desde);
                })->orWhereHas('matriculaAutonoma', function ($m) use ($desde, $ayer) {
                    $m->where('estado', 'matriculado')
                      ->whereDate('fecha_fin', '<', $ayer)
                      ->whereDate('fecha_fin', '>=', $desde);
                });
            })
            ->with(['alumno', 'pivot.grupoFormativo.accionFormativa', 'matriculaAutonoma.accionFormativa'])
            ->orderByDesc('fecha_snapshot')
            ->get()
            // Nos quedamos con el snapshot MÁS RECIENTE de cada origen.
            ->unique(fn (AlumnoProgresoMoodle $s) => $s->grupo_formativo_alumno_id
                ? "p{$s->grupo_formativo_alumno_id}"
                : "m{$s->matricula_autonoma_id}");

        if ($snapshots->isEmpty()) {
            $this->info('✅ No hay cursos finalizados en los últimos 90 días para examinar.');
            return self::SUCCESS;
        }

        $this->info("📋 Examinando {$snapshots->count()} matrícula(s) finalizada(s)...");

        $detectados = 0;
        $yaRegistrados = 0;

        foreach ($snapshots as $snap) {
            $alumno = $snap->alumno;
            $matricula = $snap->origen();
            if (!$alumno || !$matricula) {
                continue;
            }

            // Condiciones para No apto:
            //   - nota_total < 50 (o null = nunca llegó)
            //   - cuestionario_final_realizado = false
            $notaTotal = $snap->nota_total !== null ? (float) $snap->nota_total : 0.0;
            $umbralAprobado = (float) config('reportes_moodle.reporte_inactivos.umbral_aprobado_puntos', 50);

            if ($notaTotal >= $umbralAprobado) {
                continue; // alcanzó el umbral; no es No apto
            }
            if ($snap->cuestionario_final_realizado) {
                continue; // realizó el cuestionario; no es No apto (caso atípico pero protege)
            }

            // ¿Ya existe?
            $existente = AlumnoNoApto::where('alumno_id', $alumno->id)
                ->where('origen_clave', $matricula->claveOrigen())
                ->first();

            if ($existente) {
                $yaRegistrados++;
                continue;
            }

            $this->line(sprintf(
                ' · %s · curso=%s · nota=%s · fin=%s',
                $alumno->nombre_completo,
                $snap->accion_curso?->denominacion_limpia ?? "({$matricula->claveOrigen()})",
                $snap->nota_total !== null ? "{$snap->nota_total}/{$snap->nota_max}" : 'sin nota',
                $snap->fecha_fin_curso?->format('d/m/Y'),
            ));

            if ($dryRun) {
                $detectados++;
                continue;
            }

            AlumnoNoApto::create([
                'alumno_id'                 => $alumno->id,
                'grupo_formativo_id'        => $snap->pivot?->grupo_formativo_id,
                'grupo_formativo_alumno_id' => $snap->grupo_formativo_alumno_id,
                'matricula_autonoma_id'     => $snap->matricula_autonoma_id,
                'origen_clave'              => $matricula->claveOrigen(),
                'moodle_user_id'            => $snap->moodle_user_id,
                'moodle_course_id'          => $snap->moodle_course_id,
                'nota_total'                => $snap->nota_total,
                'nota_max'                  => $snap->nota_max,
                'fecha_fin_curso'           => $snap->fecha_fin_curso,
                'fecha_deteccion'           => now(),
                'reinicio_estado'           => AlumnoNoApto::ESTADO_PENDIENTE,
            ]);

            $detectados++;
        }

        $this->newLine();
        $this->info('📊 Resumen:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Nuevos No aptos detectados', $detectados],
                ['Ya estaban registrados', $yaRegistrados],
            ],
        );

        return self::SUCCESS;
    }
}
