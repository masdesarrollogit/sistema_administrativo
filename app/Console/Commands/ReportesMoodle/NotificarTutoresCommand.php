<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Mail\TutorReporteSemanalMail;
use App\Models\AlumnoNotificacionLog;
use App\Models\AlumnoProgresoMoodle;
use App\Models\ReportesMoodleSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotificarTutoresCommand extends Command
{
    protected $signature = 'reportes-moodle:notificar-tutores
        {--dry-run : Listar tutores sin enviar emails}';

    protected $description = 'Email semanal al tutor con sus alumnos no conectados (Fase 1) e inactivos (Fase 2)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN - no se enviarán emails ni se registrará en log');
        }

        if (!$dryRun && !ReportesMoodleSettings::notificacionesActivas()) {
            $this->warn('⏸ Notificaciones de Reportes Moodle DESACTIVADAS desde el dashboard. Saltando comando.');
            return self::SUCCESS;
        }

        $incluirExternos = (bool) config('reportes_moodle.reporte_no_conectados.incluir_tutores_externos', true);
        $hoy = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        $semanaEtiqueta = $hoy->format('d/m/Y');

        $snapshots = AlumnoProgresoMoodle::query()
            ->deHoy()
            ->whereHas('pivot.grupoFormativo', function ($q) use ($hoy) {
                $q->where('estado', 'en_curso')
                  ->whereDate('fecha_inicio', '<=', $hoy->toDateString())
                  ->whereDate('fecha_fin',    '>=', $hoy->toDateString());
            })
            ->where(function ($q) {
                $q->where('nunca_entro_curso', true)
                  ->orWhere(function ($qq) {
                      // Solo inactivos NO aprobados van al listado del tutor
                      $qq->where('inactivo', true)->where('aprobado', false);
                  })
                  ->orWhere(function ($qq) {
                      // Riesgo crítico (Reporte 3) — siempre no aprobados por definición
                      $qq->where('riesgo_critico', true)->where('aprobado', false);
                  })
                  ->orWhere(function ($qq) {
                      // Pre-cierre (Reporte 4) — sin cuestionario final en últimas 72h
                      $qq->where('pre_cierre', true)->where('aprobado', false);
                  })
                  ->orWhere(function ($qq) {
                      // Apto sin examen (Reporte 5) — nota>=50 sin cuestionario
                      $qq->where('apto_sin_examen', true)->where('aprobado', false);
                  })
                  ->orWhere('aprobado', true); // Reporte 6 — aprobados (sección positiva)
            })
            ->with([
                'alumno',
                'pivot.grupoFormativo.accionFormativa',
                'pivot.grupoFormativo.tutor',
            ])
            ->get();

        if ($snapshots->isEmpty()) {
            $this->info('✅ Sin alumnos no conectados ni inactivos esta semana.');
            return self::SUCCESS;
        }

        $porTutor = [];

        foreach ($snapshots as $snap) {
            $alumno = $snap->alumno;
            $grupo = $snap->pivot?->grupoFormativo;
            $tutor = $grupo?->tutor;

            if (!$alumno || !$grupo || !$tutor || !$tutor->email) {
                continue;
            }
            if (!$incluirExternos && $tutor->tipo !== 'interno') {
                continue;
            }

            $accion = $grupo->accionFormativa;
            $cursoNombre = $accion?->denominacion_limpia ?? ($accion?->denominacion ?? '—');
            $grupoEtiqueta = $accion && $grupo->id_grupo_fundae
                ? "{$accion->numero_accion}/{$grupo->id_grupo_fundae}"
                : (string) $grupo->id;

            $emailsAlumno = AlumnoNotificacionLog::query()
                ->where('alumno_id', $alumno->id)
                ->where('grupo_formativo_id', $grupo->id)
                ->whereIn('tipo', [
                    AlumnoNotificacionLog::TIPO_ALUMNO_NO_CONECTADO,
                    AlumnoNotificacionLog::TIPO_ALUMNO_INACTIVO,
                    AlumnoNotificacionLog::TIPO_ALUMNO_RIESGO_CRITICO,
                    AlumnoNotificacionLog::TIPO_ALUMNO_PRE_CIERRE,
                    AlumnoNotificacionLog::TIPO_ALUMNO_APTO_SIN_EXAMEN,
                ])
                ->where('exitoso', true)
                ->count();

            $base = [
                'alumno_id'       => $alumno->id,
                'alumno_nombre'   => $alumno->nombre_completo,
                'alumno_telefono' => $alumno->telefono ?? null,
                'curso'           => $cursoNombre,
                'grupo'           => $grupoEtiqueta,
                'emails_enviados' => $emailsAlumno,
            ];

            $porTutor[$tutor->id]['tutor'] = $tutor;

            if ($snap->pre_cierre) {
                // Pre-cierre tiene prioridad MÁXIMA sobre cualquier otro estado.
                $finCurso = CarbonImmutable::parse($grupo->fecha_fin)->endOfDay();
                $horasRestantes = max(0, (int) floor(CarbonImmutable::now('Europe/Madrid')->diffInSeconds($finCurso, false) / 3600));
                $porTutor[$tutor->id]['pre_cierre'][] = $base + [
                    'horas_restantes'              => $horasRestantes,
                    'nota_pct'                     => $snap->nota_total_porcentaje,
                    'cuestionario_final_realizado' => (bool) $snap->cuestionario_final_realizado,
                ];
            } elseif ($snap->nunca_entro_curso) {
                $diasDesdeInicio = (int) CarbonImmutable::parse($grupo->fecha_inicio)
                    ->startOfDay()
                    ->diffInDays($hoy, false);
                $porTutor[$tutor->id]['no_conectados'][] = $base + [
                    'dias_desde_inicio' => $diasDesdeInicio,
                ];
            } elseif ($snap->riesgo_critico) {
                // Riesgo crítico tiene prioridad sobre Inactivo en el listado del tutor
                // (un alumno puede ser ambos: dejarlo en la sección más urgente)
                $diasRestantes = max(0, (int) $hoy->diffInDays(
                    CarbonImmutable::parse($grupo->fecha_fin)->startOfDay(),
                    false,
                ));
                $porTutor[$tutor->id]['riesgo_critico'][] = $base + [
                    'dias_restantes'          => $diasRestantes,
                    'nota_pct'                => $snap->nota_total_porcentaje,
                    'pct_tiempo_transcurrido' => $snap->pct_tiempo_transcurrido !== null
                        ? (float) $snap->pct_tiempo_transcurrido
                        : null,
                ];
            } elseif ($snap->apto_sin_examen) {
                $diasRestantes = max(0, (int) $hoy->diffInDays(
                    CarbonImmutable::parse($grupo->fecha_fin)->startOfDay(),
                    false,
                ));
                $porTutor[$tutor->id]['apto_sin_examen'][] = $base + [
                    'dias_restantes' => $diasRestantes,
                    'nota_pct'       => $snap->nota_total_porcentaje,
                ];
            } elseif ($snap->inactivo) {
                $diasRestantes = max(0, (int) $hoy->diffInDays(
                    CarbonImmutable::parse($grupo->fecha_fin)->startOfDay(),
                    false,
                ));
                $porTutor[$tutor->id]['inactivos'][] = $base + [
                    'dias_inactivo'                => (int) $snap->dias_inactivo,
                    'dias_restantes'               => $diasRestantes,
                    'nota_pct'                     => $snap->nota_total_porcentaje,
                    'aprobado'                     => (bool) $snap->aprobado,
                    'cuestionario_final_realizado' => (bool) $snap->cuestionario_final_realizado,
                ];
            } elseif ($snap->aprobado) {
                $porTutor[$tutor->id]['aprobados'][] = $base + [
                    'nota_pct' => $snap->nota_total_porcentaje,
                ];
            }
        }

        if (empty($porTutor)) {
            $this->info('✅ Sin tutores elegibles tras aplicar filtros.');
            return self::SUCCESS;
        }

        $enviados = 0;
        $errores = 0;

        foreach ($porTutor as $bucket) {
            $tutor = $bucket['tutor'];
            $noConectados = $bucket['no_conectados'] ?? [];
            $inactivos = $bucket['inactivos'] ?? [];
            $riesgoCritico = $bucket['riesgo_critico'] ?? [];
            $preCierre = $bucket['pre_cierre'] ?? [];
            $aptoSinExamen = $bucket['apto_sin_examen'] ?? [];
            $aprobados = $bucket['aprobados'] ?? [];

            $this->line(sprintf(
                ' · %s (%s) — %d no conectados, %d inactivos, %d riesgo, %d pre-cierre, %d apto sin examen, %d aprobados',
                trim("{$tutor->nombre} {$tutor->apellido1}"),
                $tutor->email,
                count($noConectados),
                count($inactivos),
                count($riesgoCritico),
                count($preCierre),
                count($aptoSinExamen),
                count($aprobados),
            ));

            if ($dryRun) {
                $enviados++;
                continue;
            }

            $datosLog = [
                'tutor_id'           => $tutor->id,
                'tipo'               => AlumnoNotificacionLog::TIPO_TUTOR_REPORTE_SEMANAL,
                'fase'               => 1,
                'destinatario_email' => $tutor->email,
                'payload'            => [
                    'semana'                  => $semanaEtiqueta,
                    'n_no_conectados'         => count($noConectados),
                    'n_inactivos'             => count($inactivos),
                    'n_riesgo_critico'        => count($riesgoCritico),
                    'n_pre_cierre'            => count($preCierre),
                    'n_apto_sin_examen'       => count($aptoSinExamen),
                    'n_aprobados'             => count($aprobados),
                    'alumnos_no_conectados'   => array_column($noConectados, 'alumno_id'),
                    'alumnos_inactivos'       => array_column($inactivos, 'alumno_id'),
                    'alumnos_riesgo_critico'  => array_column($riesgoCritico, 'alumno_id'),
                    'alumnos_pre_cierre'      => array_column($preCierre, 'alumno_id'),
                    'alumnos_apto_sin_examen' => array_column($aptoSinExamen, 'alumno_id'),
                    'alumnos_aprobados'       => array_column($aprobados, 'alumno_id'),
                ],
            ];

            try {
                Mail::mailer('moodle')
                    ->to($tutor->email)
                    ->send(new TutorReporteSemanalMail(
                        tutor: $tutor,
                        noConectados: $noConectados,
                        inactivos: $inactivos,
                        riesgoCritico: $riesgoCritico,
                        preCierre: $preCierre,
                        aptoSinExamen: $aptoSinExamen,
                        aprobados: $aprobados,
                        semanaEtiqueta: $semanaEtiqueta,
                    ));

                AlumnoNotificacionLog::registrarExito($datosLog);
                $enviados++;
            } catch (\Throwable $e) {
                AlumnoNotificacionLog::registrarError($datosLog, $e->getMessage());
                $errores++;
                $this->error("   ✖ Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('📊 Resumen:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Tutores notificados', $enviados],
                ['Errores', $errores],
            ],
        );

        return self::SUCCESS;
    }
}
