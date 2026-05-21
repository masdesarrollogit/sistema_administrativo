<?php

namespace App\Livewire\Webcurso;

use App\Models\Candidato;
use App\Models\TipoCandidato;
use Livewire\Component;
use Livewire\WithPagination;

class CandidatosIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $filtroTipo = '';
    public $filtroEstatus = '';
    public string $orden = 'recientes';
    public string $filtroDesde = '';
    public string $filtroHasta = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'filtroTipo' => ['except' => ''],
        'filtroEstatus' => ['except' => ''],
        'orden' => ['except' => 'recientes'],
        'filtroDesde' => ['except' => ''],
        'filtroHasta' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFiltroTipo()
    {
        $this->resetPage();
    }

    public function updatingFiltroEstatus()
    {
        $this->resetPage();
    }

    public function updatingOrden()
    {
        $this->resetPage();
    }

    public function updatingFiltroDesde()
    {
        $this->resetPage();
    }

    public function updatingFiltroHasta()
    {
        $this->resetPage();
    }

    public function limpiarFiltros()
    {
        $this->reset(['search', 'filtroTipo', 'filtroEstatus', 'orden', 'filtroDesde', 'filtroHasta']);
        $this->resetPage();
    }

    public function eliminarCandidato(Candidato $candidato)
    {
        $candidato->requisitos()->delete();
        $candidato->archivos()->delete();
        $candidato->delete();
        session()->flash('message', 'Candidato eliminado.');
    }

    public function pausarCandidato(Candidato $candidato)
    {
        $candidato->pausar();
        session()->flash('message', 'Candidato pausado exitosamente');
    }

    public function reactivarCandidato(Candidato $candidato)
    {
        $candidato->reactivar();
        session()->flash('message', 'Candidato reactivado exitosamente');
    }

    // Modal de Detalles
    public $showDetailsModal = false;
    public ?Candidato $selectedCandidatoDetails = null;

    public function verDetalles($id)
    {
        $this->selectedCandidatoDetails = Candidato::with(['requisitos.tipoRequisito', 'tipoCandidato'])->find($id);
        $this->showDetailsModal = true;
    }

    public function cerrarDetalles()
    {
        $this->showDetailsModal = false;
        $this->selectedCandidatoDetails = null;
    }

    public function render()
    {
        $query = Candidato::with(['tipoCandidato', 'empresa', 'empresaExterna', 'requisitos.tipoRequisito']);

        // Búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre_contacto', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('telefono', 'like', '%' . $this->search . '%')
                  ->orWhereHas('empresa', fn($e) => $e->where('razon_social', 'like', '%' . $this->search . '%'))
                  ->orWhereHas('empresaExterna', fn($e) => $e->where('razon_social', 'like', '%' . $this->search . '%'));
            });
        }

        // Filtro por tipo
        if ($this->filtroTipo) {
            $query->whereHas('tipoCandidato', function ($q) {
                $q->where('codigo', $this->filtroTipo);
            });
        }

        // Filtro por estatus
        if ($this->filtroEstatus) {
            $query->where('estatus', $this->filtroEstatus);
        } else {
            // Por defecto ocultamos los desactivados (y antiguos cancelados)
            $query->whereNotIn('estatus', ['desactivado', 'cancelado']);
        }

        // Filtro por rango de fecha de alta
        if ($this->filtroDesde) {
            $query->whereDate('created_at', '>=', $this->filtroDesde);
        }
        if ($this->filtroHasta) {
            $query->whereDate('created_at', '<=', $this->filtroHasta);
        }

        $candidatos = $query
            ->when($this->orden === 'antiguos', fn ($q) => $q->oldest(), fn ($q) => $q->latest())
            ->paginate(15);

        $tiposCandidato = TipoCandidato::activos()->get();

        $estatusOptions = [
            'pendiente' => 'Pendiente',
            'completo' => 'Completo',
            'desactivado' => 'Desactivado',
            'pausado' => 'Pausado',
        ];

        return view('livewire.webcurso.candidatos-index', [
            'candidatos' => $candidatos,
            'tiposCandidato' => $tiposCandidato,
            'estatusOptions' => $estatusOptions,
        ])->layout('layouts.app', ['title' => 'Candidatos - WebCurso']);
    }

}
