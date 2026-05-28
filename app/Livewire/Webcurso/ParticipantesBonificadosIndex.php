<?php

namespace App\Livewire\Webcurso;

use App\Mail\SaldoParticipanteBonificadoMail;
use App\Models\Alumno;
use App\Models\BonificadoEmailEnvio;
use App\Models\BonificadoEmailExclusion;
use App\Models\Empresa;
use App\Models\ParticipanteBonificado;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class ParticipantesBonificadosIndex extends Component
{
    use WithPagination;

    // Filtros
    public string $filtroNombre     = '';
    public string $filtroNif        = '';
    public string $filtroCif        = '';
    public string $filtroEstado     = '';
    public string $filtroGrupo      = '';
    public bool $filtroSinEmail     = false;

    // Estado del re-enriquecimiento
    public ?string $resumenReenriquecimiento = null;
    public bool $reenriqueciendo            = false;

    // Paginación
    public int $perPage = 25;

    // Modal empresa
    public bool $mostrarModal   = false;
    public ?array $empresaModal = null;
    public bool $enviandoEmail  = false;
    public ?string $mensajeEmail = null;

    // Envío masivo de saldo
    public bool $enviandoMasivo = false;
    public ?string $resultadoEnvioMasivo = null;

    protected $queryString = [
        'filtroNombre'   => ['except' => ''],
        'filtroNif'      => ['except' => ''],
        'filtroCif'      => ['except' => ''],
        'filtroEstado'   => ['except' => ''],
        'filtroGrupo'    => ['except' => ''],
        'filtroSinEmail' => ['except' => false],
        'perPage'        => ['except' => 25],
    ];

    public function updatingFiltroNombre(): void   { $this->resetPage(); }
    public function updatingFiltroNif(): void      { $this->resetPage(); }
    public function updatingFiltroCif(): void      { $this->resetPage(); }
    public function updatingFiltroEstado(): void   { $this->resetPage(); }
    public function updatingFiltroGrupo(): void    { $this->resetPage(); }
    public function updatingFiltroSinEmail(): void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['filtroNombre', 'filtroNif', 'filtroCif', 'filtroEstado', 'filtroGrupo', 'filtroSinEmail']);
        $this->resetPage();
    }

    // ─── Modal empresa por CIF ────────────────────────────────────────────────

    public function abrirModal(string $cif): void
    {
        if (empty(trim($cif))) {
            return;
        }

        $empresa = Empresa::where('cif', $cif)->first();

        if ($empresa) {
            $this->empresaModal = [
                'cif'             => $empresa->cif,
                'razon_social'    => $empresa->razon_social,
                'saldo_formateado'=> $empresa->saldo_formateado,
                'credito_disponible' => $empresa->credito_disponible,
            ];
        } else {
            $this->empresaModal = [
                'cif'             => $cif,
                'razon_social'    => 'Empresa no encontrada en el sistema',
                'saldo_formateado'=> 'N/D',
                'credito_disponible' => null,
            ];
        }

        $this->mostrarModal  = true;
        $this->mensajeEmail  = null;
        $this->enviandoEmail = false;
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal  = false;
        $this->empresaModal  = null;
        $this->mensajeEmail  = null;
        $this->enviandoEmail = false;
    }

    public function enviarSaldo(): void
    {
        if (!$this->empresaModal || $this->empresaModal['credito_disponible'] === null) {
            $this->mensajeEmail = '❌ No se puede enviar: empresa no encontrada en el sistema';
            return;
        }

        $this->enviandoEmail = true;

        try {
            Mail::to('webcurso@webcurso.es')
                ->send(new SaldoParticipanteBonificadoMail(
                    $this->empresaModal['cif'],
                    $this->empresaModal['razon_social'],
                    $this->empresaModal['saldo_formateado']
                ));

            $this->mensajeEmail = '✅ Correo enviado correctamente a webcurso@webcurso.es (con copia a administración y prospectos)';
        } catch (\Exception $e) {
            $this->mensajeEmail = '❌ Error al enviar: ' . $e->getMessage();
        }

        $this->enviandoEmail = false;
    }

    // ─── Envío masivo de email de saldo ──────────────────────────────────────

    public function enviarEmailSaldoPrueba(): void
    {
        $this->enviandoMasivo = true;
        $this->resultadoEnvioMasivo = null;

        try {
            Artisan::call('bonificados:enviar-email-saldo', [
                '--dry-run' => true,
                '--manual' => true,
            ]);
            $this->resultadoEnvioMasivo = '🔍 ' . Artisan::output();
        } catch (\Exception $e) {
            $this->resultadoEnvioMasivo = '❌ Error: ' . $e->getMessage();
        }

        $this->enviandoMasivo = false;
    }

    public function enviarEmailSaldoMasivo(): void
    {
        $this->enviandoMasivo = true;
        $this->resultadoEnvioMasivo = null;

        try {
            Artisan::call('bonificados:enviar-email-saldo', ['--manual' => true]);
            $this->resultadoEnvioMasivo = '✅ ' . Artisan::output();
        } catch (\Exception $e) {
            $this->resultadoEnvioMasivo = '❌ Error: ' . $e->getMessage();
        }

        $this->enviandoMasivo = false;
    }

    // ─── Exclusión individual de participantes ────────────────────────────────

    public function excluirParticipante(string $nif, string $nombre): void
    {
        BonificadoEmailExclusion::updateOrCreate(
            ['nif' => $nif],
            [
                'nombre' => $nombre,
                'excluido_por' => auth()->id(),
            ]
        );
    }

    public function reactivarParticipante(string $nif): void
    {
        BonificadoEmailExclusion::where('nif', $nif)->delete();
    }

    // ─── Re-enriquecimiento ──────────────────────────────────────────────────

    public function reejecutarEnriquecimiento(): void
    {
        $this->reenriqueciendo = true;
        $this->resumenReenriquecimiento = null;

        try {
            Artisan::call('alumnos:importar-bonificados', ['--force' => true]);
            $this->resumenReenriquecimiento = trim(Artisan::output());
        } catch (\Exception $e) {
            $this->resumenReenriquecimiento = 'Error: ' . $e->getMessage();
        }

        $this->reenriqueciendo = false;
    }

    // ─── Query ────────────────────────────────────────────────────────────────

    protected function getParticipantes()
    {
        $query = ParticipanteBonificado::query();

        if ($this->filtroNombre) {
            $query->where('nombre', 'like', "%{$this->filtroNombre}%");
        }
        if ($this->filtroNif) {
            $query->where('nif_participante', 'like', "%{$this->filtroNif}%");
        }
        if ($this->filtroCif) {
            $query->where('cif', 'like', "%{$this->filtroCif}%");
        }
        if ($this->filtroEstado) {
            $query->where('estado', $this->filtroEstado);
        }
        if ($this->filtroGrupo) {
            $query->where('id_codigo_grupo', 'like', "%{$this->filtroGrupo}%");
        }

        if ($this->filtroSinEmail) {
            // Participantes cuyo NIF no aparece en alumnos con email no nulo/vacío
            $query->whereNotIn('nif_participante', function ($sub) {
                $sub->select('nif')
                    ->from('alumnos')
                    ->whereNotNull('email')
                    ->where('email', '!=', '');
            });
        }

        return $query->orderBy('nombre')->paginate($this->perPage);
    }

    protected function getEstadisticas(): array
    {
        $total   = ParticipanteBonificado::count();
        $cifUnicos = ParticipanteBonificado::whereNotNull('cif')
            ->where('cif', '!=', '')
            ->distinct('cif')
            ->count('cif');

        $finalizados = ParticipanteBonificado::where('estado', 'Finalizado')
            ->whereNotNull('nif_participante')
            ->where('nif_participante', '!=', '')
            ->distinct('nif_participante')
            ->count('nif_participante');

        $excluidos = BonificadoEmailExclusion::count();

        // NIFs sin email registrado: participantes cuyo NIF no está en alumnos con email
        $sinEmail = ParticipanteBonificado::query()
            ->whereNotNull('nif_participante')
            ->where('nif_participante', '!=', '')
            ->whereNotIn('nif_participante', function ($sub) {
                $sub->select('nif')
                    ->from('alumnos')
                    ->whereNotNull('email')
                    ->where('email', '!=', '');
            })
            ->distinct('nif_participante')
            ->count('nif_participante');

        $datosPendientes = Alumno::where('datos_pendientes', true)->count();

        return [
            'total'             => $total,
            'cif_unicos'        => $cifUnicos,
            'finalizados'       => $finalizados,
            'excluidos'         => $excluidos,
            'sin_email'         => $sinEmail,
            'datos_pendientes'  => $datosPendientes,
        ];
    }

    public function render()
    {
        $nifsExcluidos = BonificadoEmailExclusion::pluck('nif')->toArray();

        // Resolver emails desde alumnos para los participantes de la página actual
        $participantes = $this->getParticipantes();
        $nifs = collect($participantes->items())->pluck('nif_participante')->filter()->unique()->toArray();

        $alumnosPorNif = Alumno::whereIn('nif', $nifs)
            ->get(['nif', 'email', 'telefono', 'datos_pendientes'])
            ->keyBy('nif');

        $emailsPorNif = $alumnosPorNif
            ->filter(fn ($a) => !empty($a->email))
            ->mapWithKeys(fn ($a) => [$a->nif => $a->email])
            ->toArray();

        $telefonosPorNif = $alumnosPorNif
            ->filter(fn ($a) => !empty($a->telefono))
            ->mapWithKeys(fn ($a) => [$a->nif => $a->telefono_e164])
            ->toArray();

        $datosPendientesPorNif = $alumnosPorNif
            ->filter(fn ($a) => (bool) $a->datos_pendientes)
            ->keys()
            ->toArray();

        // Últimos envíos por NIF para los participantes de la página
        $ultimosEnviosPorNif = BonificadoEmailEnvio::query()
            ->whereIn('nif', $nifs)
            ->select('nif')
            ->selectRaw('MAX(enviado_at) as ultimo_envio')
            ->groupBy('nif')
            ->pluck('ultimo_envio', 'nif')
            ->map(fn ($v) => $v ? Carbon::parse($v) : null)
            ->toArray();

        return view('livewire.webcurso.participantes-bonificados-index', [
            'participantes'         => $participantes,
            'stats'                 => $this->getEstadisticas(),
            'nifsExcluidos'         => $nifsExcluidos,
            'emailsPorNif'          => $emailsPorNif,
            'telefonosPorNif'       => $telefonosPorNif,
            'datosPendientesPorNif' => $datosPendientesPorNif,
            'ultimosEnviosPorNif'   => $ultimosEnviosPorNif,
            'cronActivo'            => config('candidatos.email_saldo_bonificados.activo', true),
            'cronFrecuencia'        => config('candidatos.email_saldo_bonificados.frecuencia', 'monthly'),
            'proximoEnvio'          => $this->calcularProximoEnvio(),
            'ccAdmin'               => config('candidatos.email_saldo_bonificados.cc_admin'),
            'modoPrueba'            => !app()->environment('production'),
        ])->layout('layouts.app', ['title' => 'Participantes Bonificados - WebCurso']);
    }

    /**
     * Calcula la próxima ocurrencia del cron de envío de saldo según la
     * frecuencia configurada. Devuelve un Carbon en Europe/Madrid o null si está desactivado.
     */
    protected function calcularProximoEnvio(): ?Carbon
    {
        $config = config('candidatos.email_saldo_bonificados');

        if (empty($config['activo'])) {
            return null;
        }

        $frecuencia = $config['frecuencia'] ?? 'monthly';
        $hora = $config['hora'] ?? '10:00';
        $diaMes = (int) ($config['dia_del_mes'] ?? 1);

        [$h, $m] = array_pad(explode(':', $hora), 2, '0');
        $h = (int) $h;
        $m = (int) $m;

        $ahora = Carbon::now('Europe/Madrid');

        return match ($frecuencia) {
            'weekly' => $this->proximoLunes($ahora, $h, $m),
            'biweekly' => $this->proximoDia1o15($ahora, $h, $m),
            default => $this->proximoDiaDelMes($ahora, $diaMes, $h, $m),
        };
    }

    private function proximoDiaDelMes(Carbon $ahora, int $dia, int $h, int $m): Carbon
    {
        $diaSeguro = max(1, min(28, $dia));
        $candidato = $ahora->copy()->day($diaSeguro)->setTime($h, $m, 0);
        if ($candidato->lessThanOrEqualTo($ahora)) {
            $candidato->addMonthNoOverflow();
            $candidato->day($diaSeguro)->setTime($h, $m, 0);
        }
        return $candidato;
    }

    private function proximoLunes(Carbon $ahora, int $h, int $m): Carbon
    {
        $candidato = $ahora->copy()->next(Carbon::MONDAY)->setTime($h, $m, 0);
        $hoyLunes = $ahora->copy()->startOfWeek()->setTime($h, $m, 0);
        if ($ahora->isMonday() && $ahora->lessThan($hoyLunes)) {
            return $hoyLunes;
        }
        return $candidato;
    }

    private function proximoDia1o15(Carbon $ahora, int $h, int $m): Carbon
    {
        $candidato1 = $ahora->copy()->day(1)->setTime($h, $m, 0);
        $candidato15 = $ahora->copy()->day(15)->setTime($h, $m, 0);

        $candidatos = collect([$candidato1, $candidato15])
            ->filter(fn ($c) => $c->greaterThan($ahora))
            ->sortBy(fn ($c) => $c->timestamp)
            ->values();

        if ($candidatos->isEmpty()) {
            return $ahora->copy()->addMonthNoOverflow()->day(1)->setTime($h, $m, 0);
        }

        return $candidatos->first();
    }
}
