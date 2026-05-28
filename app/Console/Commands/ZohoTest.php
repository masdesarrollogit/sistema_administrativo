<?php

namespace App\Console\Commands;

use App\Services\Zoho\ZohoBooksService;
use Illuminate\Console\Command;
use Throwable;

class ZohoTest extends Command
{
    protected $signature = 'zoho:test';

    protected $description = 'Comprueba la conexión con Zoho Books (refresca token y lista organizaciones)';

    public function handle(ZohoBooksService $books): int
    {
        $this->info('Verificando conexión con Zoho Books…');

        $token = $books->token();

        if (! $token->refresh_token) {
            $this->error('No hay refresh_token guardado. Visita /webcurso/zoho/connect en el navegador para autorizar.');
            return self::FAILURE;
        }

        try {
            $result = $books->testConnection();
        } catch (Throwable $e) {
            $this->error('Conexión fallida: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('✓ Conexión OK');
        $this->line('  api_domain:       ' . ($result['api_domain'] ?? '(no devuelto)'));
        $this->line('  organization_id:  ' . ($result['organization_id'] ?? '(ninguna)'));

        $this->newLine();
        $this->info('Organizaciones accesibles:');
        foreach ($result['organizations'] as $org) {
            $this->line(sprintf(
                '  · %s (%s) — %s %s%s',
                $org['name'] ?? '?',
                $org['organization_id'] ?? '?',
                $org['country'] ?? '',
                $org['currency'] ?? '',
                $org['is_default_org'] ? ' [default]' : ''
            ));
        }

        // Prueba un par de lecturas
        $this->newLine();
        $this->info('Prueba de lectura:');

        try {
            $contacts = $books->listContacts(1, 5);
            $this->line('  Contactos (página 1): ' . count($contacts['contacts'] ?? []) . ' registros');
        } catch (Throwable $e) {
            $this->warn('  Contactos falló: ' . $e->getMessage());
        }

        try {
            $invoices = $books->listInvoices(1, 5);
            $this->line('  Facturas (página 1):  ' . count($invoices['invoices'] ?? []) . ' registros');
        } catch (Throwable $e) {
            $this->warn('  Facturas falló: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
