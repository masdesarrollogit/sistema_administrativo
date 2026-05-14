<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Mail\AlumnoAptoMail;
use App\Models\AlumnoNotificacionLog;
use App\Models\AlumnoProgresoMoodle;
use App\Models\ReportesMoodleSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotificarAptoCommand extends Command
{
    protected $signature = 'reportes-moodle:notificar-apto
        {--dry-run : Listar sin enviar emails}';

    protected $description = 'Email único de felicitación al alumno cuando aprueba el curso (nota>=50 + cuestionario final realizado).';

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

        // Subconsulta: alumnos que YA recibieron el email de R6 (no enviar otra vez)
        $yaNotificados = AlumnoNotificacionLog::query()
            ->where('tipo', AlumnoNotificacionLog::TIPO_ALUMNO_APTO)
            ->where('exitoso', true)
            ->selectRaw('CONCAT(alumno_id, "-", grupo_formativo_id) as clave')
            ->pluck('clave')
            ->all();
        $yaNotificadosSet = array_flip($yaNotificados);

        $snapshots = AlumnoProgresoMoodle::query()
            ->deHoy()
            ->where('aprobado', true)
            ->with([
                'alumno',
                'pivot.grupoFormativo.accionFormativa',
            ])
            ->get();

        if ($snapshots->isEmpty()) {
            $this->info('✅ No hay alumnos aprobados hoy.');
            return self::SUCCESS;
        }

        $enviados = 0;
        $omitidosYaNotificados = 0;
        $errores = 0;

        foreach ($snapshots as $snap) {
            $alumno = $snap->alumno;
            $grupo = $snap->pivot?->grupoFormativo;
            if (!$alumno || !$grupo || !$alumno->email) {
                continue;
            }

            $clave = "{$alumno->id}-{$grupo->id}";
            if (isset($yaNotificadosSet[$clave])) {
                $omitidosYaNotificados++;
                continue;
            }

            $notaTotal = $snap->nota_total !== null ? (float) $snap->nota_total : null;
            $notaMax = $snap->nota_max !== null ? (float) $snap->nota_max : null;
            $notaPct = $snap->nota_total_porcentaje;

            $this->line(sprintf(
                ' · %s (alumno_id=%d, grupo=%d, nota=%s)',
                $alumno->nombre_completo,
                $alumno->id,
                $grupo->id,
                $notaPct !== null ? "{$notaPct}%" : 'sin nota',
            ));

            if ($dryRun) {
                $enviados++;
                continue;
            }

            $datosLog = [
                'alumno_id'          => $alumno->id,
                'grupo_formativo_id' => $grupo->id,
                'tipo'               => AlumnoNotificacionLog::TIPO_ALUMNO_APTO,
                'fase'               => 6,
                'destinatario_email' => $alumno->email,
                'payload'            => [
                    'nota_total' => $notaTotal,
                    'nota_max'   => $notaMax,
                    'nota_pct'   => $notaPct,
                ],
            ];

            try {
                Mail::mailer('moodle')
                    ->to($alumno->email)
                    ->send(new AlumnoAptoMail(
                        alumno: $alumno,
                        grupo: $grupo,
                        notaTotal: $notaTotal,
                        notaMax: $notaMax,
                        notaPorcentaje: $notaPct,
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
                ['Omitidos (ya notificados antes)', $omitidosYaNotificados],
                ['Errores', $errores],
            ],
        );

        return self::SUCCESS;
    }
}
