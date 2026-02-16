<?php

namespace App\Livewire\Webcurso;

use App\Mail\SaldoEmpresaMail;
use App\Models\Empresa;
use App\Models\EmpresaAnterior;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

class EmpresasIndex extends Component
{
    use WithPagination, \App\Traits\HasEmpresaModal;

    public int $anioActual;
    public int $anioAnterior;
    public int $anioSeleccionado;
    
    // Filtros
    public string $filtroCif = '';
    public string $filtroRazonSocial = '';
    public string $filtroPyme = '';
    public string $filtroNuevaCreacion = '';
    public string $filtroBloqueada = '';
    
    // Ordenación
    public string $sortBy = 'fecha_creacion';
    public string $sortDirection = 'desc';
    
    // Paginación
    public int $perPage = 12;

    protected $queryString = [
        'filtroCif' => ['except' => ''],
        'filtroRazonSocial' => ['except' => ''],
        'filtroPyme' => ['except' => ''],
        'filtroNuevaCreacion' => ['except' => ''],
        'filtroBloqueada' => ['except' => ''],
        'sortBy' => ['except' => 'fecha_creacion'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 12],
        'anioSeleccionado' => ['as' => 'anio'],
    ];

    public function mount(): void
    {
        $this->anioActual = (int) date('Y');
        $this->anioAnterior = $this->anioActual - 1;
        $this->anioSeleccionado = request('anio', $this->anioActual);
    }

    public function updatingFiltroCif(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroRazonSocial(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroPyme(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroNuevaCreacion(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroBloqueada(): void
    {
        $this->resetPage();
    }

    public function cambiarAnio(int $anio): void
    {
        $this->anioSeleccionado = $anio;
        $this->resetPage();
    }

    public function sortear(string $columna): void
    {
        if ($this->sortBy === $columna) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $columna;
            $this->sortDirection = 'asc';
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'filtroCif', 
            'filtroRazonSocial', 
            'filtroPyme', 
            'filtroNuevaCreacion', 
            'filtroBloqueada'
        ]);
        $this->resetPage();
    }

    public function abrirModalSaldo(int $id): void
    {
        $this->abrirModalEmpresa($id);
    }

    public function cerrarModalSaldo(): void
    {
        $this->cerrarModalEmpresa();
    }

    protected function getEmpresas()
    {
        $modelo = $this->anioSeleccionado === $this->anioAnterior 
            ? EmpresaAnterior::class 
            : Empresa::class;

        $query = $modelo::query()
            ->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->whereNotNull('razon_social')
            ->where('razon_social', '!=', '');

        // Aplicar filtros
        if ($this->filtroCif) {
            $query->where('cif', 'like', "%{$this->filtroCif}%");
        }
        if ($this->filtroRazonSocial) {
            $query->where('razon_social', 'like', "%{$this->filtroRazonSocial}%");
        }
        if ($this->filtroPyme) {
            $query->where('pyme', $this->filtroPyme);
        }
        if ($this->filtroNuevaCreacion) {
            $query->where('nueva_creacion', $this->filtroNuevaCreacion);
        }
        if ($this->filtroBloqueada) {
            $query->where('bloqueada', $this->filtroBloqueada);
        }

        // Ordenación
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    protected function getEstadisticas()
    {
        $modelo = $this->anioSeleccionado === $this->anioAnterior 
            ? EmpresaAnterior::class 
            : Empresa::class;

        return $modelo::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN pyme = "SI" THEN 1 ELSE 0 END) as pymes,
                SUM(credito_asignado) as asignado,
                SUM(credito_dispuesto) as dispuesto,
                SUM(credito_disponible) as disponible
            ')
            ->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->first();
    }

    public function render()
    {
        return view('livewire.webcurso.empresas-index', [
            'empresas' => $this->getEmpresas(),
            'stats' => $this->getEstadisticas(),
        ])->layout('layouts.app', ['title' => 'Empresas - WebCurso']);
    }
}
