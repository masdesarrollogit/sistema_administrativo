<?php

namespace App\Livewire\Webcurso;

use App\Models\EncomiendaAlumnoStaging;
use App\Models\EncomiendaContrato;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithPagination;

class EncomiendaContratosIndex extends Component
{
    use WithPagination;

    public string $filtroEstado = '';
    public string $search = '';
    public bool $verDescartados = false;
    public ?string $resumenSync = null;

    protected $queryString = [
        'filtroEstado'   => ['except' => ''],
        'search'         => ['except' => ''],
        'verDescartados' => ['except' => false],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatingVerDescartados(): void
    {
        $this->resetPage();
    }

    /**
     * Descarta un contrato (soft): se oculta y el sync deja de traerlo.
     * Solo toca los registros de encomienda; candidato y alumnos ya creados se conservan.
     */
    public function descartar(int $id): void
    {
        $contrato = EncomiendaContrato::find($id);
        if (!$contrato || $contrato->estaDescartado()) {
            return;
        }

        $contrato->update([
            'descartado_en'  => now(),
            'descartado_por' => auth()->id(),
        ]);

        // Ocultar sus alumnos pendientes (no materializados) del panel de matriculación
        EncomiendaAlumnoStaging::where('encomienda_contrato_id', $contrato->id)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'descartado']);

        session()->flash('message-encomienda', 'Contrato descartado. El sync ya no lo volverá a traer.');
    }

    public function restaurar(int $id): void
    {
        $contrato = EncomiendaContrato::find($id);
        if (!$contrato || !$contrato->estaDescartado()) {
            return;
        }

        $contrato->update(['descartado_en' => null, 'descartado_por' => null]);

        // Reactivar sus alumnos descartados que no llegaron a materializarse
        EncomiendaAlumnoStaging::where('encomienda_contrato_id', $contrato->id)
            ->where('estado', 'descartado')
            ->whereNull('alumno_id')
            ->update(['estado' => 'pendiente']);

        session()->flash('message-encomienda', 'Contrato restaurado.');
    }

    /** Ejecuta el comando de sincronización en caliente y muestra el resumen. */
    public function sincronizarAhora(): void
    {
        Artisan::call('encomienda:sincronizar');
        $this->resumenSync = trim(Artisan::output());
        $this->resetPage();
    }

    public function limpiarResumen(): void
    {
        $this->resumenSync = null;
    }

    public function render()
    {
        $contratos = EncomiendaContrato::query()
            ->withCount(['alumnos', 'alumnosPendientes'])
            ->when($this->verDescartados, fn ($q) => $q->descartados(), fn ($q) => $q->activos())
            ->when($this->filtroEstado, fn ($q) => $q->where('estado_procesamiento', $this->filtroEstado))
            ->when(trim($this->search), function ($q) {
                $s = trim($this->search);
                $q->where(fn ($w) => $w
                    ->where('empresa_cif', 'like', "%{$s}%")
                    ->orWhere('empresa_razon_social', 'like', "%{$s}%")
                    ->orWhere('firmante_nombre', 'like', "%{$s}%")
                    ->orWhere('referencia_aceptacion', 'like', "%{$s}%"));
            })
            ->with(['empresa', 'candidato'])
            ->orderByDesc('aceptado_en')
            ->paginate(20);

        $stats = [
            'total'             => EncomiendaContrato::activos()->count(),
            'pendiente_empresa' => EncomiendaContrato::activos()->where('estado_procesamiento', 'pendiente_empresa')->count(),
            'candidato_creado'  => EncomiendaContrato::activos()->where('estado_procesamiento', 'candidato_creado')->count(),
            'descartados'       => EncomiendaContrato::descartados()->count(),
        ];

        return view('livewire.webcurso.encomienda-contratos-index', compact('contratos', 'stats'))
            ->layout('layouts.app', ['title' => 'Contratos de Encomienda - WebCurso']);
    }
}
