<?php

namespace App\Livewire\Zoho;

use App\Models\Empresa;
use App\Models\NotaEmpresa;
use App\Models\ParticipanteBonificado;
use App\Models\ZohoBooksContact;
use App\Services\Zoho\BooksExportService;
use App\Services\Zoho\ZohoBooksService;
use Carbon\Carbon;
use Throwable as ThrowableAlias;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class BooksIndex extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'resumen';

    public int $pagina = 1;
    public int $porPagina = 25;

    public ?string $error = null;

    /** @var array<int,array<string,mixed>> */
    public array $facturas = [];

    /** @var array<int,array<string,mixed>> */
    public array $contactos = [];

    /** Lista plana enriquecida para la pestaña Impagados.
     *  Cada elemento: participante (nif, nombre, cif, estado, fechas) + empresa (razon_social) + factura_resumen (cif_facturas, total_impagado, num_facturas).
     * @var array<int,array<string,mixed>>
     */
    public array $impagados = [];

    /** Resumen de empresas con factura impagada pero sin participantes bonificados ligados. Para diagnóstico. */
    public array $impagadosSinParticipantes = [];

    public bool $hayMasFacturas = false;
    public bool $hayMasContactos = false;

    public ?array $estadoConexion = null;

    // ---- Notas de seguimiento por empresa (modal) ----
    public bool   $mostrarModalNotas = false;
    public ?int   $empresaIdNotas    = null;
    public string $cifNotas          = '';
    public string $razonSocialNotas  = '';

    /** @var array<int,array<string,mixed>> */
    public array $notasLista = [];

    /** @var array{id:?int,title:?string,body:string} */
    public array $notaForm = ['id' => null, 'title' => null, 'body' => ''];

    public function mount(): void
    {
        $this->cargarDatosPestana();
    }

    public function cambiarTab(string $tab): void
    {
        $this->tab = $tab;
        $this->pagina = 1;
        $this->cargarDatosPestana();
    }

    public function paginaSiguiente(): void
    {
        $this->pagina++;
        $this->cargarDatosPestana();
    }

    public function paginaAnterior(): void
    {
        if ($this->pagina > 1) {
            $this->pagina--;
            $this->cargarDatosPestana();
        }
    }

    public function recargar(): void
    {
        $this->cargarDatosPestana();
    }

    protected function cargarDatosPestana(): void
    {
        $this->error = null;

        $books = app(ZohoBooksService::class);
        $token = $books->token();

        if (! $token->refresh_token) {
            $this->estadoConexion = ['conectado' => false];
            return;
        }

        try {
            if ($this->tab === 'resumen') {
                $this->estadoConexion = array_merge(
                    ['conectado' => true],
                    $books->testConnection()
                );
            }

            if ($this->tab === 'facturas') {
                $resp = $books->listInvoices($this->pagina, $this->porPagina);
                $this->facturas = $this->normalizarFacturas($resp['invoices'] ?? [], $books);
                $this->hayMasFacturas = (bool) ($resp['page_context']['has_more_page'] ?? false);
                $this->estadoConexion = ['conectado' => true];
            }

            if ($this->tab === 'contactos') {
                $resp = $books->listContacts($this->pagina, $this->porPagina);
                $this->contactos = $resp['contacts'] ?? [];
                $this->hayMasContactos = (bool) ($resp['page_context']['has_more_page'] ?? false);
                $this->estadoConexion = ['conectado' => true];
            }

            if ($this->tab === 'impagados') {
                $this->cargarImpagados($books);
                $this->estadoConexion = ['conectado' => true];
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->estadoConexion = ['conectado' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extrae cif/email/teléfono directamente del payload del invoice (Zoho los
     * devuelve a nivel raíz: cf_cif, email, phone).
     */
    protected function normalizarFacturas(array $facturas, ZohoBooksService $books): array
    {
        return array_map(function (array $f) use ($books) {
            $f['cif']      = $books->extractCifFromContact($f);
            $f['telefono'] = $f[config('zoho.contact_phone_field', 'phone')] ?? ($f['phone'] ?? null);
            return $f;
        }, $facturas);
    }

    /**
     * Pestaña Impagados. Itera todas las páginas de facturas con status Unpaid,
     * agrupa por CIF y cruza con participantes_bonificados.
     */
    protected function cargarImpagados(ZohoBooksService $books): void
    {
        // Iterar todas las páginas de impagadas (Zoho devuelve max 200 por página)
        $facturas = [];
        $page = 1;
        do {
            $resp = $books->listUnpaidInvoices($page, 200);
            $lote = $resp['invoices'] ?? [];
            $facturas = array_merge($facturas, $lote);
            $hayMas = (bool) ($resp['page_context']['has_more_page'] ?? false);
            $page++;
        } while ($hayMas && $page <= 20); // tope de seguridad: 4000 facturas

        // Agrupar por CIF
        $porCif = [];
        $sinCif = [];

        foreach ($facturas as $f) {
            $cif = $books->extractCifFromContact($f);
            $balance = (float) ($f['balance'] ?? 0);

            if ($balance <= 0) {
                continue;
            }

            if (! $cif) {
                $sinCif[] = [
                    'invoice_number' => $f['invoice_number'] ?? '',
                    'customer_name'  => $f['customer_name'] ?? '',
                    'balance'        => $balance,
                    'date'           => $f['date'] ?? '',
                    'status'         => $f['status'] ?? '',
                ];
                continue;
            }

            $cif = strtoupper(trim($cif));

            if (! isset($porCif[$cif])) {
                $porCif[$cif] = [
                    'cif'             => $cif,
                    'customer_name'   => $f['customer_name'] ?? '',
                    'email'           => $f['email'] ?? '',
                    'telefono'        => $f['phone'] ?? '',
                    'customer_id'     => $f['customer_id'] ?? null,
                    'total_impagado'  => 0.0,
                    'num_facturas'    => 0,
                    'facturas'        => [],
                ];
            } else {
                if (empty($porCif[$cif]['telefono']) && ! empty($f['phone'])) {
                    $porCif[$cif]['telefono'] = $f['phone'];
                }
                if (empty($porCif[$cif]['customer_id']) && ! empty($f['customer_id'])) {
                    $porCif[$cif]['customer_id'] = $f['customer_id'];
                }
            }

            $porCif[$cif]['total_impagado'] += $balance;
            $porCif[$cif]['num_facturas']++;
            $porCif[$cif]['facturas'][] = [
                'invoice_number' => $f['invoice_number'] ?? '',
                'date'           => $f['date'] ?? '',
                'due_date'       => $f['due_date'] ?? '',
                'status'         => $f['status'] ?? '',
                'balance'        => $balance,
                'currency_code'  => $f['currency_code'] ?? '',
            ];
        }

        // Participantes bonificados con CIF en la lista.
        // Comparamos case-insensitive porque el CIF en Zoho/local podría diferir.
        $cifs = array_keys($porCif);
        $participantes = ParticipanteBonificado::query()
            ->whereRaw('UPPER(TRIM(cif)) IN (' . implode(',', array_fill(0, count($cifs) ?: 1, '?')) . ')', $cifs ?: [''])
            ->orderBy('cif')
            ->orderBy('nombre')
            ->get();

        // Empresas locales con esos CIFs — tienen teléfono/email más completos
        // que los que devuelve Zoho en /invoices (donde casi siempre van vacíos).
        $empresasPorCif = Empresa::query()
            ->whereIn('cif', $cifs)
            ->get()
            ->keyBy(fn ($e) => strtoupper(trim($e->cif)));

        // Conteo de notas por empresa, para mostrar el badge en la tabla sin queries N+1.
        $conteoNotas = NotaEmpresa::query()
            ->selectRaw('empresa_id, COUNT(*) as total')
            ->whereIn('empresa_id', $empresasPorCif->pluck('id')->all())
            ->groupBy('empresa_id')
            ->pluck('total', 'empresa_id');

        // Pre-cargar contactos Zoho cacheados (con sus contact_persons en raw).
        // Para los CIFs con participante bonificado (los que tendrán fila visible),
        // si no hay caché lo descargamos y guardamos en zoho_books_contacts.
        $cifsConParticipante = $participantes->pluck('cif')
            ->filter()
            ->map(fn ($c) => strtoupper(trim($c)))
            ->unique()
            ->values()
            ->all();

        $customerIdsNecesarios = collect($cifsConParticipante)
            ->map(fn ($cif) => $porCif[$cif]['customer_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $contactosCacheados = ZohoBooksContact::query()
            ->whereIn('contact_id', $customerIdsNecesarios)
            ->get()
            ->keyBy('contact_id');

        $faltantes = array_diff($customerIdsNecesarios, $contactosCacheados->keys()->all());
        $orgId = $books->getOrganizationId();
        foreach ($faltantes as $cid) {
            try {
                $payload  = $books->getContact($cid);
                $contacto = ZohoBooksContact::updateOrCreate(
                    ['contact_id' => $cid],
                    [
                        'organization_id'               => $orgId,
                        'contact_name'                  => $payload['contact_name']  ?? null,
                        'company_name'                  => $payload['company_name']  ?? null,
                        'cif'                           => $books->extractCifFromContact($payload),
                        'email'                         => $payload['email']         ?? null,
                        'phone'                         => $payload['phone']         ?? null,
                        'mobile'                        => $payload['mobile']        ?? null,
                        'contact_type'                  => $payload['contact_type']  ?? null,
                        'status'                        => $payload['status']        ?? null,
                        'outstanding_receivable_amount' => (float) ($payload['outstanding_receivable_amount'] ?? 0),
                        'currency_code'                 => $payload['currency_code'] ?? null,
                        'raw'                           => $payload,
                        'last_synced_at'                => Carbon::now(),
                    ]
                );
                $contactosCacheados->put($cid, $contacto);
            } catch (ThrowableAlias $e) {
                // No bloqueamos la página por un contacto que falle al descargarse
            }
        }

        $porCifConParticipantes = [];

        $impagados = [];
        foreach ($participantes as $p) {
            $cifP = strtoupper(trim($p->cif ?? ''));
            if (! isset($porCif[$cifP])) {
                continue;
            }
            $resumen = $porCif[$cifP];
            $porCifConParticipantes[$cifP] = true;

            $empresa  = $empresasPorCif->get($cifP);
            $cid      = $resumen['customer_id'] ?? null;
            $contacto = $cid ? $contactosCacheados->get($cid) : null;

            $telefonos = $this->recolectarTelefonos($empresa, $contacto, $resumen['telefono'] ?? null);
            $emails    = $this->recolectarEmails($empresa, $contacto, $resumen['email'] ?? null);

            $impagados[] = [
                'nif_participante' => $p->nif_participante,
                'nombre'           => $p->nombre,
                'estado'           => $p->estado,
                'id_codigo_grupo'  => $p->id_codigo_grupo,
                'fecha_inicio'     => optional($p->fecha_inicio)->format('d/m/Y'),
                'fecha_fin'        => optional($p->fecha_fin)->format('d/m/Y'),
                'cif'              => $cifP,
                'razon_social'     => $empresa?->razon_social ?: $resumen['customer_name'],
                'telefonos'        => $telefonos,
                'emails'           => $emails,
                'total_impagado'   => $resumen['total_impagado'],
                'num_facturas'     => $resumen['num_facturas'],
                'facturas'         => $resumen['facturas'],
                'empresa_id'       => $empresa?->id,
                'notas_count'      => (int) ($conteoNotas[$empresa?->id] ?? 0),
            ];
        }

        // Empresas con factura impagada pero sin participantes bonificados ligados
        $impagadosSinPart = [];
        foreach ($porCif as $cif => $resumen) {
            if (! isset($porCifConParticipantes[$cif])) {
                $impagadosSinPart[] = $resumen;
            }
        }

        $this->impagados = $impagados;
        $this->impagadosSinParticipantes = $impagadosSinPart;
    }

    /**
     * Solo dos fuentes: Empresa Panel (empresas.telefono) y Contacto Zoho (phone+mobile
     * a nivel raíz del contacto). Cuando viene de Contacto Zoho, anexa el nombre
     * de la persona (first_name + last_name del contacto). Sin contact_persons.
     *
     * @return array<int,array{raw:string,e164:string,fuente:string,nombre:?string}>
     */
    protected function recolectarTelefonos(?Empresa $empresa, ?ZohoBooksContact $contacto, ?string $telefonoFactura = null): array
    {
        $out = [];
        $add = function (?string $tel, string $fuente, ?string $nombre = null) use (&$out) {
            if (! $tel) return;
            $tel  = trim($tel);
            $e164 = $this->normalizarTelefonoEs($tel);
            if (! $e164 || isset($out[$e164])) return;
            $out[$e164] = ['raw' => $tel, 'e164' => $e164, 'fuente' => $fuente, 'nombre' => $nombre];
        };

        if ($empresa) {
            $add($empresa->telefono, 'Empresa Panel');
        }

        if ($contacto) {
            $nombreContacto = $this->nombreContactoZoho($contacto);
            $add($contacto->phone,  'Contacto Zoho', $nombreContacto);
            $add($contacto->mobile, 'Contacto Zoho', $nombreContacto);
        }

        return array_values($out);
    }

    /**
     * Solo Empresa Panel y Contacto Zoho top-level. Anexa el nombre del contacto
     * cuando la fuente es Contacto Zoho.
     *
     * @return array<int,array{email:string,fuente:string,nombre:?string}>
     */
    protected function recolectarEmails(?Empresa $empresa, ?ZohoBooksContact $contacto, ?string $emailFactura = null): array
    {
        $out = [];
        $add = function (?string $email, string $fuente, ?string $nombre = null) use (&$out) {
            if (! $email) return;
            $email = trim($email);
            $key   = strtolower($email);
            if ($key === '' || isset($out[$key])) return;
            $out[$key] = ['email' => $email, 'fuente' => $fuente, 'nombre' => $nombre];
        };

        if ($empresa)  $add($empresa->email, 'Empresa Panel');
        if ($contacto) $add($contacto->email, 'Contacto Zoho', $this->nombreContactoZoho($contacto));

        return array_values($out);
    }

    /**
     * Concatena first_name + last_name del contacto Zoho (la persona principal).
     */
    protected function nombreContactoZoho(ZohoBooksContact $contacto): ?string
    {
        $raw   = $contacto->raw ?? [];
        $nombre = trim(($raw['first_name'] ?? '') . ' ' . ($raw['last_name'] ?? ''));
        return $nombre !== '' ? $nombre : null;
    }

    /**
     * Normaliza un teléfono a formato E.164 español (+34XXXXXXXXX). Soporta
     * "971435090", "0034916590303", "+34915489870", "971 43 50 90", etc.
     * Misma lógica que App\Models\Candidato::getTelefonoE164Attribute.
     */
    protected function normalizarTelefonoEs(?string $telefono): ?string
    {
        if (! $telefono) {
            return null;
        }

        $numero = preg_replace('/[\s\-\.()]/', '', $telefono);
        $numero = preg_replace('/^(\+34|0034)/', '', $numero);

        if ($numero === '' || ! ctype_digit($numero)) {
            return null;
        }

        return '+34' . $numero;
    }

    // ─────────────────────────────────────────────────────────────
    // Notas de seguimiento por empresa
    // ─────────────────────────────────────────────────────────────

    public function abrirNotas(int $empresaId, string $cif, string $razonSocial): void
    {
        $this->empresaIdNotas   = $empresaId;
        $this->cifNotas         = $cif;
        $this->razonSocialNotas = $razonSocial;
        $this->resetNotaForm();
        $this->cargarListaNotas();
        $this->mostrarModalNotas = true;
    }

    public function cerrarNotas(): void
    {
        $this->mostrarModalNotas = false;
        $this->notasLista        = [];
        $this->resetNotaForm();
        $this->empresaIdNotas    = null;
        $this->cifNotas          = '';
        $this->razonSocialNotas  = '';
    }

    public function guardarNota(): void
    {
        $this->validate([
            'notaForm.title' => ['nullable', 'string', 'max:150'],
            'notaForm.body'  => ['required', 'string', 'min:1', 'max:5000'],
        ], [
            'notaForm.body.required' => 'La descripción de la nota es obligatoria.',
            'notaForm.body.max'      => 'Máximo 5000 caracteres.',
            'notaForm.title.max'     => 'El título no puede superar los 150 caracteres.',
        ]);

        if ($this->notaForm['id']) {
            $nota = NotaEmpresa::find($this->notaForm['id']);
            if (! $nota || $nota->user_id !== auth()->id()) {
                $this->cargarListaNotas();
                $this->resetNotaForm();
                return;
            }
            $nota->update([
                'title'     => $this->notaForm['title'] ?: null,
                'body'      => $this->notaForm['body'],
                'edited_at' => now(),
            ]);
        } else {
            NotaEmpresa::create([
                'empresa_id' => $this->empresaIdNotas,
                'user_id'    => auth()->id(),
                'title'      => $this->notaForm['title'] ?: null,
                'body'       => $this->notaForm['body'],
            ]);
        }

        $this->cargarListaNotas();
        $this->resetNotaForm();
        $this->recargarConteoNotas();
    }

    public function editarNota(int $id): void
    {
        $nota = NotaEmpresa::find($id);
        if (! $nota || $nota->user_id !== auth()->id()) {
            return;
        }
        $this->notaForm = [
            'id'    => $nota->id,
            'title' => $nota->title,
            'body'  => $nota->body,
        ];
    }

    public function cancelarEdicion(): void
    {
        $this->resetNotaForm();
    }

    public function eliminarNota(int $id): void
    {
        $nota = NotaEmpresa::find($id);
        if (! $nota || $nota->user_id !== auth()->id()) {
            return;
        }
        $nota->delete();

        if ($this->notaForm['id'] === $id) {
            $this->resetNotaForm();
        }

        $this->cargarListaNotas();
        $this->recargarConteoNotas();
    }

    protected function cargarListaNotas(): void
    {
        if (! $this->empresaIdNotas) {
            $this->notasLista = [];
            return;
        }

        $this->notasLista = NotaEmpresa::with('autor:id,name')
            ->where('empresa_id', $this->empresaIdNotas)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'title'      => $n->title,
                'body'       => $n->body,
                'autor'      => $n->autor?->name ?? 'Usuario eliminado',
                'fecha'      => $n->created_at?->locale('es')->diffForHumans(),
                'fecha_full' => $n->created_at?->format('d/m/Y H:i'),
                'editada'    => (bool) $n->edited_at,
                'es_propia'  => $n->user_id !== null && $n->user_id === auth()->id(),
            ])
            ->all();
    }

    protected function recargarConteoNotas(): void
    {
        if (! $this->empresaIdNotas) return;
        $nuevo = NotaEmpresa::where('empresa_id', $this->empresaIdNotas)->count();
        foreach ($this->impagados as $idx => $row) {
            if (($row['empresa_id'] ?? null) === $this->empresaIdNotas) {
                $this->impagados[$idx]['notas_count'] = $nuevo;
            }
        }
    }

    protected function resetNotaForm(): void
    {
        $this->notaForm = ['id' => null, 'title' => null, 'body' => ''];
        $this->resetErrorBag('notaForm.title');
        $this->resetErrorBag('notaForm.body');
    }

    /**
     * Descarga el listado de impagados en formato XLSX. Si la pestaña Impagados
     * no estaba activa (los datos no están en memoria), los regenera primero.
     */
    public function descargarImpagadosXlsx()
    {
        if (empty($this->impagados)) {
            $this->cargarImpagados(app(ZohoBooksService::class));
        }

        if (empty($this->impagados)) {
            $this->error = 'No hay impagados que exportar.';
            return null;
        }

        $impagados = $this->impagados;
        $filename  = 'impagados_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(
            fn () => app(BooksExportService::class)->streamImpagadosXlsx($impagados),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function render()
    {
        return view('livewire.zoho.books-index')
            ->layout('layouts.app', ['title' => 'Zoho Books']);
    }
}
