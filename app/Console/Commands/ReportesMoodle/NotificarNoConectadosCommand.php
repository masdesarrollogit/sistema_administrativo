<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Mail\AlumnoNoConectadoMail;
use App\Models\AlumnoNotificacionLog;
use App\Models\AlumnoProgresoMoodle;
use App\Models\ReportesMoodleSettings;
use App\Support\MoodlePassword;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotificarNoConectadosCommand extends Command
{
    protected $signature = 'reportes-moodle:notificar-no-conectados
        {--dry-run : Listar candidatos sin enviar emails}';

    protected $description = 'Envía email al alumno que no ha entrado a su curso (días 3/6/9 desde inicio, con tope configurable)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $dispatched = 0;
        $omitidosTope = 0;
        $omitidosDia = 0;
        $errores = 0;

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN - no se enviarán emails ni se registrará en log');
        }

        if (!$dryRun && !ReportesMoodleSettings::notificacionesActivas()) {
            $this->warn('⏸ Notificaciones de Reportes Moodle DESACTIVADAS desde el dashboard. Saltando comando.');
            return self::SUCCESS;
        }

        $tope = (int) config('reportes_moodle.reporte_no_conectados.tope_reenvios_alumno', 3);
        $diasDisparo = (array) config('reportes_moodle.reporte_no_conectados.dias_disparo_alumno', [3, 6, 9]);

        $hoy = CarbonImmutable::now('Europe/Madrid')->startOfDay();

        $snapshots = AlumnoProgresoMoodle::query()
            ->deHoy()
            ->where('nunca_entro_curso', true)
            ->where('pre_cierre', false) // R4 tiene prioridad: si está en pre-cierre, R1 no envía
            ->with([
                'alumno',
                'pivot.grupoFormativo.accionFormativa',
                'matriculaAutonoma.accionFormativa',
                'pivot.grupoFormativo.tutor',
                'matriculaAutonoma.tutor',
            ])
            ->get();

        if ($snapshots->isEmpty()) {
            $this->info('✅ No hay alumnos no conectados en el snapshot de hoy.');
            return self::SUCCESS;
        }

        foreach ($snapshots as $snap) {
            $alumno = $snap->alumno;
            $pivot = $snap->pivot;
            $matricula = $snap->origen();

            if (!$alumno || !$matricula || !$alumno->email) {
                continue;
            }

            $inicioGrupo = CarbonImmutable::parse($snap->fecha_inicio_curso)->startOfDay();
            $diasDesdeInicio = (int) $inicioGrupo->diffInDays($hoy, false);

            // Skip grupos que aún no han empezado.
            if ($diasDesdeInicio <= 0 || !in_array($diasDesdeInicio, $diasDisparo, true)) {
                $omitidosDia++;
                continue;
            }

            $enviados = AlumnoNotificacionLog::query()
                ->where('alumno_id', $alumno->id)
                ->delOrigen($snap)
                ->where('tipo', AlumnoNotificacionLog::TIPO_ALUMNO_NO_CONECTADO)
                ->where('exitoso', true)
                ->count();

            if ($enviados >= $tope) {
                $omitidosTope++;
                continue;
            }

            $password = MoodlePassword::generar($alumno->nombre);
            $username = $pivot?->moodle_username ?? $snap->matriculaAutonoma?->moodle_username ?? $alumno->email;
            $intento = $enviados + 1;

            $this->line(sprintf(
                ' · %s (alumno_id=%d, grupo=%s, día %d, intento %d/%d)',
                $alumno->nombre_completo,
                $alumno->id,
                $snap->codigo_grupo ?? '—',
                $diasDesdeInicio,
                $intento,
                $tope,
            ));

            if ($dryRun) {
                $dispatched++;
                continue;
            }

            $datosLog = [
                'alumno_id'         => $alumno->id,
                ...$snap->columnasOrigenLog(),
                'tipo'              => AlumnoNotificacionLog::TIPO_ALUMNO_NO_CONECTADO,
                'fase'              => 1,
                'destinatario_email' => $alumno->email,
                'payload'           => [
                    'dias_desde_inicio' => $diasDesdeInicio,
                    'intento'           => $intento,
                    'tope'              => $tope,
                ],
            ];

            try {
                Mail::mailer('moodle')
                    ->to($alumno->email)
                    ->send(new AlumnoNoConectadoMail(
                        alumno: $alumno,
                        matricula: $matricula,
                        username: $username,
                        password: $password,
                        diasDesdeInicio: $diasDesdeInicio,
                        intentoNumero: $intento,
                    ));

                AlumnoNotificacionLog::registrarExito($datosLog);
                $dispatched++;
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
                ['Emails ' . ($dryRun ? 'a enviar' : 'enviados'), $dispatched],
                ['Omitidos por día (no aplica 3/6/9)', $omitidosDia],
                ['Omitidos por tope alcanzado', $omitidosTope],
                ['Errores', $errores],
            ],
        );

        return self::SUCCESS;
    }
}
