<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Mail\AlumnoPreCierreMail;
use App\Models\AlumnoNotificacionLog;
use App\Models\AlumnoProgresoMoodle;
use App\Models\ReportesMoodleSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotificarPreCierreCommand extends Command
{
    protected $signature = 'reportes-moodle:notificar-pre-cierre
        {--dry-run : Listar sin enviar emails}';

    protected $description = 'Email diario al alumno en Pre-cierre (<=72h antes de fecha_fin, sin cuestionario final). Throttle 23h.';

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

        $throttleHoras = (int) config('reportes_moodle.reporte_pre_cierre.throttle_horas', 23);
        $umbralAprobado = (float) config('reportes_moodle.reporte_inactivos.umbral_aprobado_puntos', 50);
        $hoy = CarbonImmutable::now('Europe/Madrid');
        $umbralEnvio = $hoy->subHours($throttleHoras);

        $snapshots = AlumnoProgresoMoodle::query()
            ->deHoy()
            ->where('pre_cierre', true)
            ->with([
                'alumno',
                'pivot.grupoFormativo.accionFormativa',
                'pivot.grupoFormativo.tutor',
            ])
            ->get();

        if ($snapshots->isEmpty()) {
            $this->info('✅ No hay alumnos en Pre-cierre hoy.');
            return self::SUCCESS;
        }

        $enviados = 0;
        $omitidosThrottle = 0;
        $errores = 0;

        foreach ($snapshots as $snap) {
            $alumno = $snap->alumno;
            $grupo = $snap->pivot?->grupoFormativo;
            if (!$alumno || !$grupo || !$alumno->email) {
                continue;
            }

            // Throttle 23h por alumno+grupo
            $ultimoEnvio = AlumnoNotificacionLog::query()
                ->where('alumno_id', $alumno->id)
                ->where('grupo_formativo_id', $grupo->id)
                ->where('tipo', AlumnoNotificacionLog::TIPO_ALUMNO_PRE_CIERRE)
                ->where('exitoso', true)
                ->orderByDesc('enviado_at')
                ->value('enviado_at');

            if ($ultimoEnvio && CarbonImmutable::parse($ultimoEnvio)->greaterThan($umbralEnvio)) {
                $omitidosThrottle++;
                continue;
            }

            $finCurso = CarbonImmutable::parse($grupo->fecha_fin)->endOfDay();
            $horasRestantes = max(0, (int) floor(CarbonImmutable::now('Europe/Madrid')->diffInSeconds($finCurso, false) / 3600));
            $notaTotal = $snap->nota_total !== null ? (float) $snap->nota_total : null;
            $notaMax = $snap->nota_max !== null ? (float) $snap->nota_max : null;
            $notaPct = $snap->nota_total_porcentaje;
            $haAlcanzadoUmbral = $notaTotal !== null && $notaTotal >= $umbralAprobado;

            $this->line(sprintf(
                ' · %s (alumno_id=%d, grupo=%d, %dh restantes, nota=%s, umbral=%s)',
                $alumno->nombre_completo,
                $alumno->id,
                $grupo->id,
                $horasRestantes,
                $notaPct !== null ? "{$notaPct}%" : 'sin nota',
                $haAlcanzadoUmbral ? 'SI' : 'no',
            ));

            if ($dryRun) {
                $enviados++;
                continue;
            }

            $datosLog = [
                'alumno_id'          => $alumno->id,
                'grupo_formativo_id' => $grupo->id,
                'tipo'               => AlumnoNotificacionLog::TIPO_ALUMNO_PRE_CIERRE,
                'fase'               => 4,
                'destinatario_email' => $alumno->email,
                'payload'            => [
                    'horas_restantes'    => $horasRestantes,
                    'nota_total'         => $notaTotal,
                    'nota_max'           => $notaMax,
                    'nota_pct'           => $notaPct,
                    'umbral_alcanzado'   => $haAlcanzadoUmbral,
                ],
            ];

            try {
                Mail::mailer('moodle')
                    ->to($alumno->email)
                    ->send(new AlumnoPreCierreMail(
                        alumno: $alumno,
                        grupo: $grupo,
                        notaTotal: $notaTotal,
                        notaMax: $notaMax,
                        notaPorcentaje: $notaPct,
                        horasRestantes: $horasRestantes,
                        haAlcanzadoUmbral: $haAlcanzadoUmbral,
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
                ['Errores', $errores],
            ],
        );

        return self::SUCCESS;
    }
}
