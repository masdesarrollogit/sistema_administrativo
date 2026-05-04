<?php

namespace App\Livewire\Webcurso;

use App\Models\Alumno;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class AlumnosIndex extends Component
{
    use WithPagination;

    // Filtros
    public string $search = '';
    public string $filtroEmpresa = '';
    public string $filtroActivo = '1';
    public string $filtroTipo = '';
    public string $filtroAno = '';
    public string $filtroDesde = '';
    public string $filtroHasta = '';

    // Modal edición
    public bool $mostrarModalEditar = false;
    public ?int $alumnoEditandoId = null;
    public string $nombre = '';
    public string $apellido1 = '';
    public string $apellido2 = '';
    public string $nif = '';
    public string $email = '';
    public string $telefono = '';
    public string $niss = '';
    public string $ccc = '';
    public string $grupoCotizacionTgss = '';
    public string $fechaNacimiento = '';
    public string $sexo = '';
    public string $nivelEstudios = '';
    public string $categoriaProfesional = '';
    public int $jornadaLaboral = 1;

    // Modal historial de grupos
    public bool $mostrarModalGrupos = false;
    public ?int $alumnoGruposId = null;
    public string $alumnoGruposNombre = '';

    protected $queryString = [
        'search'        => ['except' => ''],
        'filtroEmpresa' => ['except' => ''],
        'filtroActivo'  => ['except' => '1'],
        'filtroTipo'    => ['except' => ''],
        'filtroAno'     => ['except' => ''],
        'filtroDesde'   => ['except' => ''],
        'filtroHasta'   => ['except' => ''],
    ];

    protected function rules(): array
    {
        $nifRule = 'required|string|max:15|unique:alumnos,nif';
        if ($this->alumnoEditandoId) {
            $nifRule .= ',' . $this->alumnoEditandoId;
        }

        $emailRule = 'nullable|email|max:255|unique:alumnos,email';
        if ($this->alumnoEditandoId) {
            $emailRule .= ',' . $this->alumnoEditandoId;
        }

        return [
            'nombre'               => 'required|string|max:255',
            'apellido1'            => 'required|string|max:255',
            'apellido2'            => 'nullable|string|max:255',
            'nif'                  => $nifRule,
            'email'                => $emailRule,
            'telefono'             => 'nullable|string|max:20',
            'niss'                 => 'nullable|string|max:12',
            'ccc'                  => 'nullable|string|max:11',
            'grupoCotizacionTgss'  => 'nullable|string|max:5',
            'fechaNacimiento'      => 'nullable|date',
            'sexo'                 => 'nullable|in:H,M',
            'nivelEstudios'        => 'nullable|integer|min:1|max:10',
            'categoriaProfesional' => 'nullable|integer|min:1|max:5',
            'jornadaLaboral'       => 'required|integer|min:1|max:4',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEmpresa(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroActivo(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroAno(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroDesde(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroHasta(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'filtroEmpresa', 'filtroTipo', 'filtroAno', 'filtroDesde', 'filtroHasta']);
        $this->filtroActivo = '1';
        $this->resetPage();
    }

    public function abrirModalEditar(int $id): void
    {
        $alumno = Alumno::findOrFail($id);
        $this->alumnoEditandoId      = $alumno->id;
        $this->nombre                = $alumno->nombre;
        $this->apellido1             = $alumno->apellido1;
        $this->apellido2             = $alumno->apellido2 ?? '';
        $this->nif                   = $alumno->nif;
        $this->email                 = $alumno->email ?? '';
        $this->telefono              = $alumno->telefono ?? '';
        $this->niss                  = $alumno->niss ?? '';
        $this->ccc                   = $alumno->ccc ?? '';
        $this->grupoCotizacionTgss   = $alumno->grupo_cotizacion_tgss ?? '';
        $this->fechaNacimiento       = $alumno->fecha_nacimiento?->format('Y-m-d') ?? '';
        $this->sexo                  = $alumno->sexo ?? '';
        $this->nivelEstudios         = $alumno->nivel_estudios !== null ? (string) $alumno->nivel_estudios : '';
        $this->categoriaProfesional  = $alumno->categoria_profesional !== null ? (string) $alumno->categoria_profesional : '';
        $this->jornadaLaboral        = $alumno->jornada_laboral;
        $this->mostrarModalEditar    = true;
    }

    public function guardar(): void
    {
        $this->validate();

        Alumno::findOrFail($this->alumnoEditandoId)->update([
            'nombre'                => $this->nombre,
            'apellido1'             => $this->apellido1,
            'apellido2'             => $this->apellido2 ?: null,
            'nif'                   => $this->nif,
            'email'                 => $this->email ?: null,
            'telefono'              => $this->telefono ?: null,
            'niss'                  => $this->niss ?: null,
            'ccc'                   => $this->ccc ?: null,
            'grupo_cotizacion_tgss' => $this->grupoCotizacionTgss ?: null,
            'fecha_nacimiento'      => $this->fechaNacimiento ?: null,
            'sexo'                  => $this->sexo ?: null,
            'nivel_estudios'        => $this->nivelEstudios !== '' ? (int) $this->nivelEstudios : null,
            'categoria_profesional' => $this->categoriaProfesional !== '' ? (int) $this->categoriaProfesional : null,
            'jornada_laboral'       => $this->jornadaLaboral,
        ]);

        session()->flash('message', 'Alumno actualizado correctamente.');
        $this->cerrarModalEditar();
    }

    public function cerrarModalEditar(): void
    {
        $this->mostrarModalEditar = false;
        $this->reset([
            'alumnoEditandoId', 'nombre', 'apellido1', 'apellido2',
            'nif', 'email', 'telefono', 'niss', 'ccc', 'grupoCotizacionTgss',
            'fechaNacimiento', 'sexo', 'nivelEstudios', 'categoriaProfesional',
        ]);
        $this->jornadaLaboral = 1;
        $this->resetValidation();
    }

    public function abrirModalGrupos(int $id): void
    {
        $alumno = Alumno::findOrFail($id);
        $this->alumnoGruposId      = $id;
        $this->alumnoGruposNombre  = $alumno->nombre_completo;
        $this->mostrarModalGrupos  = true;
    }

    public function cerrarModalGrupos(): void
    {
        $this->mostrarModalGrupos = false;
        $this->reset(['alumnoGruposId', 'alumnoGruposNombre']);
    }

    public function toggleActivo(int $id): void
    {
        $alumno = Alumno::findOrFail($id);
        $alumno->update(['activo' => !$alumno->activo]);
    }

    public function render()
    {
        $query = Alumno::query()
            ->with('empresa')
            ->withCount([
                'gruposFormativos as grupos_total',
                'gruposFormativos as grupos_activos' => fn ($q) =>
                    $q->whereIn('estado', ['abierto', 'comunicado', 'en_curso']),
                'matriculasAutonomas as autonomos_total',
                'participantesBonificados as bonificados_total',
                'cursosLegacy as legacy_total',
            ]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->search}%")
                  ->orWhere('apellido1', 'like', "%{$this->search}%")
                  ->orWhere('apellido2', 'like', "%{$this->search}%")
                  ->orWhere('nif', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        if ($this->filtroEmpresa) {
            $query->whereHas('empresa', function ($q) {
                $q->where('cif', 'like', "%{$this->filtroEmpresa}%")
                  ->orWhere('razon_social', 'like', "%{$this->filtroEmpresa}%");
            });
        }

        if ($this->filtroActivo !== '') {
            $query->where('activo', (bool) $this->filtroActivo);
        }

        if ($this->filtroTipo === 'autonomo') {
            $query->whereHas('matriculasAutonomas');
        } elseif ($this->filtroTipo === 'fundae') {
            $query->where(function ($q) {
                $q->whereHas('gruposFormativos')
                  ->orWhereHas('participantesBonificados');
            });
        } elseif ($this->filtroTipo === 'legacy') {
            $query->whereHas('cursosLegacy');
        }

        // Filtro por año / rango de fechas — busca en cualquiera de las 4 fuentes de cursos
        if ($this->filtroAno || $this->filtroDesde || $this->filtroHasta) {
            $query->where(function ($q) {
                $aplicarRango = function ($sub, string $columna) {
                    if ($this->filtroAno) {
                        $sub->whereYear($columna, $this->filtroAno);
                    }
                    if ($this->filtroDesde) {
                        $sub->where($columna, '>=', $this->filtroDesde);
                    }
                    if ($this->filtroHasta) {
                        $sub->where($columna, '<=', $this->filtroHasta);
                    }
                };

                $q->whereHas('gruposFormativos', fn ($s) => $aplicarRango($s, 'fecha_inicio'))
                  ->orWhereHas('participantesBonificados', fn ($s) => $aplicarRango($s, 'fecha_inicio'))
                  ->orWhereHas('matriculasAutonomas', fn ($s) => $aplicarRango($s, 'fecha_inicio'))
                  ->orWhereHas('cursosLegacy', fn ($s) => $aplicarRango($s, 'fecha_inicio'));
            });
        }

        $alumnos = $query
            ->orderBy('apellido1')
            ->orderBy('nombre')
            ->paginate(25);

        $gruposDelAlumno = null;
        $autonomosDelAlumno = null;
        $bonificadosDelAlumno = null;
        $legacyDelAlumno = null;
        if ($this->mostrarModalGrupos && $this->alumnoGruposId) {
            $alumnoModal = Alumno::findOrFail($this->alumnoGruposId);
            $gruposDelAlumno = $alumnoModal
                ->gruposFormativos()
                ->with(['accionFormativa', 'empresa'])
                ->withPivot(['moodle_username', 'estado_moodle'])
                ->orderByDesc('fecha_inicio')
                ->get();
            $autonomosDelAlumno = $alumnoModal
                ->matriculasAutonomas()
                ->with(['accionFormativa', 'tutor'])
                ->orderByDesc('fecha_inicio')
                ->get();
            $bonificadosDelAlumno = $alumnoModal
                ->participantesBonificados()
                ->orderByDesc('fecha_inicio')
                ->get();
            $legacyDelAlumno = $alumnoModal
                ->cursosLegacy()
                ->orderByDesc('fecha_inicio')
                ->get();
            // Resolver acción formativa por formation_group_alpha (= numero_accion en Panel)
            $numerosAccion = $legacyDelAlumno
                ->pluck('formation_group_alpha')
                ->filter()
                ->unique()
                ->toArray();
            $accionesPorNumero = \App\Models\AccionFormativa::whereIn('numero_accion', $numerosAccion)
                ->get(['id', 'numero_accion', 'denominacion', 'horas'])
                ->keyBy('numero_accion');
        }

        $aniosDisponibles = collect()
            ->merge(DB::table('alumnos_legacy_cursos')->selectRaw('YEAR(fecha_inicio) AS y')->whereNotNull('fecha_inicio')->distinct()->pluck('y'))
            ->merge(DB::table('participantes_bonificados')->selectRaw('YEAR(fecha_inicio) AS y')->whereNotNull('fecha_inicio')->distinct()->pluck('y'))
            ->merge(DB::table('grupos_formativos')->selectRaw('YEAR(fecha_inicio) AS y')->whereNotNull('fecha_inicio')->distinct()->pluck('y'))
            ->merge(DB::table('matriculas_autonomas')->selectRaw('YEAR(fecha_inicio) AS y')->whereNotNull('fecha_inicio')->distinct()->pluck('y'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        return view('livewire.webcurso.alumnos-index', [
            'alumnos'               => $alumnos,
            'gruposDelAlumno'       => $gruposDelAlumno,
            'autonomosDelAlumno'    => $autonomosDelAlumno,
            'bonificadosDelAlumno'  => $bonificadosDelAlumno,
            'legacyDelAlumno'       => $legacyDelAlumno,
            'accionesPorNumero'     => $accionesPorNumero ?? collect(),
            'aniosDisponibles'      => $aniosDisponibles,
        ])->layout('layouts.app', ['title' => 'Alumnos - WebCurso']);
    }
}
