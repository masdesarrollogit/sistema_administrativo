<?php

namespace App\Console\Commands;

use App\Services\Zoho\ZohoBooksService;
use Illuminate\Console\Command;
use Throwable;

class ZohoProbeContact extends Command
{
    protected $signature = 'zoho:probe-contact';

    protected $description = 'Vuelca el JSON crudo de un contacto y una factura para descubrir nombres de campos (CIF, email, teléfono).';

    public function handle(ZohoBooksService $books): int
    {
        $this->info('Sondeando estructura de Zoho Books…');

        // 1. Listado de contactos
        try {
            $list = $books->listContacts(1, 3);
        } catch (Throwable $e) {
            $this->error('Error listando contactos: ' . $e->getMessage());
            return self::FAILURE;
        }

        $contactos = $list['contacts'] ?? [];

        if (empty($contactos)) {
            $this->warn('No hay contactos en la cuenta. Crea al menos uno para sondear.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Claves del listado de contactos (/contacts):');
        $this->line('  ' . implode(', ', array_keys($contactos[0])));

        // 2. Detalle del primer contacto (para custom_fields)
        $contactId = $contactos[0]['contact_id'] ?? null;

        if (! $contactId) {
            $this->warn('El primer contacto no tiene contact_id.');
            return self::FAILURE;
        }

        try {
            $detalle = $books->request('GET', "contacts/{$contactId}");
        } catch (Throwable $e) {
            $this->error('Error obteniendo detalle del contacto ' . $contactId . ': ' . $e->getMessage());
            return self::FAILURE;
        }

        $contactoCompleto = $detalle['contact'] ?? [];

        $this->newLine();
        $this->info('Claves del detalle de contacto (/contacts/{id}):');
        $this->line('  ' . implode(', ', array_keys($contactoCompleto)));

        // 3. Custom fields
        $this->newLine();
        $this->info('Custom fields del contacto:');
        $cf = $contactoCompleto['custom_fields'] ?? [];
        if (empty($cf)) {
            $this->line('  (sin custom fields)');
        } else {
            foreach ($cf as $f) {
                $this->line(sprintf(
                    '  · api_name="%s" label="%s" placeholder="%s" value="%s"',
                    $f['api_name'] ?? '?',
                    $f['label'] ?? '?',
                    $f['placeholder'] ?? '',
                    $f['value'] ?? '',
                ));
            }
        }

        // 4. Campos típicos donde podría vivir el CIF
        $this->newLine();
        $this->info('Campos candidatos a CIF (built-in):');
        foreach (['vat_treatment', 'vat_reg_no', 'tax_id', 'tax_reg_no', 'gst_no', 'cf_cif', 'contact_name', 'company_name'] as $key) {
            if (array_key_exists($key, $contactoCompleto)) {
                $val = $contactoCompleto[$key];
                $this->line(sprintf('  · %s = "%s"', $key, is_array($val) ? json_encode($val) : (string) $val));
            }
        }

        // 5. Una factura — claves y valores clave
        $this->newLine();
        $this->info('Sondeo de una factura:');
        try {
            $factList = $books->listInvoices(1, 1);
        } catch (Throwable $e) {
            $this->warn('Error listando facturas: ' . $e->getMessage());
            return self::SUCCESS;
        }

        $facturas = $factList['invoices'] ?? [];
        if (empty($facturas)) {
            $this->line('  (sin facturas en la cuenta)');
        } else {
            $this->line('  Claves: ' . implode(', ', array_keys($facturas[0])));
            $f = $facturas[0];
            foreach (['customer_id', 'customer_name', 'email', 'status', 'balance', 'total', 'cf_cif'] as $key) {
                if (array_key_exists($key, $f)) {
                    $this->line(sprintf('  · %s = "%s"', $key, (string) $f[$key]));
                }
            }
        }

        $this->newLine();
        $this->info('Sondeo completado. Configura ZOHO_BOOKS_CIF_FIELD en .env con el api_name del campo CIF identificado.');

        return self::SUCCESS;
    }
}
