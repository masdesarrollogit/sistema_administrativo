<?php

namespace App\Console\Commands\ReportesMoodle;

use App\Mail\AlumnoNoAptoMail;
use App\Models\AlumnoNoApto;
use App\Models\AlumnoReinicioOfrecimiento;
use App\Models\ReportesMoodleSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotificarNoAptosCommand extends Command
{
    protected $signature = 'reportes-moodle:notificar-no-aptos
        {--dry-run : Listar sin enviar emails}';

    protected $description = 'Email semanal al alumno No apto con oferta de reinicio (mailto al admin). Hasta 4 ofrecimientos en 30 días.';

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

        $ventanaDias       = (int) config('reportes_moodle.reporte_no_aptos.ventana_oferta_dias', 30);
        $intervaloDias     = (int) config('reportes_moodle.reporte_no_aptos.intervalo_dias', 7);
        $maxOfrecimientos  = (int) config('reportes_moodle.reporte_no_aptos.max_ofrecimientos', 4);

        $ahora = CarbonImmutable::now('Europe/Madrid');
        $hoy = $ahora->startOfDay();

        $candidatos = AlumnoNoApto::query()
            ->pendientesOfrecimiento()
            ->where('fecha_deteccion', '>=', $hoy->subDays($ventanaDias))
            ->with([
                'alumno',
                'grupoFormativo.accionFormativa',
                'ofrecimientos',
            ])
            ->get();

        if ($candidatos->isEmpty()) {
            $this->info('✅ No hay No aptos pendientes de ofrecimiento dentro de la ventana de ' . $ventanaDias . ' días.');
            return self::SUCCESS;
        }

        $enviados = 0;
        $omitidosIntervalo = 0;
        $omitidosTopeAlcanzado = 0;
        $errores = 0;

        foreach ($candidatos as $noApto) {
            $alumno = $noApto->alumno;
            $grupo = $noApto->grupoFormativo;
            if (!$alumno || !$grupo || !$alumno->email) {
                continue;
            }

            $ofrecimientosExitosos = $noApto->ofrecimientos->where('exitoso', true);
            $numAnteriores = $ofrecimientosExitosos->count();

            if ($numAnteriores >= $maxOfrecimientos) {
                $omitidosTopeAlcanzado++;
                // Marcar como caducado para futuras ejecuciones
                if (!$dryRun && $noApto->reinicio_estado !== AlumnoNoApto::ESTADO_CADUCADO) {
                    $noApto->update(['reinicio_estado' => AlumnoNoApto::ESTADO_CADUCADO]);
                }
                continue;
            }

            $ultimoEnvio = $ofrecimientosExitosos->last()?->fecha_envio;
            if ($ultimoEnvio && $ultimoEnvio->copy()->addDays($intervaloDias)->greaterThan($ahora)) {
                $omitidosIntervalo++;
                continue;
            }

            $numAhora = $numAnteriores + 1;

            $this->line(sprintf(
                ' · %s · curso=%s · ofrecimiento %d/%d',
                $alumno->nombre_completo,
                $grupo->accionFormativa?->denominacion_limpia ?? "(grupo {$grupo->id})",
                $numAhora,
                $maxOfrecimientos,
            ));

            if ($dryRun) {
                $enviados++;
                continue;
            }

            try {
                Mail::mailer('moodle')
                    ->to($alumno->email)
                    ->send(new AlumnoNoAptoMail(
                        alumno: $alumno,
                        grupo: $grupo,
                        noApto: $noApto,
                        numOfrecimiento: $numAhora,
                    ));

                AlumnoReinicioOfrecimiento::create([
                    'alumno_no_apto_id'  => $noApto->id,
                    'num_ofrecimiento'   => $numAhora,
                    'fecha_envio'        => now(),
                    'destinatario_email' => $alumno->email,
                    'exitoso'            => true,
                ]);

                // Actualizar estado del registro principal
                if ($noApto->reinicio_estado === AlumnoNoApto::ESTADO_PENDIENTE) {
                    $noApto->update(['reinicio_estado' => AlumnoNoApto::ESTADO_OFRECIDO]);
                }

                $enviados++;
            } catch (\Throwable $e) {
                AlumnoReinicioOfrecimiento::create([
                    'alumno_no_apto_id'  => $noApto->id,
                    'num_ofrecimiento'   => $numAhora,
                    'fecha_envio'        => now(),
                    'destinatario_email' => $alumno->email,
                    'exitoso'            => false,
                    'error_message'      => $e->getMessage(),
                ]);
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
                ["Omitidos por intervalo ({$intervaloDias}d)", $omitidosIntervalo],
                ["Omitidos por tope alcanzado ({$maxOfrecimientos})", $omitidosTopeAlcanzado],
                ['Errores', $errores],
            ],
        );

        return self::SUCCESS;
    }
}
