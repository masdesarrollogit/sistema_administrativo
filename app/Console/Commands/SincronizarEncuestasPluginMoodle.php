<?php

namespace App\Console\Commands;

use App\Models\EncuestaCalidad;
use App\Services\Webcurso\EncuestaCalidadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Moodle\Services\MoodleService;

/**
 * Trae al Panel las respuestas del cuestionario de calidad que los alumnos
 * rellenan dentro del aula (plugin Moodle mod_calidadfundae).
 *
 * Incremental por `timemodified`: el cursor es el mayor `moodle_timemodified`
 * ya guardado, con un pequeño solape. No hace falta tabla de estado porque el
 * guardado es idempotente por `forms_id = moodle-{id}`.
 */
class SincronizarEncuestasPluginMoodle extends Command
{
    protected $signature = 'encuestas-calidad:sincronizar-moodle
        {--dry-run : Muestra lo que se guardaría sin escribir en la BD}
        {--desde= : Releer desde esta fecha (Y-m-d) en lugar del cursor}
        {--curso=0 : Limitar a un curso de Moodle (id)}';

    protected $description = 'Sincroniza las respuestas del plugin Moodle mod_calidadfundae con las encuestas de calidad';

    public function handle(MoodleService $moodle, EncuestaCalidadService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !config('encuesta_calidad.plugin_sync_enabled')) {
            $this->warn('⏸ Sincronización del plugin DESACTIVADA (encuesta_calidad.plugin_sync_enabled = false).');
            return self::SUCCESS;
        }

        $since = $this->cursor();
        $courseId = (int) $this->option('curso');
        $limit = max(1, min(500, (int) config('encuesta_calidad.plugin_page_size', 200)));

        $this->info('🔄 Leyendo respuestas del plugin desde ' . ($since > 0 ? date('Y-m-d H:i:s', $since) : 'el principio') . ($courseId ? " · curso {$courseId}" : ''));

        $until = 0;
        $offset = 0;
        $guardadas = $errores = 0;

        try {
            do {
                $pagina = $moodle->getCalidadFundaeResponses($since, $until, $courseId, $limit, $offset);
                // La primera página fija la foto de la corrida
                $until = $pagina['until'] ?: $until;

                foreach ($pagina['responses'] as $r) {
                    try {
                        $datos = $service->mapearRespuestaPlugin($r);

                        if ($dryRun) {
                            $this->line(sprintf('  · %s · %s · %s · satisfacción=%s',
                                $datos['alumno_email'] ?? $datos['alumno_nombre'] ?? '—',
                                $datos['curso_resuelto'] ?? '—',
                                $datos['numero_accion'] ? $datos['numero_accion'] . '/' . $datos['numero_grupo'] : 'sin acción/grupo',
                                $datos['satisfaccion_general'] ?? '—'));
                        } else {
                            $service->guardarRespuesta($datos, 'moodle_plugin');
                        }
                        $guardadas++;
                    } catch (\Throwable $e) {
                        $errores++;
                        $this->error('  ❌ Respuesta ' . ($r['id'] ?? '?') . ': ' . $e->getMessage());
                        Log::warning('encuestas-calidad:sincronizar-moodle respuesta con error', ['id' => $r['id'] ?? null, 'error' => $e->getMessage()]);
                    }
                }

                $offset += count($pagina['responses']);
            } while ($pagina['hasmore'] && count($pagina['responses']) > 0);
        } catch (\Throwable $e) {
            $this->error('❌ Error llamando a Moodle: ' . $e->getMessage());
            Log::error('encuestas-calidad:sincronizar-moodle error', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        $this->info("Resumen — " . ($dryRun ? 'leídas' : 'guardadas') . ": {$guardadas} · errores: {$errores}" . ($dryRun ? ' (dry-run, nada guardado)' : ''));

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Timestamp desde el que leer: --desde, o el último guardado menos el solape. */
    protected function cursor(): int
    {
        if ($desde = $this->option('desde')) {
            return max(0, strtotime($desde . ' 00:00:00') - 1);
        }

        $max = (int) EncuestaCalidad::where('origen', 'moodle_plugin')->max('moodle_timemodified');

        return $max > 0 ? max(0, $max - (int) config('encuesta_calidad.plugin_solape_segundos', 60)) : 0;
    }
}
