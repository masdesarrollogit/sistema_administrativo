<?php

namespace App\Console\Commands;

use App\Models\Candidato;
use App\Mail\ResumenPendientesAdminMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarResumenPendientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'candidatos:enviar-resumen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia un resumen de candidatos pendientes al administrador';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Generando resumen de candidatos pendientes...');

        $candidatos = Candidato::pendientes()
            ->with(['tipoCandidato', 'empresa', 'empresaExterna', 'requisitos.tipoRequisito'])
            ->get();

        if ($candidatos->isEmpty()) {
            $this->info('✅ No hay candidatos pendientes para informar.');
            return;
        }

        $adminEmail = config('candidatos.recordatorios.copia_email');

        if (!$adminEmail) {
            $this->error('❌ No se ha configurado CANDIDATOS_COPIA_EMAIL en .env');
            return Command::FAILURE;
        }

        Mail::to($adminEmail)->send(new ResumenPendientesAdminMail($candidatos));

        $this->info("📧 Resumen enviado con éxito a {$adminEmail}");
        
        return Command::SUCCESS;
    }
}
