<?php

namespace App\Livewire\Webcurso;

use App\Models\Alumno;
use App\Models\EncuestaCalidad;
use App\Services\Webcurso\CursoSiguienteService;
use App\Services\Webcurso\EncuestaCalidadService;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public string $filtroTutor        = '';   // tutor_label (David Guerra | Álvaro Pino / Raquel García)
    public string $filtroEmpresa      = '';   // cif
    public string $filtroDesde        = '';
    public string $filtroHasta        = '';
    public string $search             = '';
    public bool   $soloObservaciones  = false;
    public bool   $soloResenaAutorizada = false; // alumnos que autorizan publicar su reseña
    public string $orden              = 'desc'; // sentido del orden: desc | asc
    public string $ordenarPor         = 'satisfaccion'; // alumno | curso | fecha | satisfaccion (cabecera del listado)
    public int    $perPage            = 25;

    // Pestañas: resumen (Form + aula, comparables) | cuestionario (íntegro, solo aula) | oportunidades
    public string $pestana            = 'resumen';
    public string $filtroOrigen       = '';   // '' | form | aula
    public string $filtroOportunidad  = 'abiertas'; // abiertas | todas | <estado>
    public string $filtroResenas      = '';   // '' | con_comentario | sin_comentario (pestaña Reseñas)
    public string $filtroSaldo        = '';   // '' | cubre (saldo >= precio) | con_saldo | sin_saldo

    /** Orígenes de cada vía de entrada (columna `origen`). */
    public const ORIGENES = [
        'form' => ['import', 'power_automate'],
        'aula' => ['moodle_plugin'],
    ];

    // Tabla de estadísticas por curso
    public string $ordenCurso   = 'media_desc'; // media_desc | media_asc | respuestas | nombre
    public bool   $minRespuestas = true;        // solo cursos con >= 3 respuestas

    // Modal historial de cursos del alumno
    public bool    $mostrarHistorial = false;
    public ?string $historialNombre  = null;
    public array   $historialCursos  = [];

    // Mensaje transitorio del botón "Buscar en Moodle"
    public ?string $mensajeMoodle = null;
    public string  $mensajeMoodleTipo = 'ok'; // ok | warn

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
        'soloResenaAutorizada' => ['except' => false],
        'orden'              => ['except' => 'desc'],
        'perPage'            => ['except' => 25],
        'pestana'            => ['except' => 'resumen'],
        'filtroOrigen'       => ['except' => ''],
        'filtroOportunidad'  => ['except' => 'abiertas'],
        'filtroSaldo'        => ['except' => ''],
        'ordenarPor'         => ['except' => 'satisfaccion'],
        'filtroResenas'      => ['except' => ''],
    ];

    public function updatingFiltroSaldo(): void        { $this->resetPage('opPage'); }

    public function updatingFiltroOrigen(): void       { $this->resetPage(); $this->resetPage('opPage'); }
    public function updatingFiltroOportunidad(): void  { $this->resetPage('opPage'); }
    public function updatingPestana(): void            { $this->resetPage(); $this->resetPage('opPage'); $this->resetPage('resPage'); }
    public function updatingFiltroResenas(): void      { $this->resetPage('resPage'); }

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
    public function updatingSoloResenaAutorizada(): void { $this->resetPage(); $this->resetPage('opPage'); }
    public function updatingOrden(): void              { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset([
            'filtroSatisfaccion', 'filtroCurso', 'filtroTipoCurso', 'filtroTutor',
            'filtroEmpresa', 'filtroDesde', 'filtroHasta', 'search', 'soloObservaciones', 'orden',
            'filtroOrigen', 'filtroOportunidad', 'filtroSaldo', 'ordenarPor', 'soloResenaAutorizada', 'filtroResenas',
        ]);
        $this->filtroAno = '2026';
        $this->resetPage();
    }

    /** Atajos desde los KPIs / tabla por curso. */
    public function verPromotores(): void { $this->filtroSatisfaccion = '4';      $this->ordenarPor = 'satisfaccion'; $this->orden = 'desc'; $this->soloObservaciones = false; $this->resetPage(); }
    public function verDetractores(): void { $this->filtroSatisfaccion = 'menos3'; $this->ordenarPor = 'satisfaccion'; $this->orden = 'asc';  $this->resetPage(); }

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

    /**
     * Botón por fila: resuelve el curso de UNA encuesta contra el índice de
     * matrículas de Moodle (`moodle_matricula_index`). Fallback cuando el Panel no
     * encontró curso. Requiere que el snapshot se haya ejecutado.
     */
    public function resolverEnMoodle(int $encuestaId): void
    {
        $this->mensajeMoodle = null;

        if (!config('encuesta_calidad.moodle_resolucion_enabled', true)) {
            $this->mensajeMoodle = 'La resolución vía Moodle está desactivada.';
            $this->mensajeMoodleTipo = 'warn';
            return;
        }

        $e = EncuestaCalidad::find($encuestaId);
        if (!$e) {
            return;
        }

        if (\App\Models\MoodleMatriculaIndex::query()->doesntExist()) {
            $this->mensajeMoodle = 'El índice de Moodle está vacío. Ejecuta antes: php artisan encuestas-calidad:snapshot-moodle';
            $this->mensajeMoodleTipo = 'warn';
            return;
        }

        $cand = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
            $e->alumno_email,
            $e->fecha_cumplimentacion,
            $e->denominacion_accion,
        );

        if ($cand) {
            $e->forceFill($cand)->save();
            $this->mensajeMoodle = "Curso resuelto desde Moodle: {$cand['curso_resuelto']}";
            $this->mensajeMoodleTipo = 'ok';
        } else {
            $e->forceFill(['curso_origen' => 'moodle_sin_match'])->save();
            $this->mensajeMoodle = 'No se encontró el curso en Moodle (matrícula eliminada o alumno no presente).';
            $this->mensajeMoodleTipo = 'warn';
        }
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
            ->when($this->filtroTutor !== '', fn ($q) => $q->where('tutor_label', $this->filtroTutor))
            ->when($this->filtroEmpresa !== '', fn ($q) => $q->where('cif_empresa', 'like', "%{$this->filtroEmpresa}%"))
            ->when(isset(self::ORIGENES[$this->filtroOrigen]), fn ($q) => $q->whereIn('origen', self::ORIGENES[$this->filtroOrigen]))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('alumno_nombre', 'like', "%{$this->search}%")
                        ->orWhere('alumno_email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->soloObservaciones, fn ($q) => $q->whereNotNull('observaciones')->where('observaciones', '!=', ''))
            ->when($this->soloResenaAutorizada, fn ($q) => $q->resenaAutorizada());
    }

    /** Query con TODOS los filtros + orden aplicados (sin paginar). Reutilizada por listado y export. */
    protected function filteredQuery()
    {
        $dir = $this->orden === 'asc' ? 'asc' : 'desc';

        return $this->baseQuery()
            ->when($this->filtroSatisfaccion === '4', fn ($q) => $q->where('satisfaccion_general', 4))
            ->when($this->filtroSatisfaccion === '3mas', fn ($q) => $q->where('satisfaccion_general', '>=', 3))
            ->when($this->filtroSatisfaccion === 'menos3', fn ($q) => $q->whereNotNull('satisfaccion_general')->where('satisfaccion_general', '<', 3))
            ->tap(function ($q) use ($dir) {
                // Columna elegida en la cabecera de la tabla; los vacíos siempre al final
                $col = self::COLUMNAS_ORDEN[$this->ordenarPor] ?? 'satisfaccion_general';
                $q->orderByRaw("{$col} IS NULL")->orderBy($col, $dir);
            })
            ->when($this->ordenarPor !== 'fecha', fn ($q) => $q->orderByDesc('fecha_cumplimentacion'))
            ->orderByDesc('id');
    }

    /** Columnas ordenables desde la cabecera del listado → columna de la BD. */
    public const COLUMNAS_ORDEN = [
        'alumno'       => 'alumno_nombre',
        'curso'        => 'curso_resuelto',
        'fecha'        => 'fecha_cumplimentacion',
        'satisfaccion' => 'satisfaccion_general',
    ];

    /**
     * Clic en una cabecera: si ya era la columna activa, invierte el sentido; si
     * no, la activa con su sentido natural (fecha y nota: de mayor a menor;
     * alumno y curso: A→Z).
     */
    public function ordenarPorColumna(string $columna): void
    {
        if (!isset(self::COLUMNAS_ORDEN[$columna])) {
            return;
        }

        if ($this->ordenarPor === $columna) {
            $this->orden = $this->orden === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->orden = in_array($columna, ['fecha', 'satisfaccion'], true) ? 'desc' : 'asc';
        }
        $this->resetPage();
    }

    /** El desplegable "Nota: mayor → menor" sigue ordenando por satisfacción. */
    public function updatedOrden(): void
    {
        $this->ordenarPor = 'satisfaccion';
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

        $cabeceras = ['Nombre', 'Email', 'Teléfono', 'Curso', 'Tipo', 'Acción/Grupo', 'Fecha encuesta', 'Satisfacción (1-4)', 'Observaciones', 'Origen', 'Autoriza publicar'];
        $sheet->fromArray($cabeceras, null, 'A1');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $fila = 2;
        foreach ($rows as $e) {
            // Solo identificadores numéricos reales (el Form a veces trae texto en numero_grupo).
            $accionGrupo = is_numeric($e->numero_accion)
                ? ($e->numero_accion . (is_numeric($e->numero_grupo) ? '/' . $e->numero_grupo : ''))
                : '';
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
                in_array($e->origen, self::ORIGENES['aula'], true) ? 'Cuestionario del aula' : 'Form antiguo',
                $e->resena_autorizada ? 'Sí · ' . $e->resena_nombre_publico : 'No',
            ], null, 'A' . $fila);
            $fila++;
        }

        foreach (range('A', 'K') as $col) {
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
            // "¿Lo recomendaría?" solo existe en el cuestionario del aula: el
            // denominador son las que lo contestaron, nunca el total.
            'rec_n'      => (clone $base)->conRecomendacion()->count(),
            'rec_si'     => (clone $base)->where('sino_recomendaria', 1)->count(),
            'aula'       => (clone $base)->whereIn('origen', self::ORIGENES['aula'])->count(),
        ];
    }

    // ─── Pestaña "Cuestionario completo" ──────────────────────────────────────

    /**
     * Media y distribución de CADA pregunta del cuestionario íntegro (solo las
     * respuestas del aula). Los Sí/No se dan en porcentaje, nunca se promedian.
     */
    protected function getCuestionarioCompleto(): array
    {
        $base = $this->baseQuery()->cuestionarioCompleto();
        $catalogo = config('encuesta_calidad.cuestionario_completo', []);

        $q = (clone $base)->toBase();
        $q->selectRaw('COUNT(*) as total');
        foreach ($catalogo as $bloque) {
            foreach ($bloque['preguntas'] as $p) {
                $c = $p['col'];
                if ($p['tipo'] === 'sino') {
                    $q->selectRaw("SUM(CASE WHEN {$c} = 1 THEN 1 ELSE 0 END) as si_{$c}");
                    $q->selectRaw("SUM(CASE WHEN {$c} = 2 THEN 1 ELSE 0 END) as no_{$c}");
                } else {
                    $q->selectRaw("AVG({$c}) as a_{$c}");
                    $q->selectRaw("COUNT({$c}) as c_{$c}");
                    foreach ([1, 2, 3, 4] as $n) {
                        $q->selectRaw("SUM(CASE WHEN {$c} = {$n} THEN 1 ELSE 0 END) as n{$n}_{$c}");
                    }
                }
            }
        }
        $r = (array) ($q->first() ?? []);

        $bloques = [];
        foreach ($catalogo as $bloque) {
            $preguntas = [];
            $medias = [];
            foreach ($bloque['preguntas'] as $p) {
                $c = $p['col'];
                if ($p['tipo'] === 'sino') {
                    $si = (int) ($r["si_{$c}"] ?? 0);
                    $no = (int) ($r["no_{$c}"] ?? 0);
                    $n = $si + $no;
                    $preguntas[] = $p + ['n' => $n, 'si' => $si, 'no' => $no, 'pct_si' => $n ? round($si * 100 / $n, 1) : null];
                } else {
                    $n = (int) ($r["c_{$c}"] ?? 0);
                    $media = $n ? round((float) $r["a_{$c}"], 2) : null;
                    if ($media !== null) {
                        $medias[] = $media;
                    }
                    $dist = [];
                    foreach ([1, 2, 3, 4] as $k) {
                        $dist[$k] = (int) ($r["n{$k}_{$c}"] ?? 0);
                    }
                    $preguntas[] = $p + ['n' => $n, 'media' => $media, 'dist' => $dist];
                }
            }
            $bloques[] = [
                'bloque'    => $bloque['bloque'],
                'media'     => $medias ? round(array_sum($medias) / count($medias), 2) : null,
                'preguntas' => $preguntas,
            ];
        }

        return ['total' => (int) ($r['total'] ?? 0), 'bloques' => $bloques];
    }

    /**
     * Observaciones escritas por los alumnos en el cuestionario del aula
     * (apartado 11). Primero las de peor nota: son las que hay que leer.
     */
    // ─── Pestaña "Reseñas" (autorizan publicar) ───────────────────────────────

    /** Encuestas cuyo alumno autorizó publicar su reseña, la más reciente primero. */
    protected function resenasQuery()
    {
        return $this->baseQuery()
            ->resenaAutorizada()
            ->when($this->filtroSatisfaccion === '4', fn ($q) => $q->where('satisfaccion_general', 4))
            ->when($this->filtroSatisfaccion === '3mas', fn ($q) => $q->where('satisfaccion_general', '>=', 3))
            ->when($this->filtroSatisfaccion === 'menos3', fn ($q) => $q->whereNotNull('satisfaccion_general')->where('satisfaccion_general', '<', 3))
            ->when($this->filtroResenas === 'con_comentario', fn ($q) => $q->conObservacion())
            ->when($this->filtroResenas === 'sin_comentario', fn ($q) => $q->where(fn ($w) => $w->whereNull('observaciones')->orWhere('observaciones', '')))
            ->orderByDesc('resena_consentimiento_en')
            ->orderByDesc('id');
    }

    /** Excel de las reseñas autorizadas (para cuando se publiquen, p. ej. en Trustpilot). */
    public function exportarResenas()
    {
        $rows = $this->resenasQuery()->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reseñas');

        $cabeceras = ['Nombre público', 'Nota (1-4)', '¿Recomendaría?', 'Comentario', 'Curso', 'Acción/Grupo', 'Alumno', 'Email', 'Fecha encuesta', 'Autorizó', 'Versión del texto'];
        $sheet->fromArray($cabeceras, null, 'A1');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $fila = 2;
        foreach ($rows as $e) {
            $sheet->fromArray([
                $e->resena_nombre_publico,
                $e->satisfaccion_general,
                match ($e->sino_recomendaria) { 1 => 'Sí', 2 => 'No', default => '' },
                $e->observaciones,
                $e->curso_resuelto ?: $e->denominacion_accion,
                is_numeric($e->numero_accion) ? $e->numero_accion . ($e->numero_grupo ? '/' . $e->numero_grupo : '') : '',
                $e->alumno_nombre,
                $e->alumno_email,
                $e->fecha_cumplimentacion?->format('d/m/Y'),
                $e->resena_consentimiento_en?->timezone('Europe/Madrid')->format('d/m/Y H:i'),
                $e->resena_consentimiento_version,
            ], null, 'A' . $fila);
            $fila++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $nombre = 'resenas-autorizadas-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    protected function getObservacionesAula()
    {
        return $this->baseQuery()
            ->cuestionarioCompleto()
            ->conObservacion()
            ->orderByRaw('satisfaccion_general IS NULL')
            ->orderBy('satisfaccion_general')
            ->orderByDesc('fecha_cumplimentacion')
            ->paginate(10, ['*'], 'obsPage');
    }

    // ─── Pestaña "Oportunidades" (curso siguiente) ────────────────────────────

    /** Encuestas candidatas según el filtro de estado de la oportunidad. */
    protected function oportunidadesQuery()
    {
        $q = $this->baseQuery();

        return match ($this->filtroOportunidad) {
            'todas'    => $q,
            'abiertas' => $q->where(fn ($w) => $w->whereNull('oportunidad_estado')
                ->orWhereIn('oportunidad_estado', ['pendiente', 'contactado', 'interesado'])),
            'pendiente' => $q->where(fn ($w) => $w->whereNull('oportunidad_estado')->orWhere('oportunidad_estado', 'pendiente')),
            default    => $q->where('oportunidad_estado', $this->filtroOportunidad),
        };
    }

    /** Oportunidades con el filtro de saldo FUNDAE de la empresa aplicado. */
    protected function oportunidadesFiltradas()
    {
        $todas = (new CursoSiguienteService())->oportunidades($this->oportunidadesQuery());

        return match ($this->filtroSaldo) {
            'cubre'     => $todas->filter(fn ($o) => in_array(true, $o['cubre'], true))->values(),
            'con_saldo' => $todas->filter(fn ($o) => $o['saldo'] !== null && $o['saldo'] > 0)->values(),
            'sin_saldo' => $todas->filter(fn ($o) => $o['saldo'] === null || $o['saldo'] <= 0)->values(),
            default     => $todas,
        };
    }

    protected function getOportunidades(): LengthAwarePaginator
    {
        $todas = $this->oportunidadesFiltradas();

        $page = max(1, (int) $this->getPage('opPage'));
        $lastPage = max(1, (int) ceil($todas->count() / $this->perPage));
        if ($page > $lastPage) {
            $page = 1;
            $this->setPage(1, 'opPage');
        }

        return new LengthAwarePaginator(
            $todas->forPage($page, $this->perPage)->values(),
            $todas->count(),
            $this->perPage,
            $page,
            ['pageName' => 'opPage'],
        );
    }

    public function cambiarEstadoOportunidad(int $encuestaId, string $estado): void
    {
        if (!array_key_exists($estado, config('encuesta_calidad.oportunidad_estados', []))) {
            return;
        }

        EncuestaCalidad::whereKey($encuestaId)->update([
            'oportunidad_estado' => $estado,
            'oportunidad_por'    => auth()->id(),
            'oportunidad_en'     => now(),
        ]);
    }

    public function guardarNotaOportunidad(int $encuestaId, string $nota): void
    {
        EncuestaCalidad::whereKey($encuestaId)->update([
            'oportunidad_nota' => trim($nota) !== '' ? trim($nota) : null,
            'oportunidad_por'  => auth()->id(),
            'oportunidad_en'   => now(),
        ]);
    }

    /** Excel de la lista de oportunidades (para el equipo comercial / Zoho). */
    public function exportarOportunidades()
    {
        $filas = $this->oportunidadesFiltradas();
        $estados = config('encuesta_calidad.oportunidad_estados', []);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Oportunidades');

        $cabeceras = ['Alumno', 'Email', 'Teléfono', 'Empresa', 'Saldo disponible (€)', 'Curso realizado', 'Nota (1-4)', '¿Recomendaría?', 'Fecha encuesta', 'Curso sugerido', 'Precio (€)', '¿Lo cubre el saldo?', 'Enlace', 'Estado', 'Nota interna', 'Autoriza publicar'];
        $sheet->fromArray($cabeceras, null, 'A1');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);

        $fila = 2;
        foreach ($filas as $o) {
            $e = $o['encuesta'];
            $cubre = fn ($c) => match ($o['cubre'][$c->id] ?? null) { true => 'Sí', false => 'No', default => '—' };
            $sheet->fromArray([
                $e->alumno_nombre,
                $e->alumno_email ?: $e->alumno?->email,
                $e->alumno?->telefono,
                $o['empresa']?->razon_social ?: ($e->alumno?->empresa_texto ?: $e->cif_empresa),
                $o['saldo'],
                $e->curso_resuelto,
                $e->satisfaccion_general,
                match ($e->sino_recomendaria) { 1 => 'Sí', 2 => 'No', default => '' },
                $e->fecha_cumplimentacion?->format('d/m/Y'),
                $o['destinos']->pluck('titulo')->implode(' | '),
                $o['destinos']->map(fn ($c) => $c->precio !== null ? ($c->precio_estimado ? '≈ ' : '') . number_format($c->precio, 2, ',', '.') : '—')->implode(' | '),
                $o['destinos']->map($cubre)->implode(' | '),
                $o['destinos']->pluck('url')->filter()->implode(' | '),
                $estados[$e->oportunidad_estado ?? 'pendiente'] ?? 'Pendiente',
                $e->oportunidad_nota,
                $e->resena_autorizada ? 'Sí · ' . $e->resena_nombre_publico : 'No',
            ], null, 'A' . $fila);
            $fila++;
        }

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $nombre = 'oportunidades-curso-siguiente-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
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

    /**
     * Medias por bloque FUNDAE del conjunto filtrado (se usa cuando hay un curso
     * enfocado, para ver el desglose de ESE curso). Promedio de las medias de los
     * items de cada bloque. Alias "a_" para no colisionar con el cast integer.
     */
    protected function getMediasPorBloque(): array
    {
        $bloques = config('encuesta_calidad.bloques', []);

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

    /**
     * Etiquetas de tutor presentes en las encuestas (para el dropdown del filtro).
     * Es texto porque Álvaro y Raquel comparten aula: se distingue "David Guerra"
     * del bucket conjunto "Álvaro Pino / Raquel García".
     */
    protected function getTutoresDisponibles(): array
    {
        return EncuestaCalidad::query()
            ->whereNotNull('tutor_label')->where('tutor_label', '!=', '')
            ->distinct()->orderBy('tutor_label')
            ->pluck('tutor_label')->toArray();
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
        $resumen = $this->pestana === 'resumen';

        return view('livewire.webcurso.encuestas-calidad-index', [
            // Cada pestaña calcula solo lo suyo
            'encuestas'          => $resumen ? $this->getEncuestas() : null,
            'stats'              => $resumen ? $this->getEstadisticas() : null,
            'distribucion'       => $resumen ? $this->getDistribucion() : null,
            'porCurso'           => $resumen ? $this->getEstadisticasPorCurso() : [],
            // Desglose por bloques solo cuando hay un curso enfocado
            'porBloque'          => $resumen && $this->filtroCurso !== '' ? $this->getMediasPorBloque() : [],
            'cuestionario'       => $this->pestana === 'cuestionario' ? $this->getCuestionarioCompleto() : null,
            'observacionesAula'  => $this->pestana === 'cuestionario' ? $this->getObservacionesAula() : null,
            'oportunidades'      => $this->pestana === 'oportunidades' ? $this->getOportunidades() : null,
            'resenas'            => $this->pestana === 'resenas' ? $this->resenasQuery()->paginate($this->perPage, ['*'], 'resPage') : null,
            // Contador de la pestaña: autorizadas con los filtros comunes
            'nResenas'           => $this->baseQuery()->resenaAutorizada()->count(),
            'estadosOportunidad' => config('encuesta_calidad.oportunidad_estados', []),
            'aniosDisponibles'   => $this->getAniosDisponibles(),
            'cursosDisponibles'  => $this->getCursosDisponibles(),
            'tutoresDisponibles' => $this->getTutoresDisponibles(),
        ])->layout('layouts.app', ['title' => 'Encuestas de Calidad - WebCurso']);
    }
}
