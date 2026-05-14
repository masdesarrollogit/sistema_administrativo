<?php

namespace App\Livewire\Webcurso;

use App\Models\AlumnoNoApto;
use App\Models\AlumnoNotificacionLog;
use App\Models\AlumnoProgresoMoodle;
use App\Models\ReportesMoodleSettings;
use App\Services\Webcurso\AlumnoProgresoSnapshotter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ReportesMoodleIndex extends Component
{
    use WithPagination;

    public const REPORTES_DISPONIBLES = [
        'todos'             => 'Todos los alumnos (dashboard)',
        'no_conectados'     => 'No conectados',
        'inactivos'         => 'Inactivos',
        'riesgo_critico'    => 'Riesgo crítico',
        'pre_cierre'        => 'Pre-cierre (últimas 72h)',
        'apto_sin_examen'   => 'Apto sin examen (≥50 sin cuestionario)',
        'aprobados'         => 'Aprobados (curso superado)',
        'no_aptos'          => 'No aptos (curso suspendido)',
        'conectados'        => 'Conectados',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'todos')]
    public string $reporte = 'todos';

    #[Url(except: '')]
    public string $tutorId = '';

    #[Url(except: '')]
    public string $empresaId = '';

    #[Url(except: '')]
    public string $accionId = '';

    public bool $mostrarModalHistorial = false;
    public ?int $alumnoHistorialId = null;
    public string $alumnoHistorialNombre = '';

    public bool $mostrarModalNotas = false;
    public ?int $progresoNotasId = null;
    public string $alumnoNotasNombre = '';

    public ?string $mensajeFlash = null;

    public bool $notificacionesActivas = true;
    public ?string $notificacionesActualizadasInfo = null;

    public function mount(): void
    {
        $this->cargarEstadoNotificaciones();
    }

    protected function cargarEstadoNotificaciones(): void
    {
        $settings = ReportesMoodleSettings::actual()->loadMissing('usuario');
        $this->notificacionesActivas = (bool) $settings->notificaciones_activas;

        if ($settings->updated_at) {
            $autor = $settings->usuario?->name ?? 'sistema';
            $this->notificacionesActualizadasInfo = sprintf(
                'Modificado por %s · %s',
                $autor,
                $settings->updated_at->timezone('Europe/Madrid')->format('d/m/Y H:i'),
            );
        } else {
            $this->notificacionesActualizadasInfo = null;
        }
    }

    public function toggleNotificaciones(): void
    {
        $settings = ReportesMoodleSettings::actual();
        $nuevoEstado = !$settings->notificaciones_activas;
        $settings->cambiarEstado($nuevoEstado, Auth::id());

        $this->cargarEstadoNotificaciones();

        $this->mensajeFlash = $nuevoEstado
            ? '✅ Notificaciones automáticas ACTIVADAS. Los próximos crones enviarán emails.'
            : '⏸ Notificaciones automáticas DESACTIVADAS. El snapshot diario sigue corriendo.';
    }

    public function refrescarTodos(): void
    {
        try {
            $resumen = app(AlumnoProgresoSnapshotter::class)->ejecutar(fuente: 'manual');
            $this->mensajeFlash = sprintf(
                'Snapshot manual completo: %d procesados, %d escritos, %d errores.',
                $resumen['procesados'],
                $resumen['escritos'],
                $resumen['errores'],
            );
        } catch (\Throwable $e) {
            $this->mensajeFlash = 'Error al refrescar todos: ' . $e->getMessage();
        }
    }

    public function abrirHistorial(int $alumnoId, string $nombre): void
    {
        $this->alumnoHistorialId = $alumnoId;
        $this->alumnoHistorialNombre = $nombre;
        $this->mostrarModalHistorial = true;
    }

    public function cerrarHistorial(): void
    {
        $this->mostrarModalHistorial = false;
        $this->alumnoHistorialId = null;
        $this->alumnoHistorialNombre = '';
    }

    public function abrirNotas(int $progresoId, string $nombre): void
    {
        $this->progresoNotasId = $progresoId;
        $this->alumnoNotasNombre = $nombre;
        $this->mostrarModalNotas = true;
    }

    public function cerrarNotas(): void
    {
        $this->mostrarModalNotas = false;
        $this->progresoNotasId = null;
        $this->alumnoNotasNombre = '';
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'reporte', 'tutorId', 'empresaId', 'accionId']);
        $this->resetPage();
    }

    /**
     * Marca un No apto como reiniciado (el admin ya creó el nuevo grupo).
     * Detiene los emails periódicos al alumno.
     */
    public function marcarReiniciado(int $noAptoId): void
    {
        $noApto = AlumnoNoApto::find($noAptoId);
        if (!$noApto) {
            $this->mensajeFlash = 'No encontrado.';
            return;
        }

        $noApto->update([
            'reinicio_estado'              => AlumnoNoApto::ESTADO_REINICIADO,
            'reinicio_cerrado_at'          => now(),
            'reinicio_cerrado_por_user_id' => Auth::id(),
        ]);

        $this->mensajeFlash = "✅ Marcado como reiniciado: {$noApto->alumno?->nombre_completo}. No recibirá más emails de ofrecimiento.";
    }

    /**
     * Marca como rechazado (admin decide no ofrecer reinicio).
     */
    public function marcarRechazado(int $noAptoId): void
    {
        $noApto = AlumnoNoApto::find($noAptoId);
        if (!$noApto) {
            $this->mensajeFlash = 'No encontrado.';
            return;
        }

        $noApto->update([
            'reinicio_estado'              => AlumnoNoApto::ESTADO_RECHAZADO,
            'reinicio_cerrado_at'          => now(),
            'reinicio_cerrado_por_user_id' => Auth::id(),
        ]);

        $this->mensajeFlash = "Marcado como rechazado: {$noApto->alumno?->nombre_completo}.";
    }

    /**
     * Cambia el Reporte activo desde una KPI clicable.
     */
    public function abrirReporte(string $reporte): void
    {
        if (!array_key_exists($reporte, self::REPORTES_DISPONIBLES)) {
            return;
        }
        $this->reporte = $reporte;
        $this->resetPage();
    }

    public function render()
    {
        $hoy = CarbonImmutable::now('Europe/Madrid')->toDateString();

        $base = AlumnoProgresoMoodle::query()
            ->whereDate('fecha_snapshot', $hoy)
            ->whereHas('pivot.grupoFormativo', function ($q) use ($hoy) {
                $q->where('estado', 'en_curso')
                  ->whereDate('fecha_inicio', '<=', $hoy)   // ya empezó
                  ->whereDate('fecha_fin',    '>=', $hoy);  // aún no ha finalizado
            })
            ->with([
                'alumno.empresa',
                'pivot.grupoFormativo.accionFormativa',
                'pivot.grupoFormativo.tutor',
            ]);

        // Filtros sobre relaciones
        if ($this->tutorId !== '') {
            $base->whereHas('pivot.grupoFormativo', fn ($q) => $q->where('tutor_id', $this->tutorId));
        }
        if ($this->empresaId !== '') {
            $base->whereHas('alumno', fn ($q) => $q->where('empresa_id', $this->empresaId));
        }
        if ($this->accionId !== '') {
            $base->whereHas('pivot.grupoFormativo', fn ($q) => $q->where('accion_formativa_id', $this->accionId));
        }
        if ($this->search !== '') {
            $term = "%{$this->search}%";
            $base->whereHas('alumno', function ($q) use ($term) {
                $q->where('nombre', 'like', $term)
                  ->orWhere('apellido1', 'like', $term)
                  ->orWhere('apellido2', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('nif', 'like', $term);
            });
        }

        // KPIs sobre el conjunto antes del filtro por Reporte
        $kpis = (clone $base)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN nunca_entro_curso = 1 THEN 1 ELSE 0 END) as no_conectados,
                SUM(CASE WHEN inactivo = 1 THEN 1 ELSE 0 END) as inactivos,
                SUM(CASE WHEN riesgo_critico = 1 THEN 1 ELSE 0 END) as riesgo_critico,
                SUM(CASE WHEN pre_cierre = 1 THEN 1 ELSE 0 END) as pre_cierre,
                SUM(CASE WHEN apto_sin_examen = 1 THEN 1 ELSE 0 END) as apto_sin_examen,
                SUM(CASE WHEN aprobado = 1 THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN nunca_entro_curso = 0 AND inactivo = 0 AND riesgo_critico = 0 AND pre_cierre = 0 AND apto_sin_examen = 0 AND aprobado = 0 THEN 1 ELSE 0 END) as conectados
            ')
            ->first();

        $totalAlumnos = (int) ($kpis->total ?? 0);
        $noConectados = (int) ($kpis->no_conectados ?? 0);
        $inactivos = (int) ($kpis->inactivos ?? 0);
        $riesgoCritico = (int) ($kpis->riesgo_critico ?? 0);
        $preCierre = (int) ($kpis->pre_cierre ?? 0);
        $aptoSinExamen = (int) ($kpis->apto_sin_examen ?? 0);
        $aprobados = (int) ($kpis->aprobados ?? 0);
        $conectados = (int) ($kpis->conectados ?? 0);
        $enRiesgo = $noConectados + $inactivos + $riesgoCritico + $preCierre;
        $pctRiesgo = $totalAlumnos > 0 ? round(($enRiesgo / $totalAlumnos) * 100, 1) : 0.0;

        // Filtro por Reporte seleccionado
        switch ($this->reporte) {
            case 'no_conectados':
                $base->where('nunca_entro_curso', true);
                break;
            case 'inactivos':
                $base->where('inactivo', true);
                break;
            case 'riesgo_critico':
                $base->where('riesgo_critico', true);
                break;
            case 'pre_cierre':
                $base->where('pre_cierre', true);
                break;
            case 'apto_sin_examen':
                $base->where('apto_sin_examen', true);
                break;
            case 'aprobados':
                $base->where('aprobado', true);
                break;
            case 'conectados':
                $base->where('nunca_entro_curso', false)
                     ->where('inactivo', false)
                     ->where('riesgo_critico', false)
                     ->where('pre_cierre', false)
                     ->where('apto_sin_examen', false)
                     ->where('aprobado', false);
                break;
            // 'todos' => sin filtro
        }

        $snapshots = $base->orderByDesc('nunca_entro_sitio')
            ->orderByDesc('nunca_entro_curso')
            ->orderByDesc('inactivo')
            ->paginate(25);

        // Conteo de notificaciones enviadas por (alumno, grupo) — Reportes 1, 2, 3, 4
        $alumnoIds = $snapshots->pluck('alumno_id')->all();
        $notifCounts = AlumnoNotificacionLog::query()
            ->whereIn('tipo', [
                AlumnoNotificacionLog::TIPO_ALUMNO_NO_CONECTADO,
                AlumnoNotificacionLog::TIPO_ALUMNO_INACTIVO,
                AlumnoNotificacionLog::TIPO_ALUMNO_RIESGO_CRITICO,
                AlumnoNotificacionLog::TIPO_ALUMNO_PRE_CIERRE,
                AlumnoNotificacionLog::TIPO_ALUMNO_APTO_SIN_EXAMEN,
                AlumnoNotificacionLog::TIPO_ALUMNO_APTO,
            ])
            ->where('exitoso', true)
            ->whereIn('alumno_id', $alumnoIds)
            ->selectRaw('alumno_id, grupo_formativo_id, COUNT(*) as total')
            ->groupBy('alumno_id', 'grupo_formativo_id')
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->alumno_id}:{$r->grupo_formativo_id}" => (int) $r->total])
            ->all();

        // Filtros disponibles (tutor, empresa, acción) limitados al universo del día
        $tutoresOpciones = \App\Models\Tutor::query()
            ->whereIn('id', \App\Models\GrupoFormativo::query()
                ->where('estado', 'en_curso')
                ->where(function ($q) {
                    $q->whereYear('fecha_inicio', 2026)->orWhereYear('fecha_fin', 2026);
                })
                ->pluck('tutor_id'))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellido1']);

        $empresasOpciones = \App\Models\Empresa::query()
            ->whereIn('id', $snapshots->pluck('alumno.empresa_id')->filter()->unique())
            ->orderBy('razon_social')
            ->get(['id', 'razon_social']);

        $accionesOpciones = \App\Models\AccionFormativa::query()
            ->whereIn('id', \App\Models\GrupoFormativo::query()
                ->where('estado', 'en_curso')
                ->where(function ($q) {
                    $q->whereYear('fecha_inicio', 2026)->orWhereYear('fecha_fin', 2026);
                })
                ->pluck('accion_formativa_id'))
            ->orderBy('denominacion')
            ->get(['id', 'denominacion', 'numero_accion']);

        $historialNotificaciones = collect();
        if ($this->mostrarModalHistorial && $this->alumnoHistorialId) {
            $historialNotificaciones = AlumnoNotificacionLog::query()
                ->where('alumno_id', $this->alumnoHistorialId)
                ->orderByDesc('enviado_at')
                ->limit(50)
                ->get();
        }

        // Listado de No aptos (solo cuando el filtro está en 'no_aptos').
        $noAptosListado = collect();
        if ($this->reporte === 'no_aptos') {
            $q = AlumnoNoApto::query()
                ->with(['alumno.empresa', 'grupoFormativo.accionFormativa', 'grupoFormativo.tutor', 'ofrecimientos', 'cerradoPor']);

            if ($this->tutorId !== '') {
                $q->whereHas('grupoFormativo', fn ($qq) => $qq->where('tutor_id', $this->tutorId));
            }
            if ($this->empresaId !== '') {
                $q->whereHas('alumno', fn ($qq) => $qq->where('empresa_id', $this->empresaId));
            }
            if ($this->accionId !== '') {
                $q->whereHas('grupoFormativo', fn ($qq) => $qq->where('accion_formativa_id', $this->accionId));
            }
            if ($this->search !== '') {
                $term = "%{$this->search}%";
                $q->whereHas('alumno', function ($qq) use ($term) {
                    $qq->where('nombre', 'like', $term)
                        ->orWhere('apellido1', 'like', $term)
                        ->orWhere('apellido2', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('nif', 'like', $term);
                });
            }

            $noAptosListado = $q->orderByDesc('fecha_fin_curso')->get();
        }

        $progresoNotas = null;
        if ($this->mostrarModalNotas && $this->progresoNotasId) {
            $progresoNotas = AlumnoProgresoMoodle::with([
                'calificaciones' => fn ($q) => $q->orderByDesc('is_course_total')->orderByDesc('is_final_quiz')->orderBy('id'),
            ])->find($this->progresoNotasId);
        }

        return view('livewire.webcurso.reportes-moodle-index', [
            'snapshots'              => $snapshots,
            'kpiTotal'               => $totalAlumnos,
            'kpiNoConectados'        => $noConectados,
            'kpiInactivos'           => $inactivos,
            'kpiRiesgoCritico'       => $riesgoCritico,
            'kpiPreCierre'           => $preCierre,
            'kpiAptoSinExamen'       => $aptoSinExamen,
            'kpiAprobados'           => $aprobados,
            'kpiNoAptos'             => AlumnoNoApto::count(),
            'noAptosListado'         => $noAptosListado,
            'kpiConectados'          => $conectados,
            'kpiPctRiesgo'           => $pctRiesgo,
            'notifCounts'            => $notifCounts,
            'tutoresOpciones'        => $tutoresOpciones,
            'empresasOpciones'       => $empresasOpciones,
            'accionesOpciones'       => $accionesOpciones,
            'historialNotificaciones' => $historialNotificaciones,
            'progresoNotas'          => $progresoNotas,
            'reportesDisponibles'    => self::REPORTES_DISPONIBLES,
        ])->layout('layouts.app', ['title' => 'Reportes Moodle']);
    }
}
