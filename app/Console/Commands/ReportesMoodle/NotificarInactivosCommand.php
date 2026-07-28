<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Mail\AlumnoInactivoMail;
use App\Models\AlumnoNotificacionLog;
use App\Models\AlumnoProgresoMoodle;
use App\Models\ReportesMoodleSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotificarInactivosCommand extends Command
{
    protected $signature = 'reportes-moodle:notificar-inactivos
        {--dry-run : Listar sin enviar emails}';

    protected $description = 'Email diario al alumno inactivo (>3 días sin entrar al curso) que aún NO ha aprobado. Throttle de 72h por alumno';

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

        $throttleHoras = (int) config('reportes_moodle.reporte_inactivos.throttle_horas', 72);
        $hoy = CarbonImmutable::now('Europe/Madrid')->startOfDay();
        $umbralEnvio = CarbonImmutable::now('Europe/Madrid')->subHours($throttleHoras);

        $snapshots = AlumnoProgresoMoodle::query()
            ->deHoy()
            ->where('inactivo', true)
            ->where('aprobado', false)   // No rescatar a quien ya aprobó
            ->with([
                'alumno',
                'pivot.grupoFormativo.accionFormativa',
                'matriculaAutonoma.accionFormativa',
                'pivot.grupoFormativo.tutor',
                'matriculaAutonoma.tutor',
            ])
            ->get();

        if ($snapshots->isEmpty()) {
            $this->info('✅ No hay alumnos inactivos pendientes de rescate hoy.');
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

            // Verificar que el curso siga activo (extra cinturón)
            $finCurso = CarbonImmutable::parse($snap->fecha_fin_curso)->startOfDay();
            if ($finCurso->lessThan($hoy)) {
                $omitidosCursoFinalizado++;
                continue;
            }

            // Throttle 72h por alumno+grupo
            $ultimoEnvio = AlumnoNotificacionLog::query()
                ->where('alumno_id', $alumno->id)
                ->delOrigen($snap)
                ->where('tipo', AlumnoNotificacionLog::TIPO_ALUMNO_INACTIVO)
                ->where('exitoso', true)
                ->orderByDesc('enviado_at')
                ->value('enviado_at');

            if ($ultimoEnvio && CarbonImmutable::parse($ultimoEnvio)->greaterThan($umbralEnvio)) {
                $omitidosThrottle++;
                continue;
            }

            $diasRestantes = max(0, (int) $hoy->diffInDays($finCurso, false));
            $diasInactivo = (int) $snap->dias_inactivo;
            $notaPct = $snap->nota_total_porcentaje;

            $this->line(sprintf(
                ' · %s (alumno_id=%d, grupo=%s, %d días inactivo, %d días restantes, nota=%s)',
                $alumno->nombre_completo,
                $alumno->id,
                $snap->codigo_grupo ?? '—',
                $diasInactivo,
                $diasRestantes,
                $notaPct !== null ? "{$notaPct}%" : 'sin nota',
            ));

            if ($dryRun) {
                $enviados++;
                continue;
            }

            $datosLog = [
                'alumno_id'          => $alumno->id,
                ...$snap->columnasOrigenLog(),
                'tipo'               => AlumnoNotificacionLog::TIPO_ALUMNO_INACTIVO,
                'fase'               => 2,
                'destinatario_email' => $alumno->email,
                'payload'            => [
                    'dias_inactivo'  => $diasInactivo,
                    'dias_restantes' => $diasRestantes,
                    'nota_total'     => $snap->nota_total !== null ? (float) $snap->nota_total : null,
                    'nota_max'       => $snap->nota_max !== null ? (float) $snap->nota_max : null,
                    'nota_pct'       => $notaPct,
                ],
            ];

            try {
                Mail::mailer('moodle')
                    ->to($alumno->email)
                    ->send(new AlumnoInactivoMail(
                        alumno: $alumno,
                        matricula: $matricula,
                        notaTotal: $snap->nota_total !== null ? (float) $snap->nota_total : null,
                        notaMax: $snap->nota_max !== null ? (float) $snap->nota_max : null,
                        notaPorcentaje: $notaPct,
                        diasInactivo: $diasInactivo,
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
