<?php

namespace App\Livewire\Webcurso;

use App\Models\Alumno;
use App\Models\EncuestaCalidad;
use App\Models\Tutor;
use App\Services\Webcurso\EncuestaCalidadService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Listado accionable de las respuestas del cuestionario de calidad FUNDAE.
 *
 * Enfoque (según la usuaria): no es un panel de medias por bloque, sino dos usos
 * concretos sobre el item 10 (grado de satisfacción, 1=peor .. 4=excelente):
 *  - Promotores (nota 4, configurable) → contactarles para recomendar otro curso.
 *  - Detractores (nota < 3) → ver su queja/observación para mejorar.
 */
class EncuestasCalidadIndex extends Component
{
    use WithPagination;

    public string $filtroAno          = '2026';
    public string $filtroSatisfaccion = '';   // '' | '4' | '3mas' | 'menos3'
    public string $filtroCurso        = '';   // busca en curso_resuelto
    public string $filtroTipoCurso    = '';   // fundae | legacy | autonomo | bonificado
    public string $filtroTutor        = '';   // tutor_id
    public string $filtroEmpresa      = '';   // cif
    public string $filtroDesde        = '';
    public string $filtroHasta        = '';
    public string $search             = '';
    public bool   $soloObservaciones  = false;
    public string $orden              = 'desc'; // satisfacción: desc (4 primero) | asc (1 primero)
    public int    $perPage            = 25;

    // Tabla de estadísticas por curso
    public string $ordenCurso   = 'media_desc'; // media_desc | media_asc | respuestas | nombre
    public bool   $minRespuestas = true;        // solo cursos con >= 3 respuestas

    // Modal historial de cursos del alumno
    public bool    $mostrarHistorial = false;
    public ?string $historialNombre  = null;
    public array   $historialCursos  = [];

    protected $queryString = [
        'filtroAno'          => ['except' => '2026'],
        'filtroSatisfaccion' => ['except' => ''],
        'filtroCurso'        => ['except' => ''],
        'filtroTipoCurso'    => ['except' => ''],
        'filtroTutor'        => ['except' => ''],
        'filtroEmpresa'      => ['except' => ''],
        'filtroDesde'        => ['except' => ''],
        'filtroHasta'        => ['except' => ''],
        'search'             => ['except' => ''],
        'soloObservaciones'  => ['except' => false],
        'orden'              => ['except' => 'desc'],
        'perPage'            => ['except' => 25],
    ];

    public function updatingFiltroAno(): void          { $this->resetPage(); }
    public function updatingFiltroSatisfaccion(): void { $this->resetPage(); }
    public function updatingFiltroCurso(): void        { $this->resetPage(); }
    public function updatingFiltroTipoCurso(): void    { $this->resetPage(); }
    public function updatingFiltroTutor(): void        { $this->resetPage(); }
    public function updatingFiltroEmpresa(): void      { $this->resetPage(); }
    public function updatingFiltroDesde(): void        { $this->resetPage(); }
    public function updatingFiltroHasta(): void        { $this->resetPage(); }
    public function updatingSearch(): void             { $this->resetPage(); }
    public function updatingSoloObservaciones(): void  { $this->resetPage(); }
    public function updatingOrden(): void              { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'filtroSatisfaccion', 'filtroCurso', 'filtroTipoCurso', 'filtroTutor',
            'filtroEmpresa', 'filtroDesde', 'filtroHasta', 'search', 'soloObservaciones', 'orden',
        ]);
        $this->filtroAno = '2026';
        $this->resetPage();
    }

    /** Atajos desde los KPIs / tabla por curso. */
    public function verPromotores(): void { $this->filtroSatisfaccion = '4';      $this->orden = 'desc'; $this->soloObservaciones = false; $this->resetPage(); }
    public function verDetractores(): void { $this->filtroSatisfaccion = 'menos3'; $this->orden = 'asc';  $this->resetPage(); }

    /** Enfoca todo (KPIs, distribución, listado) a un curso concreto. */
    public function verCurso(string $curso): void { $this->filtroCurso = $curso; $this->resetPage(); }

    // ─── Modal historial de cursos del alumno ─────────────────────────────────

    public function verHistorial(int $alumnoId): void
    {
        $alumno = Alumno::find($alumnoId);
        if (!$alumno) {
            return;
        }

        $cursos = (new EncuestaCalidadService())->historialCursos($alumnoId);

        // Serializar para la vista (fechas a string)
        $this->historialCursos = array_map(fn ($c) => [
            'tipo'   => $c['curso_tipo'],
            'nombre' => $c['curso_resuelto'],
            'inicio' => optional($c['curso_fecha_inicio'])->format('d/m/Y'),
            'fin'    => optional($c['curso_fecha_fin'])->format('d/m/Y'),
            'anio'   => optional($c['curso_fecha_inicio'])->format('Y'),
        ], $cursos);

        $this->historialNombre = trim("{$alumno->nombre} {$alumno->apellido1} {$alumno->apellido2}");
        $this->mostrarHistorial = true;
    }

    public function cerrarHistorial(): void
    {
        $this->mostrarHistorial = false;
        $this->historialNombre = null;
        $this->historialCursos = [];
    }

    // ─── Query principal ──────────────────────────────────────────────────────

    /** Aplica los filtros comunes (año/rango, curso, tipo, tutor, empresa, búsqueda, observaciones). */
    protected function baseQuery()
    {
        $usaRango = $this->filtroDesde !== '' || $this->filtroHasta !== '';

        return EncuestaCalidad::query()
            // El rango de fechas tiene prioridad sobre el año (para no chocar)
            ->when(!$usaRango && $this->filtroAno !== '', fn ($q) => $q->whereYear('fecha_cumplimentacion', $this->filtroAno))
            ->when($this->filtroDesde !== '', fn ($q) => $q->whereDate('fecha_cumplimentacion', '>=', $this->filtroDesde))
            ->when($this->filtroHasta !== '', fn ($q) => $q->whereDate('fecha_cumplimentacion', '<=', $this->filtroHasta))
            ->when($this->filtroCurso !== '', fn ($q) => $q->where('curso_resuelto', 'like', "%{$this->filtroCurso}%"))
            ->when($this->filtroTipoCurso !== '', fn ($q) => $q->where('curso_tipo', $this->filtroTipoCurso))
            ->when($this->filtroTutor !== '', fn ($q) => $q->where('tutor_id', $this->filtroTutor))
            ->when($this->filtroEmpresa !== '', fn ($q) => $q->where('cif_empresa', 'like', "%{$this->filtroEmpresa}%"))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('alumno_nombre', 'like', "%{$this->search}%")
                        ->orWhere('alumno_email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->soloObservaciones, fn ($q) => $q->whereNotNull('observaciones')->where('observaciones', '!=', ''));
    }

    /** Query con TODOS los filtros + orden aplicados (sin paginar). Reutilizada por listado y export. */
    protected function filteredQuery()
    {
        $dir = $this->orden === 'asc' ? 'asc' : 'desc';

        return $this->baseQuery()
            ->when($this->filtroSatisfaccion === '4', fn ($q) => $q->where('satisfaccion_general', 4))
            ->when($this->filtroSatisfaccion === '3mas', fn ($q) => $q->where('satisfaccion_general', '>=', 3))
            ->when($this->filtroSatisfaccion === 'menos3', fn ($q) => $q->whereNotNull('satisfaccion_general')->where('satisfaccion_general', '<', 3))
            ->orderByRaw('satisfaccion_general IS NULL')  // NULLs al final
            ->orderBy('satisfaccion_general', $dir)
            ->orderByDesc('fecha_cumplimentacion');
    }

    protected function getEncuestas()
    {
        return $this->filteredQuery()
            ->with(['alumno:id,nif,email,telefono', 'grupoFormativo.tutor'])
            ->paginate($this->perPage);
    }

    /** Descarga el listado filtrado en Excel (.xlsx). */
    public function exportar()
    {
        $rows = $this->filteredQuery()->with('alumno:id,telefono')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Encuestas');

        $cabeceras = ['Nombre', 'Email', 'Teléfono', 'Curso', 'Tipo', 'Acción/Grupo', 'Fecha encuesta', 'Satisfacción (1-4)', 'Observaciones'];
        $sheet->fromArray($cabeceras, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $fila = 2;
        foreach ($rows as $e) {
            $accionGrupo = ($e->numero_accion || $e->numero_grupo) ? (($e->numero_accion ?: '?') . '/' . ($e->numero_grupo ?: '?')) : '';
            $sheet->fromArray([
                $e->alumno_nombre,
                $e->alumno_email,
                $e->alumno?->telefono,
                $e->curso_resuelto ?: $e->denominacion_accion,
                $e->curso_tipo,
                $accionGrupo,
                $e->fecha_cumplimentacion?->format('d/m/Y'),
                $e->satisfaccion_general,
                $e->observaciones,
            ], null, 'A' . $fila);
            $fila++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $nombre = 'encuestas-calidad-' . ($this->filtroAno ?: 'todos') . '-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    protected function getEstadisticas(): array
    {
        $base = $this->baseQuery();

        $total = (clone $base)->count();
        $conNota = (clone $base)->whereNotNull('satisfaccion_general');
        $n = (clone $conNota)->count();
        $media = $n > 0 ? round((clone $conNota)->avg('satisfaccion_general'), 2) : null;

        return [
            'total'      => $total,
            'con_nota'   => $n,
            'media'      => $media,
            'n4'         => (clone $base)->where('satisfaccion_general', 4)->count(),
            'n3'         => (clone $base)->where('satisfaccion_general', 3)->count(),
            'nMenos3'    => (clone $base)->whereNotNull('satisfaccion_general')->where('satisfaccion_general', '<', 3)->count(),
            'con_obs'    => (clone $base)->whereNotNull('observaciones')->where('observaciones', '!=', '')->count(),
        ];
    }

    /**
     * Estadísticas por curso: una fila por curso_resuelto con nº de respuestas,
     * media del item 10 y conteos por nota (1-4). Respeta los filtros comunes.
     */
    protected function getEstadisticasPorCurso(): array
    {
        $query = $this->baseQuery()
            ->whereNotNull('satisfaccion_general')
            ->whereNotNull('curso_resuelto')
            ->where('curso_resuelto', '!=', '')
            ->groupBy('curso_resuelto')
            ->select('curso_resuelto')
            ->selectRaw('MAX(curso_tipo) as curso_tipo')
            ->selectRaw('COUNT(*) as respuestas')
            ->selectRaw('AVG(satisfaccion_general) as media')
            ->selectRaw('SUM(CASE WHEN satisfaccion_general = 1 THEN 1 ELSE 0 END) as n1')
            ->selectRaw('SUM(CASE WHEN satisfaccion_general = 2 THEN 1 ELSE 0 END) as n2')
            ->selectRaw('SUM(CASE WHEN satisfaccion_general = 3 THEN 1 ELSE 0 END) as n3')
            ->selectRaw('SUM(CASE WHEN satisfaccion_general = 4 THEN 1 ELSE 0 END) as n4');

        if ($this->minRespuestas) {
            $query->havingRaw('COUNT(*) >= 3');
        }

        match ($this->ordenCurso) {
            'media_asc'  => $query->orderBy('media', 'asc')->orderByDesc('respuestas'),
            'respuestas' => $query->orderByDesc('respuestas')->orderByDesc('media'),
            'nombre'     => $query->orderBy('curso_resuelto'),
            default      => $query->orderByDesc('media')->orderByDesc('respuestas'),
        };

        return $query->get()->toArray();
    }

    /** Distribución global de notas 1-4 (respeta filtros comunes, NO la banda de satisfacción). */
    protected function getDistribucion(): array
    {
        $base = $this->baseQuery()->whereNotNull('satisfaccion_general');
        $total = (clone $base)->count();

        $dist = [];
        foreach ([1, 2, 3, 4] as $n) {
            $c = (clone $base)->where('satisfaccion_general', $n)->count();
            $dist[$n] = ['n' => $c, 'pct' => $total > 0 ? round($c * 100 / $total, 1) : 0];
        }
        $dist['total'] = $total;

        return $dist;
    }

    /** Media de satisfacción por tutor (solo cursos resueltos a grupo FUNDAE con tutor). */
    protected function getMediaPorTutor(): array
    {
        $filas = $this->baseQuery()
            ->whereNotNull('satisfaccion_general')
            ->whereNotNull('tutor_id')
            ->groupBy('tutor_id')
            ->select('tutor_id')
            ->selectRaw('AVG(satisfaccion_general) as media')
            ->selectRaw('COUNT(*) as respuestas')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByDesc('media')
            ->get();

        $tutores = Tutor::whereIn('id', $filas->pluck('tutor_id'))->get()->keyBy('id');

        return $filas->map(fn ($r) => [
            'tutor'      => optional($tutores->get($r->tutor_id))->nombre_completo
                ?? trim((optional($tutores->get($r->tutor_id))->nombre ?? '') . ' ' . (optional($tutores->get($r->tutor_id))->apellido1 ?? ''))
                ?: ('Tutor #' . $r->tutor_id),
            'media'      => round($r->media, 2),
            'respuestas' => $r->respuestas,
        ])->toArray();
    }

    /** Media de cada bloque FUNDAE (promedio de las medias de sus items). Respeta filtros. */
    protected function getMediasPorBloque(): array
    {
        $bloques = config('encuesta_calidad.bloques', []);

        // Media de cada item + satisfacción en un solo query. Alias con prefijo
        // "a_" para NO colisionar con las columnas casteadas a integer del modelo
        // (si no, el cast truncaría la media a entero).
        $query = $this->baseQuery();
        for ($i = 1; $i <= 19; $i++) {
            $col = 'item_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $query->selectRaw("AVG({$col}) as a_{$col}");
        }
        $query->selectRaw('AVG(satisfaccion_general) as a_satisfaccion_general');
        $medias = (array) ($query->first()?->getAttributes() ?? []);

        $out = [];
        foreach ($bloques as $bloque) {
            $vals = [];
            foreach ($bloque['items'] as $col) {
                $alias = 'a_' . $col;
                if (isset($medias[$alias]) && $medias[$alias] !== null) {
                    $vals[] = (float) $medias[$alias];
                }
            }
            $out[] = [
                'label' => $bloque['label'],
                'media' => $vals ? round(array_sum($vals) / count($vals), 2) : null,
            ];
        }

        return $out;
    }

    /** Nombres de curso distintos (para el autocompletado del filtro). */
    protected function getCursosDisponibles(): array
    {
        return EncuestaCalidad::query()
            ->whereNotNull('curso_resuelto')->where('curso_resuelto', '!=', '')
            ->distinct()->orderBy('curso_resuelto')
            ->pluck('curso_resuelto')->toArray();
    }

    /** Tutores presentes en las encuestas (para el dropdown del filtro). */
    protected function getTutoresDisponibles(): array
    {
        $ids = EncuestaCalidad::query()->whereNotNull('tutor_id')->distinct()->pluck('tutor_id');

        return Tutor::whereIn('id', $ids)->orderBy('nombre')->get()
            ->mapWithKeys(fn ($t) => [$t->id => trim("{$t->nombre} {$t->apellido1}")])
            ->toArray();
    }

    protected function getAniosDisponibles(): array
    {
        $anios = EncuestaCalidad::query()
            ->whereNotNull('fecha_cumplimentacion')
            ->selectRaw('DISTINCT ' . $this->yearExpr('fecha_cumplimentacion') . ' as y')
            ->pluck('y')
            ->filter()
            ->map(fn ($y) => (int) $y);

        // Asegurar 2026 y el año actual como opciones seleccionables
        return $anios
            ->push(2026)
            ->push((int) date('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
    }

    /** YEAR() portable (MySQL y SQLite en tests). */
    protected function yearExpr(string $col): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', {$col}) AS INTEGER)"
            : "YEAR({$col})";
    }

    public function render()
    {
        return view('livewire.webcurso.encuestas-calidad-index', [
            'encuestas'          => $this->getEncuestas(),
            'stats'              => $this->getEstadisticas(),
            'distribucion'       => $this->getDistribucion(),
            'porCurso'           => $this->getEstadisticasPorCurso(),
            'porTutor'           => $this->getMediaPorTutor(),
            'porBloque'          => $this->getMediasPorBloque(),
            'aniosDisponibles'   => $this->getAniosDisponibles(),
            'cursosDisponibles'  => $this->getCursosDisponibles(),
            'tutoresDisponibles' => $this->getTutoresDisponibles(),
        ])->layout('layouts.app', ['title' => 'Encuestas de Calidad - WebCurso']);
    }
}
