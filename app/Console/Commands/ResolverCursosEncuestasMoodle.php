<?php

namespace App\Console\Commands;

use App\Models\EncuestaCalidad;
use App\Models\MoodleMatriculaIndex;
use App\Services\Webcurso\EncuestaCalidadService;
use Illuminate\Console\Command;

/**
 * Backfill: resuelve el curso de las encuestas de calidad que quedaron SIN curso
 * (ni por Nº Acción del Form ni por las 4 fuentes del Panel) cruzando el email del
 * alumno contra el índice de matrículas de Moodle (`moodle_matricula_index`).
 *
 * Requiere que `encuestas-calidad:snapshot-moodle` se haya ejecutado antes.
 * Las que no se resuelven se marcan `curso_origen='moodle_sin_match'` para no
 * reprocesarlas en cada corrida (salvo `--force`).
 */
class ResolverCursosEncuestasMoodle extends Command
{
    protected $signature = 'encuestas-calidad:resolver-moodle
        {--dry-run : No escribe; solo reporta qué resolvería}
        {--email= : Resolver solo las encuestas de este email}
        {--ano= : Resolver solo las de este año de cumplimentación}
        {--limit=0 : Máximo de encuestas a procesar (0 = todas)}
        {--force : Reprocesa también las ya marcadas moodle_sin_match}';

    protected $description = 'Rellena el curso de las encuestas de calidad sin resolver cruzando el email contra el índice de matrículas de Moodle';

    public function handle(EncuestaCalidadService $service): int
    {
        if (!config('encuesta_calidad.moodle_resolucion_enabled', true)) {
            $this->warn('⏸ Resolución vía Moodle DESACTIVADA (encuesta_calidad.moodle_resolucion_enabled = false).');
            return self::SUCCESS;
        }

        if (MoodleMatriculaIndex::query()->doesntExist()) {
            $this->error('❌ El índice de matrículas Moodle está vacío. Ejecuta antes: php artisan encuestas-calidad:snapshot-moodle');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $limit  = (int) $this->option('limit');

        $query = EncuestaCalidad::query()
            ->where(fn ($q) => $q->whereNull('curso_resuelto')->orWhere('curso_resuelto', ''))
            ->whereNotNull('alumno_email')->where('alumno_email', '!=', '');

        if (!$force) {
            $query->where(fn ($q) => $q->whereNull('curso_origen')->orWhere('curso_origen', '!=', 'moodle_sin_match'));
        }
        if ($email = $this->option('email')) {
            $query->whereRaw('LOWER(alumno_email) = ?', [mb_strtolower(trim($email))]);
        }
        if ($ano = $this->option('ano')) {
            $query->whereYear('fecha_cumplimentacion', (int) $ano);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        $this->info("🔎 Encuestas sin curso a intentar resolver: {$total}");

        $resueltas = 0;
        $sinMatch  = 0;

        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $query->orderBy('id')->chunkById(200, function ($encuestas) use ($service, $dryRun, &$resueltas, &$sinMatch, $bar) {
                foreach ($encuestas as $e) {
                    $cand = $service->resolverCursoDesdeIndiceMoodle(
                        $e->alumno_email,
                        $e->fecha_cumplimentacion,
                        $e->denominacion_accion,
                    );

                    if ($cand) {
                        $resueltas++;
                        if (!$dryRun) {
                            $e->forceFill($cand)->save();
                        }
                    } else {
                        $sinMatch++;
                        if (!$dryRun) {
                            $e->forceFill(['curso_origen' => 'moodle_sin_match'])->save();
                        }
                    }
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine(2);
        }

        $this->info("✅ Resueltas con Moodle: {$resueltas}");
        $this->line("➖ Sin match (matrícula eliminada o alumno no está en Moodle): {$sinMatch}");

        // Paso extra: rellenar el tutor de las encuestas ya resueltas por Moodle que
        // aún no tienen etiqueta (p.ej. resueltas antes de capturar el tutor).
        $tutores = $this->rellenarTutores($service, $dryRun);
        $this->line("👤 Tutor asignado (encuestas ya resueltas por Moodle): {$tutores}");

        if ($dryRun) {
            $this->warn('🧪 --dry-run: no se guardó nada.');
        }

        return self::SUCCESS;
    }

    /**
     * Estampa `tutor_label` en las encuestas resueltas vía Moodle que aún no lo
     * tienen, cruzando email + curso contra el índice.
     */
    protected function rellenarTutores(EncuestaCalidadService $service, bool $dryRun): int
    {
        $n = 0;
        EncuestaCalidad::query()
            ->whereNull('tutor_label')
            ->whereNotNull('curso_resuelto')->where('curso_resuelto', '!=', '')
            ->whereNotNull('alumno_email')->where('alumno_email', '!=', '')
            ->orderBy('id')
            ->chunkById(300, function ($encuestas) use ($service, $dryRun, &$n) {
                foreach ($encuestas as $e) {
                    $label = $service->etiquetaTutorDesdeIndice($e->alumno_email, $e->curso_resuelto);
                    if ($label) {
                        $n++;
                        if (!$dryRun) {
                            $e->forceFill(['tutor_label' => $label])->save();
                        }
                    }
                }
            });

        return $n;
    }
}
