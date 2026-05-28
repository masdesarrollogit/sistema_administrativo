<?php

namespace App\Http\Controllers;

use App\Services\Zoho\ZohoBooksService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ZohoController extends Controller
{
    public function __construct(protected ZohoBooksService $books) {}

    /**
     * Inicia el flujo OAuth redirigiendo a la pantalla de consentimiento de Zoho.
     */
    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        session(['zoho_oauth_state' => $state]);

        return redirect()->away($this->books->authorizationUrl($state));
    }

    /**
     * Callback que Zoho invoca tras el consentimiento.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = session()->pull('zoho_oauth_state');

        if ($request->query('state') && $expectedState && $request->query('state') !== $expectedState) {
            return redirect()
                ->route('webcurso.zoho.books')
                ->with('error', 'El parámetro state no coincide. Reintenta la conexión.');
        }

        if ($request->query('error')) {
            return redirect()
                ->route('webcurso.zoho.books')
                ->with('error', 'Zoho rechazó la autorización: ' . $request->query('error'));
        }

        $code = $request->query('code');

        if (! $code) {
            return redirect()
                ->route('webcurso.zoho.books')
                ->with('error', 'Zoho no devolvió ningún código de autorización.');
        }

        try {
            $this->books->exchangeCodeForToken($code, optional($request->user())->id);
            $this->books->getOrganizationId(); // detecta y cachea organización
        } catch (Throwable $e) {
            Log::error('Zoho OAuth callback fallo', ['error' => $e->getMessage()]);
            return redirect()
                ->route('webcurso.zoho.books')
                ->with('error', 'No se pudo completar la conexión con Zoho: ' . $e->getMessage());
        }

        return redirect()
            ->route('webcurso.zoho.books')
            ->with('success', 'Conexión con Zoho Books completada.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->books->disconnect();

        return redirect()
            ->route('webcurso.zoho.books')
            ->with('success', 'Conexión con Zoho Books revocada localmente.');
    }
}
