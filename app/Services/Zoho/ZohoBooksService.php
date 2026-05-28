<?php

namespace App\Services\Zoho;

use App\Models\ZohoToken;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoBooksService
{
    protected string $service = 'books';

    public function __construct()
    {
        if (! config('zoho.client_id') || ! config('zoho.client_secret')) {
            throw new RuntimeException('Faltan credenciales Zoho en config/zoho.php (ZOHO_CLIENT_ID / ZOHO_CLIENT_SECRET).');
        }
    }

    // ---------------------------------------------------------------------
    // Configuración por región
    // ---------------------------------------------------------------------

    public function accountsBase(): string
    {
        $region = config('zoho.region', 'eu');
        return "https://accounts.zoho.{$region}";
    }

    public function apiBase(): string
    {
        $token = $this->token();

        if ($token->api_domain) {
            return rtrim($token->api_domain, '/');
        }

        $region = config('zoho.region', 'eu');
        return "https://www.zohoapis.{$region}";
    }

    public function token(): ZohoToken
    {
        return ZohoToken::forService($this->service);
    }

    // ---------------------------------------------------------------------
    // Flujo OAuth
    // ---------------------------------------------------------------------

    public function authorizationUrl(?string $state = null): string
    {
        $params = http_build_query([
            'scope'         => implode(',', config('zoho.scopes', [])),
            'client_id'     => config('zoho.client_id'),
            'response_type' => 'code',
            'redirect_uri'  => config('zoho.redirect_uri'),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state ?? '',
        ]);

        return $this->accountsBase() . '/oauth/v2/auth?' . $params;
    }

    public function exchangeCodeForToken(string $code, ?int $userId = null): ZohoToken
    {
        $response = Http::asForm()->post($this->accountsBase() . '/oauth/v2/token', [
            'grant_type'    => 'authorization_code',
            'client_id'     => config('zoho.client_id'),
            'client_secret' => config('zoho.client_secret'),
            'redirect_uri'  => config('zoho.redirect_uri'),
            'code'          => $code,
        ]);

        $data = $this->decodeAuthResponse($response);

        $token = $this->token();
        $token->access_token   = $data['access_token'];
        $token->refresh_token  = $data['refresh_token'] ?? $token->refresh_token;
        $token->expires_at     = Carbon::now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        $token->scope          = $data['scope'] ?? implode(',', config('zoho.scopes', []));
        $token->api_domain     = $data['api_domain'] ?? null;
        $token->updated_by     = $userId;
        $token->save();

        return $token;
    }

    public function refreshAccessToken(): ZohoToken
    {
        $token = $this->token();

        $refreshToken = $token->refresh_token ?: config('zoho.refresh_token');

        if (! $refreshToken) {
            throw new RuntimeException('No hay refresh token de Zoho. Conecta primero desde /zoho/connect.');
        }

        $response = Http::asForm()->post($this->accountsBase() . '/oauth/v2/token', [
            'grant_type'    => 'refresh_token',
            'client_id'     => config('zoho.client_id'),
            'client_secret' => config('zoho.client_secret'),
            'refresh_token' => $refreshToken,
        ]);

        $data = $this->decodeAuthResponse($response);

        $token->access_token = $data['access_token'];
        $token->expires_at   = Carbon::now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        if (! empty($data['api_domain'])) {
            $token->api_domain = $data['api_domain'];
        }
        if (! $token->refresh_token && $refreshToken) {
            $token->refresh_token = $refreshToken;
        }
        $token->save();

        return $token;
    }

    public function disconnect(): void
    {
        $token = $this->token();
        $token->access_token  = null;
        $token->refresh_token = null;
        $token->expires_at    = null;
        $token->scope         = null;
        $token->save();
    }

    protected function decodeAuthResponse(Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException("Error HTTP al hablar con Zoho OAuth: {$response->status()} - {$response->body()}");
        }

        $data = $response->json();

        if (! is_array($data) || isset($data['error'])) {
            $err = is_array($data) ? ($data['error'] ?? json_encode($data)) : (string) $data;
            throw new RuntimeException("Zoho OAuth devolvió error: {$err}");
        }

        if (empty($data['access_token'])) {
            throw new RuntimeException('Respuesta de Zoho OAuth sin access_token: ' . json_encode($data));
        }

        return $data;
    }

    // ---------------------------------------------------------------------
    // Cliente HTTP autenticado
    // ---------------------------------------------------------------------

    public function getAccessToken(): string
    {
        $token = $this->token();

        if (! $token->accessTokenIsValid()) {
            $token = $this->refreshAccessToken();
        }

        return $token->access_token;
    }

    public function request(string $method, string $endpoint, array $query = [], array $body = []): array
    {
        $accessToken = $this->getAccessToken();

        $url = $this->apiBase() . '/books/v3/' . ltrim($endpoint, '/');

        if (! isset($query['organization_id']) && $orgId = $this->getOrganizationId()) {
            $query['organization_id'] = $orgId;
        }

        $request = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
        ])->acceptJson();

        $method = strtoupper($method);

        $response = match ($method) {
            'GET'    => $request->get($url, $query),
            'POST'   => $request->asJson()->post($url . '?' . http_build_query($query), $body),
            'PUT'    => $request->asJson()->put($url . '?' . http_build_query($query), $body),
            'DELETE' => $request->delete($url, $query),
            default  => throw new RuntimeException("Método HTTP no soportado: {$method}"),
        };

        // Reintento ante 401: refrescar y volver a llamar una vez
        if ($response->status() === 401) {
            Log::info('Zoho devolvió 401, refrescando access_token y reintentando');
            $this->refreshAccessToken();
            return $this->request($method, $endpoint, $query, $body);
        }

        if ($response->failed()) {
            $body = $response->body();
            throw new RuntimeException("Zoho Books API error: {$response->status()} - {$body}");
        }

        $data = $response->json() ?? [];

        if (is_array($data) && isset($data['code']) && $data['code'] !== 0) {
            throw new RuntimeException("Zoho Books API error (code {$data['code']}): " . ($data['message'] ?? 'sin mensaje'));
        }

        return $data;
    }

    // ---------------------------------------------------------------------
    // Organización
    // ---------------------------------------------------------------------

    public function getOrganizations(): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
        ])->acceptJson()->get($this->apiBase() . '/books/v3/organizations');

        if ($response->failed()) {
            throw new RuntimeException("Zoho Books API error al listar organizaciones: {$response->status()} - {$response->body()}");
        }

        $data = $response->json() ?? [];

        return $data['organizations'] ?? [];
    }

    public function getOrganizationId(): ?string
    {
        $token = $this->token();

        if ($token->organization_id) {
            return $token->organization_id;
        }

        if ($fromConfig = config('zoho.organization_id')) {
            $token->organization_id = $fromConfig;
            $token->save();
            return $fromConfig;
        }

        if (! $token->access_token) {
            return null;
        }

        $orgs = $this->getOrganizations();

        if (empty($orgs)) {
            return null;
        }

        $default = collect($orgs)->firstWhere('is_default_org', true) ?? $orgs[0];
        $token->organization_id = (string) ($default['organization_id'] ?? '');
        $token->save();

        return $token->organization_id ?: null;
    }

    // ---------------------------------------------------------------------
    // Listados (solo lectura)
    // ---------------------------------------------------------------------

    public function listContacts(int $page = 1, int $perPage = 25, array $extra = []): array
    {
        return $this->request('GET', 'contacts', array_merge([
            'page'     => $page,
            'per_page' => min($perPage, 200),
        ], $extra));
    }

    public function listInvoices(int $page = 1, int $perPage = 25, array $extra = []): array
    {
        return $this->request('GET', 'invoices', array_merge([
            'page'     => $page,
            'per_page' => min($perPage, 200),
            'sort_column' => 'date',
            'sort_order'  => 'D',
        ], $extra));
    }

    /**
     * Facturas impagadas (status = sent, partially_paid, overdue, viewed con balance > 0).
     * Usa el filtro estándar de Zoho `filter_by=Status.Unpaid`.
     */
    public function listUnpaidInvoices(int $page = 1, int $perPage = 200, array $extra = []): array
    {
        return $this->listInvoices($page, $perPage, array_merge([
            'filter_by' => 'Status.Unpaid',
        ], $extra));
    }

    public function getContact(string $contactId): array
    {
        $resp = $this->request('GET', "contacts/{$contactId}");
        return $resp['contact'] ?? [];
    }

    /**
     * Itera todas las páginas de /contacts y entrega cada contacto al callback.
     * El callback recibe el array crudo del contacto y devuelve void.
     * Devuelve el total de contactos procesados.
     */
    public function listAllContacts(callable $onContact, int $perPage = 200): int
    {
        $page  = 1;
        $total = 0;

        do {
            $resp     = $this->listContacts($page, $perPage);
            $contacts = $resp['contacts'] ?? [];

            foreach ($contacts as $c) {
                $onContact($c);
                $total++;
            }

            $hasMore = (bool) ($resp['page_context']['has_more_page'] ?? false);
            $page++;
        } while ($hasMore);

        return $total;
    }

    /**
     * Extrae el CIF de un payload de Zoho (contacto o factura). Lee del campo
     * configurado en config/zoho.contact_cif_field (top-level) y cae back a
     * `custom_fields[*]` por api_name o label.
     */
    public function extractCifFromContact(array $payload): ?string
    {
        $field = config('zoho.contact_cif_field', 'cf_cif');

        if (! empty($payload[$field])) {
            return (string) $payload[$field];
        }

        foreach (($payload['custom_fields'] ?? []) as $cf) {
            $api   = $cf['api_name']    ?? null;
            $label = strtolower($cf['label'] ?? '');
            if ($api === $field || $label === 'cif' || $label === 'nif/cif') {
                return ! empty($cf['value']) ? (string) $cf['value'] : null;
            }
        }

        // Fallback a vat_no / tax_reg_no
        foreach (['vat_no', 'tax_reg_no', 'tax_id'] as $key) {
            if (! empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Diagnóstico
    // ---------------------------------------------------------------------

    public function testConnection(): array
    {
        $orgs = $this->getOrganizations();

        return [
            'ok'             => true,
            'organizations'  => array_map(fn ($o) => [
                'organization_id' => $o['organization_id'] ?? null,
                'name'            => $o['name'] ?? null,
                'is_default_org'  => $o['is_default_org'] ?? false,
                'country'         => $o['country'] ?? null,
                'currency'        => $o['currency_code'] ?? null,
            ], $orgs),
            'api_domain'     => $this->token()->api_domain,
            'organization_id'=> $this->getOrganizationId(),
        ];
    }
}
