<?php

namespace App\Livewire\Webcurso;

use App\Models\Empresa;
use App\Models\EmpresaAnterior;
use App\Models\Grupo;
use App\Models\GrupoAnterior;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public int $anioActual;
    public int $anioAnterior;
    public int $anioSeleccionado;

    // Estadísticas
    public int $totalEmpresas = 0;
    public int $totalPymes = 0;
    public float $totalAsignado = 0;
    public float $totalDispuesto = 0;
    public float $totalDisponible = 0;
    public float $promedioAsignado = 0;
    public int $totalGrupos = 0;
    public int $gruposConCif = 0;
    public int $empresasSinGrupos = 0;
    public ?string $ultimaActualizacion = null;

    public function mount(): void
    {
        $this->anioActual = (int) date('Y');
        $this->anioAnterior = $this->anioActual - 1;
        $this->anioSeleccionado = $this->anioActual;
        
        $this->cargarEstadisticas();
    }

    public function cambiarAnio(int $anio): void
    {
        $this->anioSeleccionado = $anio;
        $this->cargarEstadisticas();
    }

    protected function cargarEstadisticas(): void
    {
        $modeloEmpresa = $this->anioSeleccionado === $this->anioAnterior 
            ? EmpresaAnterior::class 
            : Empresa::class;
        
        $modeloGrupo = $this->anioSeleccionado === $this->anioAnterior 
            ? GrupoAnterior::class 
            : Grupo::class;

        // Estadísticas de empresas
        $stats = $modeloEmpresa::query()
            ->selectRaw('
                COUNT(*) as total_empresas,
                SUM(CASE WHEN pyme = "SI" THEN 1 ELSE 0 END) as total_pymes,
                SUM(credito_asignado) as total_asignado,
                SUM(credito_dispuesto) as total_dispuesto,
                SUM(credito_disponible) as total_disponible,
                AVG(credito_asignado) as promedio_asignado
            ')
            ->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->whereNotNull('razon_social')
            ->where('razon_social', '!=', '')
            ->first();

        $this->totalEmpresas = $stats->total_empresas ?? 0;
        $this->totalPymes = $stats->total_pymes ?? 0;
        $this->totalAsignado = $stats->total_asignado ?? 0;
        $this->totalDispuesto = $stats->total_dispuesto ?? 0;
        $this->totalDisponible = $stats->total_disponible ?? 0;
        $this->promedioAsignado = $stats->promedio_asignado ?? 0;

        // Estadísticas de grupos
        $this->totalGrupos = $modeloGrupo::count();
        $this->gruposConCif = $modeloGrupo::conCif()->count();

        // Empresas sin grupos
        $tablaEmpresas = $this->anioSeleccionado === $this->anioAnterior ? 'empresas_anterior' : 'empresas';
        $tablaGrupos = $this->anioSeleccionado === $this->anioAnterior ? 'grupos_anterior' : 'grupos';
        
        $this->empresasSinGrupos = $modeloEmpresa::query()
            ->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->whereNotIn('cif', function ($query) use ($tablaGrupos) {
                $query->select('cif')
                    ->from($tablaGrupos)
                    ->whereNotNull('cif')
                    ->where('cif', '!=', '');
            })
            ->count();

        // Última actualización
        $ultima = $modeloEmpresa::query()
            ->selectRaw('GREATEST(MAX(fecha_creacion), MAX(actualizacion)) as ultima')
            ->first();
        
        $this->ultimaActualizacion = $ultima->ultima 
            ? \Carbon\Carbon::parse($ultima->ultima)->format('d/m/Y H:i') 
            : null;
    }

    public function render()
    {
        return view('livewire.webcurso.dashboard')
            ->layout('layouts.app', ['title' => 'Panel WebCurso']);
    }
}
