<?php

namespace App\Console\Commands;

use App\Models\Candidato;
use App\Models\NotificacionLog;
use App\Mail\RecordatorioRequisitosMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatoriosCandidatos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'candidatos:enviar-recordatorios {--dry-run : Ejecutar sin enviar emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enviar recordatorios a candidatos con requisitos pendientes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN activado - No se enviarán emails');
        }

        $config = config('candidatos.recordatorios');

        if (!$config['activo'] && !$dryRun) {
            $this->error('❌ Los recordatorios están desactivados en la configuración');
            return self::FAILURE;
        }

        $this->info('🚀 Iniciando envío de recordatorios...');
        $this->newLine();

        // Obtener candidatos listos para recibir recordatorio
        $candidatos = Candidato::with(['requisitos.tipoRequisito', 'tipoCandidato'])
            ->listosParaRecordatorio()
            ->get();

        if ($candidatos->isEmpty()) {
            $this->info('✅ No hay candidatos que requieran recordatorio en este momento');
            return self::SUCCESS;
        }

        $this->info("📋 Encontrados {$candidatos->count()} candidatos para procesar");
        $this->newLine();

        $enviados = 0;
        $completados = 0;
        $pausados = 0;
        $errores = 0;

        foreach ($candidatos as $candidato) {
            $this->procesarCandidato($candidato, $dryRun, $enviados, $completados, $pausados, $errores);
        }

        // Resumen
        $this->newLine();
        $this->info('📊 Resumen de ejecución:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Recordatorios enviados', $enviados],
                ['Candidatos completados', $completados],
                ['Candidatos pausados', $pausados],
                ['Errores', $errores],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Procesar un candidato individual
     */
    private function procesarCandidato(
        Candidato $candidato,
        bool $dryRun,
        int &$enviados,
        int &$completados,
        int &$pausados,
        int &$errores
    ): void {
        $faltantes = $candidato->requisitosFaltantes();

        // Si no tiene requisitos faltantes, marcar como completo
        if ($faltantes->isEmpty()) {
            $this->line("✅ Candidato #{$candidato->id} ({$candidato->email}) - Sin requisitos pendientes, marcando como completo");
            
            if (!$dryRun) {
                $candidato->verificarYMarcarCompleto();
            }
            
            $completados++;
            return;
        }

        // Verificar si alcanzó el límite de recordatorios
        $maxRecordatorios = config('candidatos.recordatorios.max_recordatorios');
        if ($candidato->recordatorios_enviados >= $maxRecordatorios) {
            $this->warn("⏸️  Candidato #{$candidato->id} ({$candidato->email}) - Límite de recordatorios alcanzado, pausando");
            
            if (!$dryRun) {
                $candidato->pausar();
            }
            
            $pausados++;
            return;
        }

        // Enviar recordatorio
        $requisitosFaltantesArray = $faltantes->map(function ($requisito) {
            return [
                'nombre' => $requisito->tipoRequisito->nombre,
                'descripcion' => $requisito->tipoRequisito->descripcion,
            ];
        })->toArray();

        $numeroRecordatorio = $candidato->recordatorios_enviados + 1;
        $this->line("📧 Candidato #{$candidato->id} ({$candidato->email}) - Enviando recordatorio ({$numeroRecordatorio}/{$maxRecordatorios})");
        $this->line("   Requisitos faltantes: " . $faltantes->count());


        if (!$dryRun) {
            try {
                Mail::to($candidato->email)
                    ->bcc(config('candidatos.recordatorios.copia_email'))
                    ->send(
                    new RecordatorioRequisitosMail($candidato, $faltantes)
                );

                $candidato->registrarRecordatorio();

                NotificacionLog::registrarExito(
                    $candidato->id,
                    $requisitosFaltantesArray,
                    $candidato->email
                );

                $enviados++;
            } catch (\Exception $e) {
                $this->error("   ❌ Error al enviar: {$e->getMessage()}");

                NotificacionLog::registrarError(
                    $candidato->id,
                    $requisitosFaltantesArray,
                    $candidato->email,
                    $e->getMessage()
                );

                $errores++;
            }
        } else {
            $enviados++;
        }
    }
}
