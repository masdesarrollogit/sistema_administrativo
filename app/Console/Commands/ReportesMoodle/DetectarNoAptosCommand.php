<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Models\AlumnoNoApto;
use App\Models\AlumnoProgresoMoodle;
use App\Models\GrupoFormativo;
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

        // Examinamos cursos cuyo fecha_fin sea entre hace 90 días y ayer.
        // (cualquier ventana razonable; el comando es idempotente con UNIQUE en BD).
        $gruposCandidatos = GrupoFormativo::query()
            ->where('estado', 'en_curso')
            ->whereDate('fecha_fin', '<', $hoy->toDateString())
            ->whereDate('fecha_fin', '>=', $hoy->subDays(90)->toDateString())
            ->pluck('id');

        if ($gruposCandidatos->isEmpty()) {
            $this->info('✅ No hay grupos finalizados en los últimos 90 días para examinar.');
            return self::SUCCESS;
        }

        $this->info("📋 Examinando {$gruposCandidatos->count()} grupo(s) finalizado(s)...");

        // Por cada grupo, buscamos el ÚLTIMO snapshot de cada alumno y verificamos condiciones.
        $detectados = 0;
        $yaRegistrados = 0;

        foreach ($gruposCandidatos as $grupoId) {
            // Último snapshot por alumno+grupo (usa el más reciente disponible)
            $snapshots = AlumnoProgresoMoodle::query()
                ->whereHas('pivot', fn ($q) => $q->where('grupo_formativo_id', $grupoId))
                ->with(['alumno', 'pivot.grupoFormativo'])
                ->orderByDesc('fecha_snapshot')
                ->get()
                ->unique('grupo_formativo_alumno_id'); // primer snapshot por pivot (más reciente)

            foreach ($snapshots as $snap) {
                $alumno = $snap->alumno;
                $grupo = $snap->pivot?->grupoFormativo;
                if (!$alumno || !$grupo) {
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
                    ->where('grupo_formativo_id', $grupo->id)
                    ->first();

                if ($existente) {
                    $yaRegistrados++;
                    continue;
                }

                $this->line(sprintf(
                    ' · %s · curso=%s · nota=%s · fin=%s',
                    $alumno->nombre_completo,
                    $grupo->accionFormativa?->denominacion_limpia ?? "(grupo {$grupo->id})",
                    $snap->nota_total !== null ? "{$snap->nota_total}/{$snap->nota_max}" : 'sin nota',
                    $grupo->fecha_fin?->format('d/m/Y'),
                ));

                if ($dryRun) {
                    $detectados++;
                    continue;
                }

                AlumnoNoApto::create([
                    'alumno_id'                 => $alumno->id,
                    'grupo_formativo_id'        => $grupo->id,
                    'grupo_formativo_alumno_id' => $snap->grupo_formativo_alumno_id,
                    'moodle_user_id'            => $snap->moodle_user_id,
                    'moodle_course_id'          => $snap->moodle_course_id,
                    'nota_total'                => $snap->nota_total,
                    'nota_max'                  => $snap->nota_max,
                    'fecha_fin_curso'           => $grupo->fecha_fin,
                    'fecha_deteccion'           => now(),
                    'reinicio_estado'           => AlumnoNoApto::ESTADO_PENDIENTE,
                ]);

                $detectados++;
            }
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
