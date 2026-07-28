<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Mail\AlumnoRiesgoCriticoMail;
use App\Models\AlumnoNotificacionLog;
use App\Models\AlumnoProgresoMoodle;
use App\Models\ReportesMoodleSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotificarRiesgoCriticoCommand extends Command
{
    protected $signature = 'reportes-moodle:notificar-riesgo-critico
        {--dry-run : Listar sin enviar emails}';

    protected $description = 'Email al alumno en Riesgo crítico (nota<50 + >=50% tiempo curso). Throttle 7 días, sin tope.';

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

        $throttleHoras = (int) config('reportes_moodle.reporte_riesgo_critico.throttle_horas', 168);
        $hoy = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        $umbralEnvio = CarbonImmutable::now('Europe/Madrid')->subHours($throttleHoras);

        $snapshots = AlumnoProgresoMoodle::query()
            ->deHoy()
            ->where('riesgo_critico', true)
            ->where('aprobado', false)   // cinturón extra: si aprobó, no insistir
            ->where('pre_cierre', false) // R4 tiene prioridad: si está en pre-cierre, R3 no envía
            ->with([
                'alumno',
                'pivot.grupoFormativo.accionFormativa',
                'matriculaAutonoma.accionFormativa',
                'pivot.grupoFormativo.tutor',
                'matriculaAutonoma.tutor',
            ])
            ->get();

        if ($snapshots->isEmpty()) {
            $this->info('✅ No hay alumnos en Riesgo crítico hoy.');
            return self::SUCCESS;
        }

        $enviados = 0;
        $omitidosThrottle = 0;
        $omitidosCursoFinalizado = 0;
        $errores = 0;

        foreach ($snapshots as $snap) {
            $alumno = $snap->alumno;
            $matricula = $snap->origen();

            if (!$alumno || !$matricula || !$alumno->email) {
                continue;
            }

            $finCurso = CarbonImmutable::parse($snap->fecha_fin_curso)->startOfDay();
            if ($finCurso->lessThan($hoy)) {
                $omitidosCursoFinalizado++;
                continue;
            }

            // Throttle (default 7 días) por alumno+grupo
            $ultimoEnvio = AlumnoNotificacionLog::query()
                ->where('alumno_id', $alumno->id)
                ->delOrigen($snap)
                ->where('tipo', AlumnoNotificacionLog::TIPO_ALUMNO_RIESGO_CRITICO)
                ->where('exitoso', true)
                ->orderByDesc('enviado_at')
                ->value('enviado_at');

            if ($ultimoEnvio && CarbonImmutable::parse($ultimoEnvio)->greaterThan($umbralEnvio)) {
                $omitidosThrottle++;
                continue;
            }

            $diasRestantes = max(0, (int) $hoy->diffInDays($finCurso, false));
            $notaPct = $snap->nota_total_porcentaje;
            $pctTiempo = $snap->pct_tiempo_transcurrido !== null ? (float) $snap->pct_tiempo_transcurrido : null;

            $this->line(sprintf(
                ' · %s (alumno_id=%d, grupo=%s, nota=%s, tiempo=%s%%, %d días restantes)',
                $alumno->nombre_completo,
                $alumno->id,
                $snap->codigo_grupo ?? '—',
                $notaPct !== null ? "{$notaPct}%" : 'sin nota',
                $pctTiempo !== null ? number_format($pctTiempo, 0) : '?',
                $diasRestantes,
            ));

            if ($dryRun) {
                $enviados++;
                continue;
            }

            $datosLog = [
                'alumno_id'          => $alumno->id,
                ...$snap->columnasOrigenLog(),
                'tipo'               => AlumnoNotificacionLog::TIPO_ALUMNO_RIESGO_CRITICO,
                'fase'               => 3,
                'destinatario_email' => $alumno->email,
                'payload'            => [
                    'dias_restantes'          => $diasRestantes,
                    'nota_total'              => $snap->nota_total !== null ? (float) $snap->nota_total : null,
                    'nota_max'                => $snap->nota_max !== null ? (float) $snap->nota_max : null,
                    'nota_pct'                => $notaPct,
                    'pct_tiempo_transcurrido' => $pctTiempo,
                ],
            ];

            try {
                Mail::mailer('moodle')
                    ->to($alumno->email)
                    ->send(new AlumnoRiesgoCriticoMail(
                        alumno: $alumno,
                        matricula: $matricula,
                        notaTotal: $snap->nota_total !== null ? (float) $snap->nota_total : null,
                        notaMax: $snap->nota_max !== null ? (float) $snap->nota_max : null,
                        notaPorcentaje: $notaPct,
                        diasRestantes: $diasRestantes,
                        pctTiempoTranscurrido: $pctTiempo,
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
                ['Emails ' . ($dryRun ? 'a enviar' : 'enviados'), $enviados],
                ["Omitidos por throttle ({$throttleHoras}h)", $omitidosThrottle],
                ['Omitidos por curso finalizado', $omitidosCursoFinalizado],
                ['Errores', $errores],
            ],
        );

        return self::SUCCESS;
    }
}
