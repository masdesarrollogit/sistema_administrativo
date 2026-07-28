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

    public function mount(): void
    {
        if ($this->filtroAno === '') {
            $this->filtroAno = (string) date('Y');
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'filtroEmpresa', 'filtroTipo', 'filtroDesde', 'filtroHasta']);
        $this->filtroActivo = '1';
        $this->filtroAno    = (string) date('Y');
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

    /**
     * Resuelve el curso más reciente del alumno cuyas fechas tocan el año dado.
     * Unifica las 4 fuentes (gruposFormativos, participantesBonificados,
     * matriculasAutonomas, cursosLegacy) y devuelve un array con tipo,
     * denominación, acción/grupo, fechas y conteo de otros cursos en ese año.
     */
    private function resolverCursoReciente(Alumno $a, int $year): ?array
    {
        $inicio = "{$year}-01-01";
        $fin    = "{$year}-12-31";

        $candidatos = collect();

        foreach ($a->gruposFormativos()->with('accionFormativa')
                    ->where('fecha_inicio', '<=', $fin)
                    ->where('fecha_fin', '>=', $inicio)->get() as $g) {
            // En gestión externa el acción/grupo es el que nos dio la empresa cliente (texto libre).
            $accionGrupo = $g->gestion_externa
                ? array_pad(explode('/', (string) $g->codigo_grupo_externo, 2), 2, null)
                : [$g->accionFormativa?->numero_accion, $g->id_grupo_fundae];

            $candidatos->push([
                'tipo'         => $g->gestion_externa ? 'FUNDAE ext.' : 'FUNDAE',
                'denominacion' => $g->accionFormativa?->denominacion_limpia ?? $g->accionFormativa?->denominacion ?? '—',
                'accion'       => $accionGrupo[0] ?: null,
                'grupo'        => $accionGrupo[1] ?: null,
                'fecha_inicio' => $g->fecha_inicio,
                'fecha_fin'    => $g->fecha_fin,
            ]);
        }

        foreach ($a->participantesBonificados()
                    ->where('fecha_inicio', '<=', $fin)
                    ->where('fecha_fin', '>=', $inicio)->get() as $pb) {
            $accion = null; $grupo = null;
            if ($pb->id_codigo_grupo && preg_match('/\((\d+)\)\s*(\d+)\/(\d+)/', $pb->id_codigo_grupo, $m)) {
                $accion = $m[2];
                $grupo  = $m[3];
            }
            $candidatos->push([
                'tipo'         => 'FUNDAE imp.',
                'denominacion' => $pb->id_codigo_grupo ?: '—',
                'accion'       => $accion,
                'grupo'        => $grupo,
                'fecha_inicio' => $pb->fecha_inicio,
                'fecha_fin'    => $pb->fecha_fin,
            ]);
        }

        foreach ($a->matriculasAutonomas()->with('accionFormativa')
                    ->where('fecha_inicio', '<=', $fin)
                    ->where('fecha_fin', '>=', $inicio)->get() as $ma) {
            $candidatos->push([
                'tipo'         => $ma->esParticular() ? 'Particular' : 'Autónomo',
                'denominacion' => $ma->accionFormativa?->denominacion_limpia ?? $ma->accionFormativa?->denominacion ?? '—',
                'accion'       => $ma->accionFormativa?->numero_accion,
                'grupo'        => null,
                'fecha_inicio' => $ma->fecha_inicio,
                'fecha_fin'    => $ma->fecha_fin,
            ]);
        }

        foreach ($a->cursosLegacy()
                    ->where('fecha_inicio', '<=', $fin)
                    ->where('fecha_fin', '>=', $inicio)->get() as $cl) {
            $tipo = $cl->origen_enriquecimiento === 'no_bonificado' ? 'No bonificado' : 'Legacy';
            $candidatos->push([
                'tipo'         => $tipo,
                'denominacion' => $cl->curso_titulo ?? '—',
                'accion'       => $cl->formation_group_alpha,
                'grupo'        => $cl->formation_group_number,
                'fecha_inicio' => $cl->fecha_inicio,
                'fecha_fin'    => $cl->fecha_fin,
            ]);
        }

        if ($candidatos->isEmpty()) {
            return null;
        }

        $ordenados = $candidatos->sortByDesc(fn ($c) => $c['fecha_inicio'])->values();
        $reciente = $ordenados->first();
        $reciente['otros_2026'] = $ordenados->count() - 1;
        return $reciente;
    }

    public function render()
    {
        $query = Alumno::query()
            ->with('empresa')
            ->withCount([
                'gruposFormativos as grupos_total',
                'gruposFormativos as grupos_activos' => fn ($q) =>
                    $q->whereIn('estado', ['abierto', 'comunicado', 'en_curso']),
                // Comparten tabla pero se cuentan por separado: el 2x1 es gratis y de empresa,
                // el particular lo paga el alumno y no tiene empresa.
                'matriculasAutonomas as autonomos_total' => fn ($q) => $q->autonomos(),
                'matriculasAutonomas as particulares_total' => fn ($q) => $q->particulares(),
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
            // Solo 2x1: el particular comparte tabla pero es otra cosa (paga y no tiene empresa)
            $query->whereHas('matriculasAutonomas', fn ($q) => $q->autonomos());
        } elseif ($this->filtroTipo === 'particular') {
            $query->whereHas('matriculasAutonomas', fn ($q) => $q->particulares());
        } elseif ($this->filtroTipo === 'bonificado') {
            // Aplica el rango del año filtrado a las subqueries (si filtroAno está activo)
            // para que la exclusión de no_bonificado también respete el año.
            $aplicarAnio = function ($q) {
                if ($this->filtroAno !== '') {
                    $q->where('fecha_inicio', '<=', "{$this->filtroAno}-12-31")
                      ->where('fecha_fin', '>=', "{$this->filtroAno}-01-01");
                }
            };
            $query->where(function ($q) use ($aplicarAnio) {
                $q->whereHas('gruposFormativos', $aplicarAnio)
                  ->orWhereHas('participantesBonificados', $aplicarAnio)
                  ->orWhereHas('cursosLegacy', function ($s) use ($aplicarAnio) {
                      $s->where(function ($w) {
                          $w->whereNull('origen_enriquecimiento')
                            ->orWhere('origen_enriquecimiento', '!=', 'no_bonificado');
                      });
                      $aplicarAnio($s);
                  });
            })
            // Mutuamente excluyente con el filtro "no_bonificado":
            // excluir alumnos que tengan algún curso legacy marcado no_bonificado en el año.
            ->whereDoesntHave('cursosLegacy', function ($q) use ($aplicarAnio) {
                $q->where('origen_enriquecimiento', 'no_bonificado');
                $aplicarAnio($q);
            });
        } elseif ($this->filtroTipo === 'no_bonificado') {
            $query->whereHas('cursosLegacy', fn ($q) => $q->where('origen_enriquecimiento', 'no_bonificado'));
        }

        // Filtro por año / rango de fechas — busca en cualquiera de las 4 fuentes de cursos.
        // Captura cursos cuyo PERIODO toca el rango (no solo los que empiezan en él),
        // para incluir cursos que cruzan años (ej. 2025-12 → 2026-01).
        if ($this->filtroAno || $this->filtroDesde || $this->filtroHasta) {
            $query->where(function ($q) {
                $aplicarRango = function ($sub) {
                    if ($this->filtroAno) {
                        $sub->where('fecha_inicio', '<=', "{$this->filtroAno}-12-31")
                            ->where('fecha_fin', '>=', "{$this->filtroAno}-01-01");
                    }
                    if ($this->filtroDesde) {
                        $sub->where('fecha_fin', '>=', $this->filtroDesde);
                    }
                    if ($this->filtroHasta) {
                        $sub->where('fecha_inicio', '<=', $this->filtroHasta);
                    }
                };

                $q->whereHas('gruposFormativos', fn ($s) => $aplicarRango($s))
                  ->orWhereHas('participantesBonificados', fn ($s) => $aplicarRango($s))
                  ->orWhereHas('matriculasAutonomas', fn ($s) => $aplicarRango($s))
                  ->orWhereHas('cursosLegacy', fn ($s) => $aplicarRango($s));
            });
        }

        $alumnos = $query
            ->orderBy('apellido1')
            ->orderBy('nombre')
            ->paginate(25);

        $anoEfectivo = $this->filtroAno !== '' ? (int) $this->filtroAno : (int) date('Y');
        $alumnos->getCollection()->each(function ($a) use ($anoEfectivo) {
            $a->cursoReciente = $this->resolverCursoReciente($a, $anoEfectivo);
        });

        $gruposPorAnio = null;
        $autonomosPorAnio = null;
        $bonificadosPorAnio = null;
        $legacyPorAnio = null;
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

            $agruparPorAnio = fn ($coleccion) => $coleccion
                ->groupBy(fn ($r) => $r->fecha_inicio?->year ?? 0)
                ->sortKeysDesc();

            $gruposPorAnio       = $agruparPorAnio($gruposDelAlumno);
            $autonomosPorAnio    = $agruparPorAnio($autonomosDelAlumno);
            $bonificadosPorAnio  = $agruparPorAnio($bonificadosDelAlumno);
            $legacyPorAnio       = $agruparPorAnio($legacyDelAlumno);
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
            'gruposPorAnio'         => $gruposPorAnio,
            'autonomosPorAnio'      => $autonomosPorAnio,
            'bonificadosPorAnio'    => $bonificadosPorAnio,
            'legacyPorAnio'         => $legacyPorAnio,
            'accionesPorNumero'     => $accionesPorNumero ?? collect(),
            'aniosDisponibles'      => $aniosDisponibles,
            'anoActual'             => (int) date('Y'),
        ])->layout('layouts.app', ['title' => 'Alumnos - WebCurso']);
    }
}
