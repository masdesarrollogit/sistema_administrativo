<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Mail\AlumnoAptoSinExamenMail;
use App\Models\AlumnoNotificacionLog;
use App\Models\AlumnoProgresoMoodle;
use App\Models\ReportesMoodleSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotificarAptoSinExamenCommand extends Command
{
    protected $signature = 'reportes-moodle:notificar-apto-sin-examen
        {--dry-run : Listar sin enviar emails}';

    protected $description = 'Email semanal al alumno con nota>=50 que aún no realizó el cuestionario final. Throttle 7 días. R4 tiene prioridad.';

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

        $throttleHoras = (int) config('reportes_moodle.reporte_apto_sin_examen.throttle_horas', 168);
        $hoy = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        $umbralEnvio = CarbonImmutable::now('Europe/Madrid')->subHours($throttleHoras);

        $snapshots = AlumnoProgresoMoodle::query()
            ->deHoy()
            ->where('apto_sin_examen', true)
            ->where('pre_cierre', false)  // R4 tiene prioridad: si está en pre-cierre, R5 no envía
            ->where('aprobado', false)    // cinturón: si ya aprobó, no insistir
            ->with([
                'alumno',
                'pivot.grupoFormativo.accionFormativa',
                'matriculaAutonoma.accionFormativa',
                'pivot.grupoFormativo.tutor',
                'matriculaAutonoma.tutor',
            ])
            ->get();

        if ($snapshots->isEmpty()) {
            $this->info('✅ No hay alumnos en Apto sin examen pendientes de aviso hoy.');
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

            $ultimoEnvio = AlumnoNotificacionLog::query()
                ->where('alumno_id', $alumno->id)
                ->delOrigen($snap)
                ->where('tipo', AlumnoNotificacionLog::TIPO_ALUMNO_APTO_SIN_EXAMEN)
                ->where('exitoso', true)
                ->orderByDesc('enviado_at')
                ->value('enviado_at');

            if ($ultimoEnvio && CarbonImmutable::parse($ultimoEnvio)->greaterThan($umbralEnvio)) {
                $omitidosThrottle++;
                continue;
            }

            $diasRestantes = max(0, (int) $hoy->diffInDays($finCurso, false));
            $notaTotal = $snap->nota_total !== null ? (float) $snap->nota_total : null;
            $notaMax = $snap->nota_max !== null ? (float) $snap->nota_max : null;
            $notaPct = $snap->nota_total_porcentaje;

            $this->line(sprintf(
                ' · %s (alumno_id=%d, grupo=%s, nota=%s, %d días restantes)',
                $alumno->nombre_completo,
                $alumno->id,
                $snap->codigo_grupo ?? '—',
                $notaPct !== null ? "{$notaPct}%" : 'sin nota',
                $diasRestantes,
            ));

            if ($dryRun) {
                $enviados++;
                continue;
            }

            $datosLog = [
                'alumno_id'          => $alumno->id,
                ...$snap->columnasOrigenLog(),
                'tipo'               => AlumnoNotificacionLog::TIPO_ALUMNO_APTO_SIN_EXAMEN,
                'fase'               => 5,
                'destinatario_email' => $alumno->email,
                'payload'            => [
                    'dias_restantes' => $diasRestantes,
                    'nota_total'     => $notaTotal,
                    'nota_max'       => $notaMax,
                    'nota_pct'       => $notaPct,
                ],
            ];

            try {
                Mail::mailer('moodle')
                    ->to($alumno->email)
                    ->send(new AlumnoAptoSinExamenMail(
                        alumno: $alumno,
                        matricula: $matricula,
                        notaTotal: $notaTotal,
                        notaMax: $notaMax,
                        notaPorcentaje: $notaPct,
                        diasRestantes: $diasRestantes,
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
