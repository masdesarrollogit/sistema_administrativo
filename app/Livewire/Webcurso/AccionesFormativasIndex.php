<?php

namespace App\Livewire\Webcurso;

use App\Models\AccionFormativa;
use App\Models\AccionFormativaMoodleCurso;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Moodle\Services\MoodleService;

class AccionesFormativasIndex extends Component
{
    use WithPagination;

    // Filtros
    public string $busqueda = '';
    public string $filtroEstado = '';
    public string $filtroAreaProfesional = '';
    public string $filtroPlataforma = '';

    // ─── Vinculación con Moodle ───
    public ?int $accionVinculandoId = null;
    public string $vinculoMoodleCourseId = '';
    public string $vinculoMoodleFullname = '';
    public string $vinculoTipo = 'activa';
    public string $vinculoBusqueda = '';       // texto de búsqueda por nombre
    public array  $vinculoSugerencias = [];    // resultados de Moodle
    public bool   $vinculoBuscando = false;

    protected $queryString = [
        'busqueda' => ['except' => ''],
        'filtroEstado' => ['except' => ''],
        'filtroAreaProfesional' => ['except' => ''],
        'filtroPlataforma' => ['except' => ''],
    ];

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroAreaProfesional(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroPlataforma(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['busqueda', 'filtroEstado', 'filtroAreaProfesional', 'filtroPlataforma']);
        $this->resetPage();
    }

    // ═══════════════════════════════════════════════
    //  VINCULACIÓN MOODLE
    // ═══════════════════════════════════════════════

    public function abrirVinculacion(int $accionId): void
    {
        $this->accionVinculandoId = $accionId;
        $this->vinculoMoodleCourseId = '';
        $this->vinculoMoodleFullname = '';
        $this->vinculoTipo = 'activa';
        $this->vinculoBusqueda = '';
        $this->vinculoSugerencias = [];
        $this->vinculoBuscando = false;
        $this->resetValidation();
    }

    public function updatedVinculoBusqueda(string $value): void
    {
        $this->vinculoMoodleCourseId = '';
        $this->vinculoMoodleFullname = '';
        $this->vinculoSugerencias = [];

        if (strlen(trim($value)) < 3) {
            return;
        }

        $this->vinculoBuscando = true;

        try {
            $this->vinculoSugerencias = app(MoodleService::class)->searchCourses(trim($value));
        } catch (\Exception $e) {
            // silencioso — el usuario verá que no hay sugerencias
        }

        $this->vinculoBuscando = false;
    }

    public function seleccionarCursoMoodle(int $id, string $fullname): void
    {
        $this->vinculoMoodleCourseId = (string) $id;
        $this->vinculoMoodleFullname = $fullname;
        $this->vinculoBusqueda = $fullname;
        $this->vinculoSugerencias = [];
    }

    public function cerrarVinculacion(): void
    {
        $this->accionVinculandoId = null;
    }

    public function guardarVinculo(): void
    {
        $this->validate([
            'vinculoMoodleCourseId' => 'required|integer|min:1',
            'vinculoMoodleFullname' => 'required|string|max:255',
            'vinculoTipo'           => 'required|in:plantilla,activa,repaso,desactualizado',
        ]);

        AccionFormativaMoodleCurso::create([
            'accion_formativa_id' => $this->accionVinculandoId,
            'moodle_course_id'    => (int) $this->vinculoMoodleCourseId,
            'moodle_fullname'     => $this->vinculoMoodleFullname,
            'tipo'                => $this->vinculoTipo,
        ]);

        $this->cerrarVinculacion();
        session()->flash('message', 'Curso de Moodle vinculado correctamente.');
    }

    public function eliminarVinculo(int $vinculoId): void
    {
        AccionFormativaMoodleCurso::findOrFail($vinculoId)->delete();
    }

    public function render()
    {
        $query = AccionFormativa::query();

        if ($this->busqueda) {
            $query->where(function ($q) {
                $q->where('denominacion', 'like', "%{$this->busqueda}%")
                  ->orWhere('numero_accion', 'like', "%{$this->busqueda}%");
            });
        }

        if ($this->filtroEstado) {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroAreaProfesional) {
            $query->where('area_profesional', $this->filtroAreaProfesional);
        }

        if ($this->filtroPlataforma) {
            $query->where('nif_proveedor_plataforma', $this->filtroPlataforma);
        }

        $acciones = $query->withCount('moodleCursos')
            ->orderBy('numero_accion')
            ->paginate(25);

        // Obtener valores únicos para filtros
        $areasProfesionales = AccionFormativa::whereNotNull('area_profesional')
            ->distinct()
            ->orderBy('area_profesional')
            ->pluck('area_profesional');

        $plataformas = AccionFormativa::whereNotNull('nif_proveedor_plataforma')
            ->distinct()
            ->pluck('nif_proveedor_plataforma', 'nif_proveedor_plataforma');

        $totalAcciones = AccionFormativa::count();
        $totalVinculadas = AccionFormativa::has('moodleCursos')->count();

        return view('livewire.webcurso.acciones-formativas-index', [
            'acciones'           => $acciones,
            'areasProfesionales' => $areasProfesionales,
            'plataformas'        => $plataformas,
            'totalAcciones'      => $totalAcciones,
            'totalVinculadas'    => $totalVinculadas,
        ])->layout('layouts.app', ['title' => 'Acciones Formativas - WebCurso']);
    }
}
