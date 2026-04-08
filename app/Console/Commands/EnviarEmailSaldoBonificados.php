<?php

namespace App\Console\Commands;

use App\Mail\SaldoBonificadoMensualMail;
use App\Models\Alumno;
use App\Models\BonificadoEmailExclusion;
use App\Models\Empresa;
use App\Models\ParticipanteBonificado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarEmailSaldoBonificados extends Command
{
    protected $signature = 'bonificados:enviar-email-saldo {--dry-run : Ejecutar sin enviar emails}';

    protected $description = 'Enviar email mensual con saldo disponible a participantes bonificados';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN activado - No se enviarán emails');
        }

        $config = config('candidatos.email_saldo_bonificados');

        if (!$config['activo'] && !$dryRun) {
            $this->error('❌ El envío de email de saldo a bonificados está desactivado en la configuración');
            return self::FAILURE;
        }

        $this->info('🚀 Iniciando envío de emails de saldo a participantes bonificados...');
        $this->newLine();

        // Cargar NIFs excluidos
        $nifsExcluidos = BonificadoEmailExclusion::nifsExcluidos();
        $this->info("🚫 NIFs excluidos del envío: " . count($nifsExcluidos));

        // Obtener participantes finalizados, agrupados por NIF+CIF únicos
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

        // Cache de empresas y emails para evitar queries repetidas
        $cacheEmpresas = [];
        $cacheEmails = [];

        foreach ($participantes as $p) {
            $nif = trim($p->nif_participante);
            $cif = trim($p->cif);

            // Verificar exclusión
            if (in_array($nif, $nifsExcluidos)) {
                $this->line("🚫 {$p->nombre} ({$nif}) - Excluido del envío");
                $excluidos++;
                continue;
            }

            // Resolver email desde tabla alumnos (cache por NIF)
            if (!isset($cacheEmails[$nif])) {
                $cacheEmails[$nif] = Alumno::where('nif', $nif)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->value('email');
            }
            $email = $cacheEmails[$nif];

            if (!$email) {
                $this->line("⚠️  {$p->nombre} ({$nif}) - Sin email en tabla alumnos");
                $sinEmail++;
                continue;
            }

            // Buscar empresa (cache por CIF)
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

            $this->line("📧 {$p->nombre} ({$nif}) → {$email} — Saldo: {$empresa->saldo_formateado}");

            if (!$dryRun) {
                try {
                    Mail::to($email)
                        ->send(new SaldoBonificadoMensualMail(
                            $p->nombre,
                            $empresa->cif,
                            $empresa->razon_social,
                            $empresa->saldo_formateado
                        ));

                    $enviados++;
                } catch (\Exception $e) {
                    $this->error("   ❌ Error al enviar a {$email}: {$e->getMessage()}");
                    $errores++;
                }
            } else {
                $enviados++;
            }
        }

        // Resumen
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
            ]
        );

        return self::SUCCESS;
    }
}
