<?php

namespace App\Livewire\Webcurso;

use App\Models\Empresa;
use App\Models\EmpresaAnterior;
use Livewire\Component;
use Livewire\WithPagination;

class EmpresasSinGrupos extends Component
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
    public string $filtroPoblacion = '';
    
    // Paginación
    public int $perPage = 25;

    protected $queryString = [
        'filtroCif' => ['except' => ''],
        'filtroRazonSocial' => ['except' => ''],
        'filtroPyme' => ['except' => ''],
        'filtroNuevaCreacion' => ['except' => ''],
        'filtroBloqueada' => ['except' => ''],
        'filtroPoblacion' => ['except' => ''],
        'perPage' => ['except' => 25],
        'anioSeleccionado' => ['as' => 'anio'],
    ];

    public function mount(): void
    {
        $this->anioActual = (int) date('Y');
        $this->anioAnterior = $this->anioActual - 1;
        $this->anioSeleccionado = request('anio', $this->anioActual);
    }

    public function cambiarAnio(int $anio): void
    {
        $this->anioSeleccionado = $anio;
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'filtroCif', 
            'filtroRazonSocial', 
            'filtroPyme', 
            'filtroNuevaCreacion', 
            'filtroBloqueada',
            'filtroPoblacion'
        ]);
        $this->resetPage();
    }

    protected function getEmpresas()
    {
        $modeloEmpresa = $this->anioSeleccionado === $this->anioAnterior 
            ? EmpresaAnterior::class 
            : Empresa::class;

        $tablaGrupos = $this->anioSeleccionado === $this->anioAnterior 
            ? 'grupos_anterior' 
            : 'grupos';

        $query = $modeloEmpresa::query()
            ->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->whereNotNull('razon_social')
            ->where('razon_social', '!=', '')
            ->whereNotIn('cif', function ($subQuery) use ($tablaGrupos) {
                $subQuery->select('cif')
                    ->from($tablaGrupos)
                    ->whereNotNull('cif')
                    ->where('cif', '!=', '');
            });

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
        if ($this->filtroPoblacion) {
            $query->where('poblacion', 'like', "%{$this->filtroPoblacion}%");
        }

        if ($this->perPage == 0) {
            return $query->orderBy('id', 'asc')->paginate($query->count());
        }

        return $query->orderBy('id', 'asc')->paginate($this->perPage);
    }

    protected function getEstadisticas()
    {
        $modeloEmpresa = $this->anioSeleccionado === $this->anioAnterior 
            ? EmpresaAnterior::class 
            : Empresa::class;

        $tablaGrupos = $this->anioSeleccionado === $this->anioAnterior 
            ? 'grupos_anterior' 
            : 'grupos';

        $totalUniverso = $modeloEmpresa::query()
            ->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->whereNotNull('razon_social')
            ->where('razon_social', '!=', '')
            ->count();

        $stats = $modeloEmpresa::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN pyme = "SI" THEN 1 ELSE 0 END) as pymes,
                SUM(credito_asignado) as asignado,
                SUM(credito_dispuesto) as dispuesto,
                SUM(credito_disponible) as disponible,
                AVG(credito_asignado) as promedio
            ')
            ->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->whereNotNull('razon_social')
            ->where('razon_social', '!=', '')
            ->whereNotIn('cif', function ($subQuery) use ($tablaGrupos) {
                $subQuery->select('cif')
                    ->from($tablaGrupos)
                    ->whereNotNull('cif')
                    ->where('cif', '!=', '');
            })
            ->first();

        $stats->total_universo = $totalUniverso;
        $stats->porcentaje = $totalUniverso > 0 ? ($stats->total / $totalUniverso) * 100 : 0;

        return $stats;
    }

    public function render()
    {
        return view('livewire.webcurso.empresas-sin-grupos', [
            'empresas' => $this->getEmpresas(),
            'stats' => $this->getEstadisticas(),
        ])->layout('layouts.app', ['title' => 'Empresas Sin Grupos - WebCurso']);
    }
}
