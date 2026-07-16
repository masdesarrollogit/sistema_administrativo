<?php

namespace App\Livewire\Webcurso;

use App\Models\Alumno;
use App\Models\EncuestaCalidad;
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
    public string $filtroAccion       = '';
    public string $search             = '';
    public bool   $soloObservaciones  = false;
    public string $orden              = 'desc'; // satisfacción: desc (4 primero) | asc (1 primero)
    public int    $perPage            = 25;

    // Modal historial de cursos del alumno
    public bool    $mostrarHistorial = false;
    public ?string $historialNombre  = null;
    public array   $historialCursos  = [];

    protected $queryString = [
        'filtroAno'          => ['except' => '2026'],
        'filtroSatisfaccion' => ['except' => ''],
        'filtroAccion'       => ['except' => ''],
        'search'             => ['except' => ''],
        'soloObservaciones'  => ['except' => false],
        'orden'              => ['except' => 'desc'],
        'perPage'            => ['except' => 25],
    ];

    public function updatingFiltroAno(): void          { $this->resetPage(); }
    public function updatingFiltroSatisfaccion(): void { $this->resetPage(); }
    public function updatingFiltroAccion(): void       { $this->resetPage(); }
    public function updatingSearch(): void             { $this->resetPage(); }
    public function updatingSoloObservaciones(): void  { $this->resetPage(); }
    public function updatingOrden(): void              { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['filtroSatisfaccion', 'filtroAccion', 'search', 'soloObservaciones', 'orden']);
        $this->filtroAno = '2026';
        $this->resetPage();
    }

    /** Atajo desde los KPIs. */
    public function verPromotores(): void { $this->filtroSatisfaccion = '4';      $this->orden = 'desc'; $this->soloObservaciones = false; $this->resetPage(); }
    public function verDetractores(): void { $this->filtroSatisfaccion = 'menos3'; $this->orden = 'asc';  $this->resetPage(); }

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

    /** Aplica los filtros comunes (año, acción, búsqueda, observaciones). */
    protected function baseQuery()
    {
        return EncuestaCalidad::query()
            ->when($this->filtroAno !== '', fn ($q) => $q->whereYear('fecha_cumplimentacion', $this->filtroAno))
            ->when($this->filtroAccion !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('numero_accion', $this->filtroAccion)
                        ->orWhere('denominacion_accion', 'like', "%{$this->filtroAccion}%");
                });
            })
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
     * Ranking de cursos por nota media del item 10 (respeta año).
     * Agrupa por el CURSO RESUELTO (el nombre que traemos del historial del
     * alumno), no por el Nº Acción del formulario — que casi siempre viene vacío.
     * Requiere un mínimo de respuestas por curso para que la media sea fiable.
     */
    protected function getRanking(string $dir): array
    {
        $min = 3; // mínimo de respuestas por curso

        return EncuestaCalidad::query()
            ->whereNotNull('satisfaccion_general')
            ->whereNotNull('curso_resuelto')
            ->where('curso_resuelto', '!=', '')
            ->when($this->filtroAno !== '', fn ($q) => $q->whereYear('fecha_cumplimentacion', $this->filtroAno))
            ->groupBy('curso_resuelto')
            ->select('curso_resuelto')
            ->selectRaw('AVG(satisfaccion_general) as media')
            ->selectRaw('COUNT(*) as respuestas')
            ->havingRaw('COUNT(*) >= ?', [$min])
            ->orderBy('media', $dir)
            ->orderByDesc('respuestas')
            ->limit(5)
            ->get()
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
            'encuestas'        => $this->getEncuestas(),
            'stats'            => $this->getEstadisticas(),
            'peorValorados'    => $this->getRanking('asc'),
            'mejorValorados'   => $this->getRanking('desc'),
            'aniosDisponibles' => $this->getAniosDisponibles(),
        ])->layout('layouts.app', ['title' => 'Encuestas de Calidad - WebCurso']);
    }
}
