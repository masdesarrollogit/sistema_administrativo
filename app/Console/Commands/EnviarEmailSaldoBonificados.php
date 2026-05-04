<?php

namespace App\Console\Commands;

use App\Mail\SaldoBonificadoMensualMail;
use App\Models\Alumno;
use App\Models\BonificadoEmailEnvio;
use App\Models\BonificadoEmailExclusion;
use App\Models\Empresa;
use App\Models\ParticipanteBonificado;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class EnviarEmailSaldoBonificados extends Command
{
    protected $signature = 'bonificados:enviar-email-saldo
        {--dry-run : Ejecutar sin enviar emails ni registrar}
        {--manual : Marcar el envío como manual en lugar de cron}';

    protected $description = 'Enviar email mensual con saldo disponible a participantes bonificados';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $metodo = $this->option('manual') ? 'manual' : 'cron';

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN activado - No se enviarán emails ni se registrarán envíos');
        }

        $config = config('candidatos.email_saldo_bonificados');

        if (!$config['activo'] && !$dryRun) {
            $this->error('❌ El envío de email de saldo a bonificados está desactivado en la configuración');
            return self::FAILURE;
        }

        // ⚠ REGLA INNEGOCIABLE: en cualquier entorno != production no se debe
        // enviar a destinatarios reales ni aplicar CCs reales. Mailpit captura
        // todo via SMTP local, y AppServiceProvider además aplica Mail::alwaysTo.
        $esProduccion = app()->environment('production');
        $forzarTesting = (bool) env('MAIL_FORCE_TESTING', !$esProduccion);

        if ($forzarTesting) {
            $this->warn('🧪 MODO PRUEBA: emails redirigidos a Mailpit, sin CC reales (empresa ni admin)');
        }

        $this->info('🚀 Iniciando envío de emails de saldo a participantes bonificados...');
        $this->newLine();

        $nifsExcluidos = BonificadoEmailExclusion::nifsExcluidos();
        $this->info("🚫 NIFs excluidos del envío: " . count($nifsExcluidos));

        $participantes = ParticipanteBonificado::where('estado', 'Finalizado')
            ->whereNotNull('nif_participante')
            ->where('nif_participante', '!=', '')
            ->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->select('nif_participante', 'nombre', 'cif')
            ->distinct()
            ->get()
            ->unique(fn ($p) => $p->nif_participante . '|' . $p->cif);

        if ($participantes->isEmpty()) {
            $this->info('✅ No hay participantes bonificados finalizados para procesar');
            return self::SUCCESS;
        }

        $this->info("📋 Encontrados {$participantes->count()} participantes únicos (NIF+CIF) para procesar");
        $this->newLine();

        $enviados = 0;
        $excluidos = 0;
        $sinEmail = 0;
        $sinEmpresa = 0;
        $sinSaldo = 0;
        $errores = 0;

        $cacheEmpresas = [];
        $cacheEmails = [];

        foreach ($participantes as $p) {
            $nif = trim($p->nif_participante);
            $cif = trim($p->cif);

            if (in_array($nif, $nifsExcluidos)) {
                $this->line("🚫 {$p->nombre} ({$nif}) - Excluido del envío");
                $excluidos++;
                continue;
            }

            if (!isset($cacheEmails[$nif])) {
                $cacheEmails[$nif] = Alumno::where('nif', $nif)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->value('email');
            }
            $emailReal = $cacheEmails[$nif];

            if (!$emailReal) {
                $this->line("⚠️  {$p->nombre} ({$nif}) - Sin email en tabla alumnos");
                $sinEmail++;
                continue;
            }

            if (!isset($cacheEmpresas[$cif])) {
                $cacheEmpresas[$cif] = Empresa::where('cif', $cif)->first();
            }
            $empresa = $cacheEmpresas[$cif];

            if (!$empresa) {
                $this->line("⚠️  {$p->nombre} ({$nif}) - Empresa {$cif} no encontrada");
                $sinEmpresa++;
                continue;
            }

            if ($empresa->credito_disponible <= 0) {
                $this->line("⏭️  {$p->nombre} ({$nif}) - Empresa {$cif} sin saldo disponible");
                $sinSaldo++;
                continue;
            }

            // Determinar destinatario y CCs según entorno
            $emailCcEmpresa = $empresa->email ?: null;
            $emailCcAdmin = $config['cc_admin'] ?? null;

            if ($forzarTesting) {
                // Redirigir destinatario a dirección de prueba; no aplicar CCs reales
                $destinatario = env('MAIL_FORCE_TO', 'pruebas@webcurso.local');
                $ccs = [];
            } else {
                $destinatario = $emailReal;
                $ccs = array_filter([$emailCcEmpresa, $emailCcAdmin]);
            }

            $listaCcs = empty($ccs) ? '(sin CC)' : implode(', ', $ccs);
            $this->line("📧 {$p->nombre} ({$nif}) → {$destinatario} — Saldo: {$empresa->saldo_formateado} — CC: {$listaCcs}");

            if (!$dryRun) {
                try {
                    Mail::to($destinatario)
                        ->send(new SaldoBonificadoMensualMail(
                            $p->nombre,
                            $empresa->cif,
                            $empresa->razon_social,
                            $empresa->saldo_formateado,
                            $ccs
                        ));

                    BonificadoEmailEnvio::create([
                        'nif' => $nif,
                        'cif' => $cif,
                        'email_destinatario' => $emailReal, // siempre se guarda el real para auditoría
                        'email_cc_empresa' => $emailCcEmpresa,
                        'email_cc_admin' => $emailCcAdmin,
                        'nombre_participante' => $p->nombre,
                        'razon_social' => $empresa->razon_social,
                        'saldo_enviado' => (float) ($empresa->credito_disponible ?? 0),
                        'metodo' => $metodo,
                        'modo_prueba' => $forzarTesting,
                        'enviado_at' => Carbon::now(),
                    ]);

                    $enviados++;
                } catch (\Exception $e) {
                    $this->error("   ❌ Error al enviar a {$destinatario}: {$e->getMessage()}");
                    $errores++;
                }
            } else {
                $enviados++;
            }
        }

        $this->newLine();
        $this->info('📊 Resumen de ejecución:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Emails enviados' . ($dryRun ? ' (simulados)' : ''), $enviados],
                ['Excluidos por admin', $excluidos],
                ['Sin email en alumnos', $sinEmail],
                ['Empresa no encontrada', $sinEmpresa],
                ['Empresa sin saldo', $sinSaldo],
                ['Errores de envío', $errores],
                ['Modo prueba', $forzarTesting ? 'SÍ (sin destinatarios reales)' : 'NO (producción)'],
                ['Método', $metodo],
            ]
        );

        return self::SUCCESS;
    }
}
