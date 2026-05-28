<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\ZohoBooksContact;
use App\Services\Zoho\ZohoBooksService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ZohoSyncContacts extends Command
{
    protected $signature = 'zoho:sync-contacts
                            {--limit= : Limita el número de contactos sincronizados (para pruebas)}
                            {--skip-detail : No pide /contacts/{id} (más rápido pero sin custom_fields)}';

    protected $description = 'Sincroniza los contactos de Zoho Books a la cache local y mapea zoho_contact_id en empresas por CIF.';

    public function handle(ZohoBooksService $books): int
    {
        $limit       = $this->option('limit') ? (int) $this->option('limit') : null;
        $skipDetail  = (bool) $this->option('skip-detail');

        $token = $books->token();
        if (! $token->refresh_token) {
            $this->error('No hay refresh_token Zoho. Conecta primero desde /webcurso/zoho/connect.');
            return self::FAILURE;
        }

        $orgId = $books->getOrganizationId();
        $this->info("Sincronizando contactos (org={$orgId})…");

        $contadores = [
            'procesados'   => 0,
            'con_cif'      => 0,
            'sin_cif'      => 0,
            'empresas_ok'  => 0,
            'errores'      => 0,
        ];

        $manejarContacto = function (array $contacto) use ($books, $skipDetail, $orgId, &$contadores, $limit) {
            if ($limit !== null && $contadores['procesados'] >= $limit) {
                return;
            }

            $contadores['procesados']++;
            $contactId = $contacto['contact_id'] ?? null;

            if (! $contactId) {
                $contadores['errores']++;
                return;
            }

            try {
                // Pedir detalle (incluye custom_fields). El listado en versiones
                // antiguas de Zoho no traía custom_fields; el detalle es seguro.
                $detalle = $skipDetail ? $contacto : $books->getContact($contactId);
            } catch (Throwable $e) {
                $this->warn("  · contact_id={$contactId}: error en detalle: {$e->getMessage()}");
                $detalle = $contacto;
            }

            $cif = $books->extractCifFromContact($detalle);

            $datos = [
                'contact_id'                    => $contactId,
                'organization_id'               => $orgId,
                'contact_name'                  => $detalle['contact_name']  ?? null,
                'company_name'                  => $detalle['company_name']  ?? null,
                'cif'                           => $cif,
                'email'                         => $detalle['email']         ?? null,
                'phone'                         => $detalle['phone']         ?? null,
                'mobile'                        => $detalle['mobile']        ?? null,
                'contact_type'                  => $detalle['contact_type']  ?? null,
                'status'                        => $detalle['status']        ?? null,
                'outstanding_receivable_amount' => (float) ($detalle['outstanding_receivable_amount'] ?? 0),
                'currency_code'                 => $detalle['currency_code'] ?? null,
                'raw'                           => $detalle,
                'last_synced_at'                => Carbon::now(),
            ];

            ZohoBooksContact::updateOrCreate(
                ['contact_id' => $contactId],
                $datos
            );

            if ($cif) {
                $contadores['con_cif']++;
                $empresa = Empresa::where('cif', $cif)->first();
                if ($empresa) {
                    if ($empresa->zoho_contact_id !== $contactId) {
                        $empresa->zoho_contact_id = $contactId;
                        $empresa->save();
                    }
                    $contadores['empresas_ok']++;
                }
            } else {
                $contadores['sin_cif']++;
            }
        };

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% contactos [%bar%] %elapsed:6s%');
        $bar->start();

        try {
            $books->listAllContacts(function (array $c) use ($manejarContacto, $bar, $limit, &$contadores) {
                if ($limit !== null && $contadores['procesados'] >= $limit) {
                    return;
                }
                $manejarContacto($c);
                $bar->advance();
            }, 200);
        } catch (Throwable $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('Sync abortado: ' . $e->getMessage());
            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'OK · %d contactos · %d con CIF · %d sin CIF · %d empresas matcheadas · %d errores',
            $contadores['procesados'],
            $contadores['con_cif'],
            $contadores['sin_cif'],
            $contadores['empresas_ok'],
            $contadores['errores'],
        ));

        return self::SUCCESS;
    }
}
