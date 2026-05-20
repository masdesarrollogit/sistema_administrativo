<?php

namespace App\Services\Webcurso;

use App\Models\AlumnoCalificacionMoodle;
use App\Models\AlumnoProgresoMoodle;
use App\Models\GrupoFormativoAlumno;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlumnoProgresoSnapshotter
{
    public function __construct(protected MoodleReportingService $reporting)
    {
    }

    /**
     * Ejecuta el snapshot diario.
     *
     * @return array{procesados:int, escritos:int, errores:int, omitidos:int, mensajes:array<int,string>}
     */
    public function ejecutar(?int $alumnoId = null, bool $dryRun = false, string $fuente = 'cron'): array
    {
        $hoy = CarbonImmutable::now('Europe/Madrid')->toDateString();
        $resumen = ['procesados' => 0, 'escritos' => 0, 'errores' => 0, 'omitidos' => 0, 'mensajes' => []];

        $pivots = $this->seleccionarPivots($alumnoId);

        // Resolver IDs de categorías Moodle excluidas (Repaso, Foros, etc.)
        $nombresExcluidos = (array) config('reportes_moodle.moodle.categorias_excluidas', []);
        $categoriaIdsExcluidos = $this->reporting->resolverCategoriaIds($nombresExcluidos);
        if (!empty($categoriaIdsExcluidos)) {
            $resumen['mensajes'][] = 'Categorías Moodle excluidas: IDs ' . implode(',', $categoriaIdsExcluidos)
                . ' (' . implode(', ', $nombresExcluidos) . ')';
        }

        // Limpieza pre-bucle: borrar snapshots de hoy cuyos pivots ya no pasan el SQL
        // (curso finalizado, fecha_inicio futura, alumno desmatriculado, etc.).
        // Los borrados por categoría Moodle excluida se hacen DENTRO del bucle.
        if ($alumnoId === null && !$dryRun) {
            $pivotIdsActivos = $pivots->pluck('id')->all();
            $borrados = AlumnoProgresoMoodle::whereDate('fecha_snapshot', $hoy)
                ->whereNotIn('grupo_formativo_alumno_id', $pivotIdsActivos)
                ->delete();
            if ($borrados > 0) {
                $resumen['mensajes'][] = "Limpieza: {$borrados} snapshot(s) obsoleto(s) eliminados.";
            }
        }

        if ($pivots->isEmpty()) {
            $resumen['mensajes'][] = 'No hay alumnos elegibles (grupos en_curso 2026 con estado_moodle=matriculado).';
            return $resumen;
        }

        $moodleUserIds = $pivots->pluck('moodle_user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        try {
            $lastAccessGlobal = $this->reporting->getLastAccessGlobalBatch(
                $moodleUserIds,
                config('reportes_moodle.snapshot.lote_lastaccess', 50),
            );
        } catch (\Throwable $e) {
            Log::error('Snapshotter: error en batch lastaccess global - ' . $e->getMessage());
            $resumen['errores']++;
            $resumen['mensajes'][] = 'Fallo batch lastaccess global: ' . $e->getMessage();
            $lastAccessGlobal = [];
        }

        foreach ($pivots as $pivot) {
            $resumen['procesados']++;

            if (!$pivot->moodle_user_id || !$pivot->grupoFormativo?->moodle_course_id) {
                $resumen['omitidos']++;
                continue;
            }

            $moodleUserId = (int) $pivot->moodle_user_id;
            $moodleCourseId = (int) $pivot->grupoFormativo->moodle_course_id;
            $lastGlobal = $lastAccessGlobal[$moodleUserId] ?? null;

            try {
                $stats = $this->reporting->getUserCourseStats($moodleUserId, $moodleCourseId);
            } catch (\Throwable $e) {
                Log::warning("Snapshotter: error stats curso (user={$moodleUserId}, course={$moodleCourseId}): " . $e->getMessage());
                $resumen['errores']++;
                continue;
            }

            // Si Moodle no devuelve el curso para este usuario, la matrícula no está activa
            // (suspendida, desenrolada o nunca se completó). El reporte exige matrícula activa,
            // así que omitimos y limpiamos cualquier snapshot previo del día.
            if ($stats === null) {
                $resumen['omitidos']++;
                if (!$dryRun) {
                    AlumnoProgresoMoodle::where('grupo_formativo_alumno_id', $pivot->id)
                        ->whereDate('fecha_snapshot', $hoy)
                        ->delete();
                }
                continue;
            }

            // Saltar pivots cuyo curso Moodle está en una categoría excluida (Repaso, Foros…)
            $categoriaCurso = $stats['category'] ?? null;
            if ($categoriaCurso !== null && in_array($categoriaCurso, $categoriaIdsExcluidos, true)) {
                $resumen['omitidos']++;
                if (!$dryRun) {
                    AlumnoProgresoMoodle::where('grupo_formativo_alumno_id', $pivot->id)
                        ->whereDate('fecha_snapshot', $hoy)
                        ->delete();
                }
                continue;
            }

            // Capturar notas (Fase 2 — siempre, aunque no esté inactivo, así tenemos historial)
            $notas = ['items' => [], 'total' => null, 'final_quiz' => null];
            try {
                $notas = $this->reporting->getUserGrades($moodleUserId, $moodleCourseId);
            } catch (\Throwable $e) {
                Log::warning("Snapshotter: error notas (user={$moodleUserId}, course={$moodleCourseId}): " . $e->getMessage());
            }

            $lastaccessCurso = $stats['lastaccess'] ?? null;
            $umbralInactividad = (int) config('reportes_moodle.reporte_inactivos.umbral_inactividad_dias', 3);
            $umbralAprobado = (float) config('reportes_moodle.reporte_inactivos.umbral_aprobado_puntos', 50);
            $umbralTiempoRiesgo = (float) config('reportes_moodle.reporte_riesgo_critico.umbral_tiempo_pct', 50);

            $diasInactivo = $this->calcularDiasInactivo($lastaccessCurso);
            $notaTotal = $notas['total']['grade'] ?? null;
            $cuestionarioFinalRealizado = $notas['final_quiz'] !== null
                && $notas['final_quiz']['grade'] !== null;
            // Aprobado FUNDAE = nota total >= umbral (50) AND cuestionario final realizado.
            $aprobado = $notaTotal !== null
                && (float) $notaTotal >= $umbralAprobado
                && $cuestionarioFinalRealizado;

            // Estado "Inactivo" = entró al curso alguna vez y lleva más días que el umbral.
            // (la notificación por email aplicará el filtro adicional 'aprobado=false')
            $inactivo = $lastaccessCurso !== null
                && $lastaccessCurso > 0
                && $diasInactivo !== null
                && $diasInactivo > $umbralInactividad;

            // Reporte 3 — Riesgo crítico: entró al curso AND nota < 50 AND >= 50% del tiempo.
            // (los que nunca entraron al curso son Reporte 1, no Reporte 3, aunque su nota
            // total figure técnicamente como 0/2.94 — el problema principal es no haber entrado).
            $pctTiempo = $this->calcularPctTiempoTranscurrido(
                $pivot->grupoFormativo->fecha_inicio ?? null,
                $pivot->grupoFormativo->fecha_fin ?? null,
            );
            $riesgoCritico = $lastaccessCurso !== null
                && $lastaccessCurso > 0
                && !$aprobado
                && $notaTotal !== null
                && (float) $notaTotal < $umbralAprobado
                && $pctTiempo !== null
                && $pctTiempo >= $umbralTiempoRiesgo;

            // Reporte 4 — Pre-cierre: últimas 72h del curso AND cuestionario_final no realizado.
            $umbralPreCierreHoras = (int) config('reportes_moodle.reporte_pre_cierre.umbral_horas', 72);
            $horasHastaFin = $this->calcularHorasHastaFin($pivot->grupoFormativo->fecha_fin ?? null);
            $preCierre = $horasHastaFin !== null
                && $horasHastaFin >= 0
                && $horasHastaFin <= $umbralPreCierreHoras
                && !$cuestionarioFinalRealizado;

            // Reporte 5 — Apto sin examen: nota_total >= 50 AND cuestionario_final no realizado.
            // Es complementario a R3 (que es nota<50). El alumno ya cruzó el umbral, solo falta cerrar.
            $aptoSinExamen = $notaTotal !== null
                && (float) $notaTotal >= $umbralAprobado
                && !$cuestionarioFinalRealizado;

            $datos = [
                'alumno_id'                    => $pivot->alumno_id,
                'moodle_user_id'               => $moodleUserId,
                'moodle_course_id'             => $moodleCourseId,
                'lastaccess_global'            => $lastGlobal,
                'lastaccess_curso'             => $lastaccessCurso,
                'nunca_entro_sitio'            => ($lastGlobal === 0),
                'nunca_entro_curso'            => $lastaccessCurso === null ? true : ($lastaccessCurso === 0),
                'progress'                     => $stats['progress'] ?? null,
                'completed'                    => $stats['completed'] ?? null,
                'aprobado'                     => $aprobado,
                'cuestionario_final_realizado' => $cuestionarioFinalRealizado,
                'nota_total'                   => $notaTotal,
                'nota_max'                     => $notas['total']['grademax'] ?? null,
                'dias_inactivo'                => $diasInactivo,
                'inactivo'                     => $inactivo,
                'riesgo_critico'               => $riesgoCritico,
                'pre_cierre'                   => $preCierre,
                'apto_sin_examen'              => $aptoSinExamen,
                'pct_tiempo_transcurrido'      => $pctTiempo,
                'fecha_snapshot'               => $hoy,
                'ultimo_refresh_at'            => now(),
                'fuente'                       => $fuente,
            ];

            if ($dryRun) {
                $resumen['escritos']++;
                continue;
            }

            $progreso = AlumnoProgresoMoodle::updateOrCreate(
                [
                    'grupo_formativo_alumno_id' => $pivot->id,
                    'fecha_snapshot'            => $hoy,
                ],
                $datos,
            );

            // Reemplazar las calificaciones del día (idempotente)
            DB::transaction(function () use ($progreso, $notas) {
                $progreso->calificaciones()->delete();
                foreach ($notas['items'] as $item) {
                    AlumnoCalificacionMoodle::create([
                        'alumno_progreso_moodle_id' => $progreso->id,
                        'moodle_grade_item_id'      => $item['id'],
                        'itemname'                  => mb_substr($item['itemname'], 0, 255),
                        'itemtype'                  => $item['itemtype'],
                        'itemmodule'                => $item['itemmodule'],
                        'grade'                     => $item['grade'],
                        'grademax'                  => $item['grademax'],
                        'grademin'                  => $item['grademin'],
                        'percentageformatted'       => $item['percentageformatted'],
                        'lettergrade'               => $item['lettergrade'],
                        'is_course_total'           => $item['is_course_total'],
                        'is_final_quiz'             => $item['is_final_quiz'],
                        'graded_at'                 => $item['graded_at'],
                    ]);
                }
            });

            $resumen['escritos']++;
        }

        return $resumen;
    }

    /**
     * Calcula los días desde el último acceso al curso. Devuelve null si nunca entró.
     */
    protected function calcularDiasInactivo(?int $lastaccessCurso): ?int
    {
        if (!$lastaccessCurso || $lastaccessCurso === 0) {
            return null;
        }
        return max(0, (int) floor((time() - $lastaccessCurso) / 86400));
    }

    /**
     * Calcula las horas que quedan hasta fecha_fin (final del día Madrid).
     * Negativo si ya pasó. Null si no hay fecha.
     */
    protected function calcularHorasHastaFin($fechaFin): ?int
    {
        if (!$fechaFin) {
            return null;
        }
        // Re-anclar la fecha en Madrid para que endOfDay se calcule correctamente.
        $fechaStr = $fechaFin instanceof \DateTimeInterface
            ? $fechaFin->format('Y-m-d')
            : (string) $fechaFin;
        $fin = CarbonImmutable::parse($fechaStr, 'Europe/Madrid')->endOfDay();
        $ahora = CarbonImmutable::now('Europe/Madrid');
        return (int) floor($ahora->diffInSeconds($fin, false) / 3600);
    }

    /**
     * Calcula el porcentaje del tiempo del curso transcurrido entre fecha_inicio y fecha_fin.
     * Devuelve float entre 0 y 100 (saturado a [0, 100]). Null si las fechas no son válidas.
     */
    protected function calcularPctTiempoTranscurrido($fechaInicio, $fechaFin): ?float
    {
        if (!$fechaInicio || !$fechaFin) {
            return null;
        }

        $inicio = CarbonImmutable::parse($fechaInicio)->startOfDay();
        $fin = CarbonImmutable::parse($fechaFin)->startOfDay();
        $hoy = CarbonImmutable::now('Europe/Madrid')->startOfDay();

        $duracionTotal = $inicio->diffInSeconds($fin);
        if ($duracionTotal <= 0) {
            return null;
        }

        $transcurrido = $inicio->diffInSeconds($hoy, false);
        $pct = ($transcurrido / $duracionTotal) * 100;
        return round(max(0.0, min(100.0, $pct)), 2);
    }

    /**
     * Refresca un único pivote ahora mismo (acción manual desde la UI).
     */
    public function refrescar(GrupoFormativoAlumno $pivot): ?AlumnoProgresoMoodle
    {
        $resumen = $this->ejecutar(alumnoId: $pivot->alumno_id, dryRun: false, fuente: 'manual');

        return AlumnoProgresoMoodle::deHoy()
            ->where('grupo_formativo_alumno_id', $pivot->id)
            ->first();
    }

    /**
     * @return Collection<int, GrupoFormativoAlumno>
     */
    protected function seleccionarPivots(?int $alumnoId = null): Collection
    {
        $hoy = CarbonImmutable::now('Europe/Madrid')->toDateString();

        $query = GrupoFormativoAlumno::query()
            ->with(['grupoFormativo:id,moodle_course_id,fecha_inicio,fecha_fin,estado,tutor_id,accion_formativa_id'])
            ->where('estado_moodle', 'matriculado')
            ->whereNotNull('moodle_user_id')
            ->whereHas('grupoFormativo', function ($q) use ($hoy) {
                $q->where('estado', 'en_curso')
                  ->whereNotNull('moodle_course_id')
                  ->whereDate('fecha_inicio', '<=', $hoy)     // ya empezó
                  ->whereDate('fecha_fin',    '>=', $hoy)     // aún no ha finalizado
                  ->where(function ($qq) {
                      $qq->whereYear('fecha_inicio', 2026)
                         ->orWhereYear('fecha_fin', 2026);
                  });
            });

        if ($alumnoId !== null) {
            $query->where('alumno_id', $alumnoId);
        }

        return $query->get();
    }
}
