<?php

namespace App\Livewire\Webcurso;

use App\Models\AccionFormativa;
use App\Models\CursoSiguiente;
use App\Models\EncuestaCalidad;
use App\Models\MoodleCurso;
use App\Services\Webcurso\CursoSiguienteService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Configuración de "si hizo X, ofrecer Y". Los cursos de origen son los que
 * aparecen en las encuestas de calidad; los destinos salen del catálogo de
 * webcurso.es (`moodle_cursos`). El botón "Proponer" sugiere parejas por nivel
 * en el nombre, que quedan inactivas hasta que la usuaria las confirma.
 */
class CursosSiguientesIndex extends Component
{
    use WithPagination;

    public string  $search            = '';
    public string  $filtro            = '';   // '' | sin_siguiente | por_confirmar | con_siguiente
    public ?string $editandoClave     = null;
    public string  $buscarCatalogo    = '';
    public ?string $mensaje           = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filtro' => ['except' => ''],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFiltro(): void { $this->resetPage(); }

    public function proponer(CursoSiguienteService $service): void
    {
        $n = $service->sugerir();
        $this->mensaje = $n > 0
            ? "Se han propuesto {$n} cursos siguientes. Revísalos y pulsa «Confirmar» en los que sean correctos."
            : 'No hay propuestas nuevas: o ya están creadas o el catálogo no tiene un nivel superior con el mismo nombre.';
    }

    public function editar(string $clave): void
    {
        $this->editandoClave = $this->editandoClave === $clave ? null : $clave;
        $this->buscarCatalogo = '';
    }

    /**
     * Añade un curso siguiente ya confirmado.
     *
     * @param string $tipo 'accion' (acción formativa FUNDAE) | 'web' (catálogo webcurso.es)
     */
    public function anadirDestino(string $tipo, int $id): void
    {
        $modelo = $tipo === 'accion' ? AccionFormativa::class : MoodleCurso::class;
        if (!$this->editandoClave || !$modelo::whereKey($id)->exists()) {
            return;
        }

        $nombre = (new CursoSiguienteService())->cursosDeEncuestas()[$this->editandoClave] ?? $this->editandoClave;
        $prioridad = (int) CursoSiguiente::where('curso_origen_clave', $this->editandoClave)->max('prioridad') + 1;
        $col = $tipo === 'accion' ? 'accion_formativa_id' : 'moodle_curso_id';

        CursoSiguiente::updateOrCreate(
            ['curso_origen_clave' => $this->editandoClave, $col => $id],
            [
                'curso_origen_nombre' => $nombre,
                'prioridad'           => $prioridad,
                'activo'              => true,
                'confirmado_por'      => auth()->id(),
                'confirmado_en'       => now(),
            ],
        );
        $this->buscarCatalogo = '';
    }

    public function confirmar(int $id): void
    {
        CursoSiguiente::whereKey($id)->update([
            'activo' => true, 'confirmado_por' => auth()->id(), 'confirmado_en' => now(),
        ]);
    }

    public function desactivar(int $id): void
    {
        CursoSiguiente::whereKey($id)->update(['activo' => false]);
    }

    public function quitar(int $id): void
    {
        CursoSiguiente::whereKey($id)->delete();
    }

    /** Enlace a la ficha del curso en webcurso.es, para enviárselo al alumno. */
    public function guardarUrl(int $moodleCursoId, string $url): void
    {
        $url = trim($url);
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->mensaje = 'El enlace no es una URL válida (debe empezar por https://).';
            return;
        }
        MoodleCurso::whereKey($moodleCursoId)->update(['url' => $url !== '' ? $url : null]);
    }

    /** Un registro por curso de origen (clave), con su volumen y media en encuestas. */
    protected function getOrigenes(): LengthAwarePaginator
    {
        $service = new CursoSiguienteService();
        $cursos = $service->cursosDeEncuestas();

        // Volumen y media del item 10 por clave
        $stats = [];
        EncuestaCalidad::query()
            ->whereNotNull('curso_resuelto')->where('curso_resuelto', '!=', '')
            ->groupBy('curso_resuelto')
            ->selectRaw('curso_resuelto, COUNT(*) as n, SUM(satisfaccion_general) as suma, COUNT(satisfaccion_general) as con_nota, SUM(CASE WHEN satisfaccion_general = 4 THEN 1 ELSE 0 END) as n4')
            ->get()
            ->each(function ($r) use (&$stats) {
                $k = CursoSiguiente::claveCurso($r->curso_resuelto);
                $stats[$k]['n'] = ($stats[$k]['n'] ?? 0) + (int) $r->n;
                $stats[$k]['suma'] = ($stats[$k]['suma'] ?? 0) + (int) $r->suma;
                $stats[$k]['con_nota'] = ($stats[$k]['con_nota'] ?? 0) + (int) $r->con_nota;
                $stats[$k]['n4'] = ($stats[$k]['n4'] ?? 0) + (int) $r->n4;
            });

        $siguientes = CursoSiguiente::with(['cursoDestino', 'accionFormativa'])->orderBy('prioridad')->get()->groupBy('curso_origen_clave');

        $filas = collect($cursos)->map(function ($nombre, $clave) use ($stats, $siguientes) {
            $s = $stats[$clave] ?? [];
            $sig = $siguientes[$clave] ?? collect();

            return [
                'clave'      => $clave,
                'nombre'     => $nombre,
                'encuestas'  => $s['n'] ?? 0,
                'n4'         => $s['n4'] ?? 0,
                'media'      => !empty($s['con_nota']) ? round($s['suma'] / $s['con_nota'], 2) : null,
                'siguientes' => $sig,
                'activos'    => $sig->where('activo', true)->count(),
                'pendientes' => $sig->filter(fn ($x) => !$x->activo && $x->sugerido_auto && !$x->confirmado_en)->count(),
            ];
        })->values();

        $buscar = CursoSiguiente::claveCurso($this->search);
        $filas = $filas
            ->when($buscar !== '', fn ($c) => $c->filter(fn ($f) => str_contains($f['clave'], $buscar)))
            ->when($this->filtro === 'sin_siguiente', fn ($c) => $c->filter(fn ($f) => $f['activos'] === 0 && $f['pendientes'] === 0))
            ->when($this->filtro === 'por_confirmar', fn ($c) => $c->filter(fn ($f) => $f['pendientes'] > 0))
            ->when($this->filtro === 'con_siguiente', fn ($c) => $c->filter(fn ($f) => $f['activos'] > 0))
            // Primero lo que más encuestas (y más promotores) tiene: ahí está el negocio
            ->sortByDesc(fn ($f) => [$f['n4'], $f['encuestas']])
            ->values();

        $porPagina = 25;
        $page = max(1, (int) $this->getPage());

        return new LengthAwarePaginator($filas->forPage($page, $porPagina)->values(), $filas->count(), $porPagina, $page);
    }

    /**
     * Búsqueda en las dos fuentes de cursos: acciones formativas FUNDAE (lo que se
     * imparte hoy) y catálogo web de webcurso.es.
     *
     * @return array<int, array{tipo: string, id: int, titulo: string, detalle: string}>
     */
    protected function getResultadosCatalogo(): array
    {
        $texto = trim($this->buscarCatalogo);
        if (!$this->editandoClave || mb_strlen($texto) < 2) {
            return [];
        }

        $acciones = AccionFormativa::activas()
            ->where(fn ($q) => $q->where('denominacion', 'like', "%{$texto}%")->orWhere('numero_accion', $texto))
            ->orderByDesc('numero_accion')->limit(10)->get()
            ->map(fn ($a) => ['tipo' => 'accion', 'id' => $a->id, 'titulo' => $a->denominacion_limpia,
                'detalle' => "AF {$a->numero_accion} · {$a->horas}h"]);

        $web = MoodleCurso::with('categoria:id,nombre')
            ->where('titulo', 'like', "%{$texto}%")
            ->orderBy('titulo')->limit(10)->get()
            ->map(fn ($c) => ['tipo' => 'web', 'id' => $c->id, 'titulo' => $c->titulo,
                'detalle' => "{$c->horas}h · " . number_format((float) $c->precio, 0, ',', '.') . ' € · ' . $c->categoria?->nombre]);

        return $acciones->concat($web)->all();
    }

    public function render()
    {
        return view('livewire.webcurso.cursos-siguientes-index', [
            'origenes'      => $this->getOrigenes(),
            'resultados'    => $this->getResultadosCatalogo(),
            'porConfirmar'  => CursoSiguiente::pendientesDeConfirmar()->count(),
            'totalCatalogo' => MoodleCurso::count(),
            'totalAcciones' => AccionFormativa::activas()->count(),
        ])->layout('layouts.app', ['title' => 'Cursos siguientes - WebCurso']);
    }
}
