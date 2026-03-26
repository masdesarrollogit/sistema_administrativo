<?php

namespace App\Livewire\Webcurso;

use App\Models\Tutor;
use Livewire\Component;
use Livewire\WithPagination;

class TutoresIndex extends Component
{
    use WithPagination;

    // Filtros
    public string $busqueda = '';
    public string $filtroTipo = '';
    public string $filtroActivo = '1';

    // Modal crear/editar
    public bool $mostrarModal = false;
    public ?int $tutorEditandoId = null;
    public string $nombre = '';
    public string $apellido1 = '';
    public string $apellido2 = '';
    public string $nif = '';
    public string $email = '';
    public string $telefono = '';
    public string $tipo = 'interno';
    public bool $activo = true;
    public string $moodleUsername = '';

    protected $queryString = [
        'busqueda' => ['except' => ''],
        'filtroTipo' => ['except' => ''],
        'filtroActivo' => ['except' => '1'],
    ];

    protected function rules(): array
    {
        $nifRule = 'required|string|max:15|unique:tutores,nif';
        if ($this->tutorEditandoId) {
            $nifRule .= ',' . $this->tutorEditandoId;
        }

        return [
            'nombre' => 'required|string|max:255',
            'apellido1' => 'required|string|max:255',
            'apellido2' => 'nullable|string|max:255',
            'nif' => $nifRule,
            'email' => 'required|email|max:255',
            'telefono' => 'required|string|max:20',
            'tipo' => 'required|in:interno,externo',
            'activo' => 'boolean',
        ];
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroActivo(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        $this->resetFormulario();
        $this->mostrarModal = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $tutor = Tutor::findOrFail($id);
        $this->tutorEditandoId = $tutor->id;
        $this->nombre = $tutor->nombre;
        $this->apellido1 = $tutor->apellido1;
        $this->apellido2 = $tutor->apellido2 ?? '';
        $this->nif = $tutor->nif;
        $this->email = $tutor->email;
        $this->telefono = $tutor->telefono;
        $this->tipo = $tutor->tipo;
        $this->activo = $tutor->activo;
        $this->moodleUsername = $tutor->moodle_username ?? '';
        $this->mostrarModal = true;
    }

    public function guardar(): void
    {
        $this->validate();

        $datos = [
            'nombre'         => $this->nombre,
            'apellido1'      => $this->apellido1,
            'apellido2'      => $this->apellido2 ?: null,
            'nif'            => $this->nif,
            'email'          => $this->email,
            'telefono'       => $this->telefono,
            'tipo'           => $this->tipo,
            'activo'         => $this->activo,
            'moodle_username' => $this->moodleUsername ?: null,
        ];

        if ($this->tutorEditandoId) {
            Tutor::findOrFail($this->tutorEditandoId)->update($datos);
            session()->flash('message', 'Tutor actualizado correctamente.');
        } else {
            Tutor::create($datos);
            session()->flash('message', 'Tutor creado correctamente.');
        }

        $this->cerrarModal();
    }

    public function toggleActivo(int $id): void
    {
        $tutor = Tutor::findOrFail($id);
        $tutor->update(['activo' => !$tutor->activo]);
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->resetFormulario();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['busqueda', 'filtroTipo', 'filtroActivo']);
        $this->resetPage();
    }

    protected function resetFormulario(): void
    {
        $this->reset(['tutorEditandoId', 'nombre', 'apellido1', 'apellido2', 'nif', 'email', 'telefono', 'tipo', 'activo', 'moodleUsername']);
        $this->tipo = 'interno';
        $this->activo = true;
        $this->resetValidation();
    }

    public function render()
    {
        $query = Tutor::query();

        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->busqueda}%")
                  ->orWhere('apellido1', 'like', "%{$this->busqueda}%")
                  ->orWhere('nif', 'like', "%{$this->busqueda}%");
            });
        }

        if ($this->filtroTipo) {
            $query->where('tipo', $this->filtroTipo);
        }

        if ($this->filtroActivo !== '') {
            $query->where('activo', (bool) $this->filtroActivo);
        }

        $tutores = $query->orderBy('apellido1')->paginate(15);

        return view('livewire.webcurso.tutores-index', [
            'tutores' => $tutores,
        ])->layout('layouts.app', ['title' => 'Tutores - WebCurso']);
    }
}
