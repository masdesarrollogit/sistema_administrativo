<?php

namespace App\Livewire\Webcurso;

use App\Mail\SaldoParticipanteBonificadoMail;
use App\Models\Empresa;
use App\Models\ParticipanteBonificado;
use Livewire\Component;
use Livewire\WithPagination;
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

    // Paginación
    public int $perPage = 25;

    // Modal empresa
    public bool $mostrarModal   = false;
    public ?array $empresaModal = null;
    public bool $enviandoEmail  = false;
    public ?string $mensajeEmail = null;

    protected $queryString = [
        'filtroNombre' => ['except' => ''],
        'filtroNif'    => ['except' => ''],
        'filtroCif'    => ['except' => ''],
        'filtroEstado' => ['except' => ''],
        'filtroGrupo'  => ['except' => ''],
        'perPage'      => ['except' => 25],
    ];

    public function updatingFiltroNombre(): void { $this->resetPage(); }
    public function updatingFiltroNif(): void    { $this->resetPage(); }
    public function updatingFiltroCif(): void    { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }
    public function updatingFiltroGrupo(): void  { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['filtroNombre', 'filtroNif', 'filtroCif', 'filtroEstado', 'filtroGrupo']);
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

        return $query->orderBy('nombre')->paginate($this->perPage);
    }

    protected function getEstadisticas(): array
    {
        $total   = ParticipanteBonificado::count();
        $cifUnicos = ParticipanteBonificado::whereNotNull('cif')
            ->where('cif', '!=', '')
            ->distinct('cif')
            ->count('cif');

        return [
            'total'      => $total,
            'cif_unicos' => $cifUnicos,
        ];
    }

    public function render()
    {
        return view('livewire.webcurso.participantes-bonificados-index', [
            'participantes' => $this->getParticipantes(),
            'stats'         => $this->getEstadisticas(),
        ])->layout('layouts.app', ['title' => 'Participantes Bonificados - WebCurso']);
    }
}
