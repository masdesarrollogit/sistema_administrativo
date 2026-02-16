<?php

namespace App\Livewire\Webcurso;

use App\Models\Grupo;
use App\Models\GrupoAnterior;
use Livewire\Component;
use Livewire\WithPagination;

class GruposIndex extends Component
{
    use WithPagination;

    public int $anioActual;
    public int $anioAnterior;
    public int $anioSeleccionado;
    
    // Filtros
    public string $filtroCif = '';
    public string $filtroDenominacion = '';
    public string $filtroEstado = '';
    public string $filtroModalidad = '';
    public string $filtroCodigo = '';
    public string $filtroAccionFormativa = '';
    
    // Paginación
    public int $perPage = 25;

    protected $queryString = [
        'filtroCif' => ['except' => ''],
        'filtroDenominacion' => ['except' => ''],
        'filtroEstado' => ['except' => ''],
        'filtroModalidad' => ['except' => ''],
        'filtroCodigo' => ['except' => ''],
        'filtroAccionFormativa' => ['except' => ''],
        'perPage' => ['except' => 25],
        'anioSeleccionado' => ['as' => 'anio'],
    ];

    public function mount(): void
    {
        $this->anioActual = (int) date('Y');
        $this->anioAnterior = $this->anioActual - 1;
        $this->anioSeleccionado = request('anio', $this->anioActual);
    }

    public function updatingFiltroCif(): void { $this->resetPage(); }
    public function updatingFiltroDenominacion(): void { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }
    public function updatingFiltroModalidad(): void { $this->resetPage(); }
    public function updatingFiltroCodigo(): void { $this->resetPage(); }
    public function updatingFiltroAccionFormativa(): void { $this->resetPage(); }

    public function cambiarAnio(int $anio): void
    {
        $this->anioSeleccionado = $anio;
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'filtroCif', 
            'filtroDenominacion', 
            'filtroEstado', 
            'filtroModalidad',
            'filtroCodigo',
            'filtroAccionFormativa'
        ]);
        $this->resetPage();
    }

    protected function getGrupos()
    {
        $modelo = $this->anioSeleccionado === $this->anioAnterior 
            ? GrupoAnterior::class 
            : Grupo::class;

        $query = $modelo::query();

        // Aplicar filtros
        if ($this->filtroCif) {
            $query->where('cif', 'like', "%{$this->filtroCif}%");
        }
        if ($this->filtroDenominacion) {
            $query->where('denominacion', 'like', "%{$this->filtroDenominacion}%");
        }
        if ($this->filtroEstado) {
            $query->where('estado', $this->filtroEstado);
        }
        if ($this->filtroModalidad) {
            $query->where('modalidad', $this->filtroModalidad);
        }
        if ($this->filtroCodigo) {
            $query->where('codigo_grupo', 'like', "%{$this->filtroCodigo}%");
        }
        if ($this->filtroAccionFormativa) {
            $query->where('codigo_grupo_accion_formativa', 'like', "%{$this->filtroAccionFormativa}%");
        }

        return $query->orderBy('id', 'asc')->paginate($this->perPage);
    }

    protected function getEstadisticas()
    {
        $modelo = $this->anioSeleccionado === $this->anioAnterior 
            ? GrupoAnterior::class 
            : Grupo::class;

        $total = $modelo::count();
        $conCif = $modelo::conCif()->count();

        return [
            'total' => $total,
            'con_cif' => $conCif,
            'sin_cif' => $total - $conCif,
        ];
    }

    public function render()
    {
        return view('livewire.webcurso.grupos-index', [
            'grupos' => $this->getGrupos(),
            'stats' => $this->getEstadisticas(),
        ])->layout('layouts.app', ['title' => 'Grupos - WebCurso']);
    }
}
