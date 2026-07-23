@php
    $badge = function ($n) {
        return match ((int) $n) {
            4 => 'bg-green-100 text-green-700',
            3 => 'bg-blue-100 text-blue-700',
            2 => 'bg-amber-100 text-amber-700',
            1 => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-500',
        };
    };
    $etiqueta = fn ($n) => match ((int) $n) {
        4 => '4 · Excelente', 3 => '3 · Bien', 2 => '2 · Regular', 1 => '1 · Malo', default => '—',
    };
    $tipoCurso = fn ($t) => match ($t) {
        'fundae'     => ['FUNDAE', 'bg-blue-100 text-blue-700'],
        'autonomo'   => ['Autónomo', 'bg-amber-100 text-amber-700'],
        'bonificado' => ['Bonificado', 'bg-emerald-100 text-emerald-700'],
        'legacy'     => ['Legacy', 'bg-violet-100 text-violet-700'],
        'moodle'     => ['Moodle', 'bg-sky-100 text-sky-700'],
        default      => [null, ''],
    };
@endphp

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">⭐ Encuestas de Calidad</h1>
            <p class="text-sm text-gray-500 mt-1">Cuestionario de evaluación de la calidad (FUNDAE). Grado de satisfacción general: 1 = peor · 4 = excelente.</p>
        </div>

        {{-- ─── KPIs ─── --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</div>
                <div class="text-xs text-gray-500 mt-1">Respuestas</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
                <div class="text-2xl font-bold text-indigo-600">{{ $stats['media'] !== null ? number_format($stats['media'], 2) : '—' }}<span class="text-sm text-gray-400">/4</span></div>
                <div class="text-xs text-gray-500 mt-1">Media satisfacción</div>
            </div>
            <button wire:click="verPromotores"
                    class="bg-green-50 rounded-xl shadow-sm p-4 text-center border border-green-100 hover:ring-2 hover:ring-green-300 transition">
                <div class="text-2xl font-bold text-green-700">{{ number_format($stats['n4']) }}</div>
                <div class="text-xs text-green-700 mt-1">🟢 Excelentes (4)</div>
            </button>
            <button wire:click="verDetractores"
                    class="bg-red-50 rounded-xl shadow-sm p-4 text-center border border-red-100 hover:ring-2 hover:ring-red-300 transition">
                <div class="text-2xl font-bold text-red-700">{{ number_format($stats['nMenos3']) }}</div>
                <div class="text-xs text-red-700 mt-1">🔴 A mejorar (&lt;3)</div>
            </button>
            <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['con_obs']) }}</div>
                <div class="text-xs text-gray-500 mt-1">💬 Con observación</div>
            </div>
        </div>

        {{-- ─── Distribución global 1-4 ─── --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Distribución de calificaciones @if($distribucion['total']) <span class="text-gray-400 font-normal">· {{ number_format($distribucion['total']) }} respuestas</span>@endif</h3>
            @if($distribucion['total'] > 0)
                <div class="flex w-full h-6 rounded-lg overflow-hidden mb-2">
                    @foreach([1=>'bg-red-400',2=>'bg-amber-400',3=>'bg-blue-400',4=>'bg-green-500'] as $n => $c)
                        @if($distribucion[$n]['pct'] > 0)
                            <div class="{{ $c }} flex items-center justify-center text-[10px] text-white font-semibold" style="width: {{ $distribucion[$n]['pct'] }}%" title="Nota {{ $n }}: {{ $distribucion[$n]['n'] }} ({{ $distribucion[$n]['pct'] }}%)">
                                {{ $distribucion[$n]['pct'] >= 6 ? $distribucion[$n]['pct'].'%' : '' }}
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                    @foreach([1=>'🔴',2=>'🟠',3=>'🔵',4=>'🟢'] as $n => $ic)
                        <span>{{ $ic }} Nota {{ $n }}: <strong>{{ number_format($distribucion[$n]['n']) }}</strong> ({{ $distribucion[$n]['pct'] }}%)</span>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-gray-400">Sin datos con los filtros actuales.</p>
            @endif
        </div>

        {{-- ─── Filtros ─── --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <select wire:model.live="filtroAno" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos los años</option>
                    @foreach($aniosDisponibles as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filtroSatisfaccion" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todas las notas</option>
                    <option value="4">🟢 Solo 4 (excelente)</option>
                    <option value="3mas">Notas 3 y 4</option>
                    <option value="menos3">🔴 Menos de 3 (a mejorar)</option>
                </select>

                {{-- Buscar por curso (curso_resuelto) con autocompletado --}}
                <input type="text" list="cursosLista" wire:model.live.debounce.300ms="filtroCurso"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="🎓 Buscar curso...">
                <datalist id="cursosLista">
                    @foreach($cursosDisponibles as $c)
                        <option value="{{ $c }}"></option>
                    @endforeach
                </datalist>

                <select wire:model.live="filtroTipoCurso" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos los tipos</option>
                    <option value="fundae">FUNDAE</option>
                    <option value="legacy">Legacy</option>
                    <option value="autonomo">Autónomo</option>
                    <option value="bonificado">Bonificado</option>
                    <option value="moodle">Moodle</option>
                </select>

                <select wire:model.live="filtroTutor" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos los tutores</option>
                    @foreach($tutoresDisponibles as $etiquetaTutor)
                        <option value="{{ $etiquetaTutor }}">{{ $etiquetaTutor }}</option>
                    @endforeach
                </select>

                <input type="text" wire:model.live.debounce.300ms="filtroEmpresa"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="🏢 CIF empresa...">

                <input type="text" wire:model.live.debounce.300ms="search"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="👤 Nombre o email...">
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 items-center">
                <label class="text-xs text-gray-500 flex flex-col gap-1">Desde
                    <input type="date" wire:model.live="filtroDesde" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </label>
                <label class="text-xs text-gray-500 flex flex-col gap-1">Hasta
                    <input type="date" wire:model.live="filtroHasta" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </label>

                <select wire:model.live="orden" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm self-end">
                    <option value="desc">Nota: mayor → menor</option>
                    <option value="asc">Nota: menor → mayor</option>
                </select>

                <button wire:click="limpiarFiltros" class="w-full px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm self-end">🗑️ Limpiar filtros</button>
            </div>

            <label class="inline-flex items-center gap-2 mt-3 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" wire:model.live="soloObservaciones" class="rounded border-gray-300 text-indigo-600">
                Solo respuestas con observación
            </label>
            @if($filtroCurso)
                <span class="ml-3 inline-flex items-center gap-1 text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full">
                    🎓 {{ $filtroCurso }} <button wire:click="$set('filtroCurso','')" class="font-bold">×</button>
                </span>
            @endif
        </div>

        {{-- ─── Estadísticas por curso ─── --}}
        <div class="bg-white rounded-xl shadow-sm mb-6 border border-gray-100">
            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-700">📊 Media por curso <span class="text-gray-400 font-normal">({{ count($porCurso) }} cursos)</span></h3>
                <div class="flex items-center gap-2">
                    <select wire:model.live="ordenCurso" class="px-2 py-1 border border-gray-300 rounded text-xs">
                        <option value="media_desc">Mejor media primero</option>
                        <option value="media_asc">Peor media primero</option>
                        <option value="respuestas">Más respuestas</option>
                        <option value="nombre">Nombre (A-Z)</option>
                    </select>
                    <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                        <input type="checkbox" wire:model.live="minRespuestas" class="rounded border-gray-300 text-indigo-600">
                        Solo ≥3 respuestas
                    </label>
                </div>
            </div>
            <div class="overflow-x-auto" style="max-height: 24rem;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Curso</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Respuestas</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Media</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Distribución 1·2·3·4</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">% Exc. (4)</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">% &lt;3</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($porCurso as $c)
                            @php
                                [$tLabel, $tClass] = $tipoCurso($c['curso_tipo']);
                                $resp = (int) $c['respuestas'];
                                $pct4 = $resp ? round($c['n4'] * 100 / $resp) : 0;
                                $pctBajo = $resp ? round(($c['n1'] + $c['n2']) * 100 / $resp) : 0;
                            @endphp
                            <tr class="hover:bg-indigo-50/40 cursor-pointer" wire:click="verCurso(@js($c['curso_resuelto']))">
                                <td class="px-4 py-2">
                                    <span class="text-sm text-gray-800">{{ $c['curso_resuelto'] }}</span>
                                    @if($tLabel)<span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $tClass }}">{{ $tLabel }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-center text-sm text-gray-600">{{ $resp }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $badge(round($c['media'])) }}">{{ number_format($c['media'], 2) }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex w-32 h-3 rounded overflow-hidden bg-gray-100" title="1:{{ $c['n1'] }} 2:{{ $c['n2'] }} 3:{{ $c['n3'] }} 4:{{ $c['n4'] }}">
                                        @foreach(['n1'=>'bg-red-400','n2'=>'bg-amber-400','n3'=>'bg-blue-400','n4'=>'bg-green-500'] as $k => $col)
                                            @if($c[$k] > 0)<div class="{{ $col }}" style="width: {{ round($c[$k]*100/$resp) }}%"></div>@endif
                                        @endforeach
                                    </div>
                                    <span class="text-[10px] text-gray-400">{{ $c['n1'] }}·{{ $c['n2'] }}·{{ $c['n3'] }}·{{ $c['n4'] }}</span>
                                </td>
                                <td class="px-4 py-2 text-center text-sm text-green-700 font-semibold">{{ $pct4 }}%</td>
                                <td class="px-4 py-2 text-center text-sm {{ $pctBajo >= 25 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ $pctBajo }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">Sin cursos con los filtros actuales{{ $minRespuestas ? ' (probá desmarcar "Solo ≥3 respuestas")' : '' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ─── Valoración por bloques FUNDAE del curso enfocado ─── --}}
        @if($filtroCurso && count($porBloque))
            <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-indigo-100">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">🧩 Valoración por bloques FUNDAE · <span class="text-indigo-700">{{ $filtroCurso }}</span></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1">
                    @foreach($porBloque as $b)
                        <div class="flex items-center gap-3 py-1 pr-2">
                            <span class="text-sm text-gray-700 w-44 flex-shrink-0 truncate">{{ $b['label'] }}</span>
                            <div class="flex-grow h-2 bg-gray-100 rounded overflow-hidden">
                                <div class="h-2 {{ $b['media'] >= 3.5 ? 'bg-green-500' : ($b['media'] >= 2.5 ? 'bg-blue-400' : 'bg-amber-400') }}" style="width: {{ $b['media'] ? round($b['media']*25) : 0 }}%"></div>
                            </div>
                            <span class="text-xs font-semibold text-gray-600 w-12 flex-shrink-0 text-right tabular-nums">{{ $b['media'] !== null ? number_format($b['media'], 2) : '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ─── Tabla ─── --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm text-gray-500">
                    @if($encuestas->total() > 0)
                        Mostrando {{ $encuestas->firstItem() }} – {{ $encuestas->lastItem() }} de {{ number_format($encuestas->total()) }}
                    @else
                        Sin resultados
                    @endif
                </span>
                <div class="flex items-center gap-2">
                    <button wire:click="exportar" wire:loading.attr="disabled" wire:target="exportar"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium disabled:opacity-50">
                        <span wire:loading.remove wire:target="exportar">⬇️ Exportar Excel</span>
                        <span wire:loading wire:target="exportar">Generando…</span>
                    </button>
                    <select wire:model.live="perPage" class="px-2 py-1 border border-gray-300 rounded text-sm">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            @if($mensajeMoodle)
                <div class="mb-3 px-4 py-2 rounded-lg text-sm {{ $mensajeMoodleTipo === 'ok' ? 'bg-sky-50 text-sky-800 border border-sky-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                    {{ $mensajeMoodle }}
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alumno</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curso</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Satisfacción</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($encuestas as $e)
                            @php
                                $esDetractorConQueja = $e->satisfaccion_general !== null && $e->satisfaccion_general < 3 && filled($e->observaciones);
                                $tel = $e->alumno?->telefono_e164;
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $esDetractorConQueja ? 'border-l-4 border-red-400 bg-red-50/40' : '' }}">
                                {{-- Alumno --}}
                                <td class="px-4 py-3 align-top">
                                    @if($e->alumno_id)
                                        <button type="button" wire:click="verHistorial({{ $e->alumno_id }})"
                                                class="text-sm font-medium text-indigo-700 hover:text-indigo-900 hover:underline text-left"
                                                title="Ver historial de cursos del alumno">
                                            {{ $e->alumno_nombre ?: '—' }}
                                            <span class="text-[10px] text-indigo-400">🗂️</span>
                                        </button>
                                    @else
                                        <div class="text-sm font-medium text-gray-900">{{ $e->alumno_nombre ?: '—' }}</div>
                                    @endif
                                    @if($e->alumno_email)
                                        <a href="mailto:{{ $e->alumno_email }}" class="text-xs text-indigo-600 hover:underline block">✉️ {{ $e->alumno_email }}</a>
                                    @endif
                                    @if($tel)
                                        <a href="tel:{{ $tel }}" class="text-xs text-gray-500 hover:underline block">📞 {{ $e->alumno->telefono }}</a>
                                    @endif
                                    @unless($e->alumno_id)
                                        <span class="text-[10px] text-gray-400">sin ficha en Panel</span>
                                    @endunless
                                </td>
                                {{-- Curso --}}
                                <td class="px-4 py-3 align-top">
                                    @php [$tLabel, $tClass] = $tipoCurso($e->curso_tipo); @endphp
                                    <div class="text-sm text-gray-800">{{ $e->curso_resuelto ?: ($e->denominacion_accion ?: '—') }}</div>
                                    <div class="flex flex-wrap items-center gap-1 mt-1">
                                        @if($tLabel)
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $tClass }}">{{ $tLabel }}</span>
                                        @endif
                                        {{-- Píldora acción/grupo FUNDAE: solo cuando son identificadores numéricos reales
                                             (el Form a veces trae texto en numero_grupo, p.ej. una categoría profesional). --}}
                                        @if(is_numeric($e->numero_accion))
                                            <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-mono font-semibold">
                                                {{ $e->numero_accion }}@if(is_numeric($e->numero_grupo))/{{ $e->numero_grupo }}@endif
                                            </span>
                                        @endif
                                    </div>
                                    @if($e->curso_fecha_inicio && $e->curso_fecha_fin)
                                        <div class="text-[11px] text-gray-400 mt-0.5">
                                            {{ $e->curso_fecha_inicio->format('d/m/y') }} – {{ $e->curso_fecha_fin->format('d/m/y') }}
                                        </div>
                                    @endif
                                    @if(filled($e->tutor_label))
                                        <div class="text-[11px] text-gray-500 mt-0.5">👤 {{ $e->tutor_label }}</div>
                                    @endif
                                    @if(blank($e->curso_resuelto) && filled($e->alumno_email))
                                        <button type="button" wire:click="resolverEnMoodle({{ $e->id }})"
                                                wire:loading.attr="disabled" wire:target="resolverEnMoodle({{ $e->id }})"
                                                class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium text-sky-700 bg-sky-50 border border-sky-200 hover:bg-sky-100 disabled:opacity-50"
                                                title="Buscar el curso en Moodle por el email del alumno">
                                            🔍 Buscar en Moodle
                                        </button>
                                        @if($e->curso_origen === 'moodle_sin_match')
                                            <span class="ml-1 text-[10px] text-gray-400">sin match en Moodle</span>
                                        @endif
                                    @endif
                                </td>
                                {{-- Fecha --}}
                                <td class="px-4 py-3 align-top whitespace-nowrap text-sm text-gray-600">
                                    {{ $e->fecha_cumplimentacion?->format('d/m/Y') ?: '—' }}
                                </td>
                                {{-- Satisfacción --}}
                                <td class="px-4 py-3 align-top text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $badge($e->satisfaccion_general) }}">
                                        {{ $etiqueta($e->satisfaccion_general) }}
                                    </span>
                                </td>
                                {{-- Observación --}}
                                <td class="px-4 py-3 align-top max-w-md">
                                    @if(filled($e->observaciones))
                                        <div class="text-sm text-gray-700 whitespace-pre-line {{ $esDetractorConQueja ? 'font-medium' : '' }}">💬 {{ $e->observaciones }}</div>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    No hay encuestas con los filtros actuales{{ $filtroAno ? " (año $filtroAno)" : '' }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-100">
                {{ $encuestas->links() }}
            </div>
        </div>
    </div>

    {{-- ─── Modal: historial de cursos del alumno ─── --}}
    @if($mostrarHistorial)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:key="modal-historial">
            <div class="absolute inset-0 bg-black/40" wire:click="cerrarHistorial"></div>
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">🗂️ Historial de cursos · {{ $historialNombre }}</h3>
                    <button wire:click="cerrarHistorial" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-5 overflow-y-auto">
                    @if(count($historialCursos) === 0)
                        <p class="text-sm text-gray-500 text-center py-8">Este alumno no tiene cursos con fechas registrados en el Panel.</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Curso</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fechas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($historialCursos as $c)
                                    @php [$tLabel, $tClass] = $tipoCurso($c['tipo']); @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-sm text-gray-800">{{ $c['nombre'] ?: '(sin nombre)' }}</td>
                                        <td class="px-3 py-2">
                                            @if($tLabel)
                                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $tClass }}">{{ $tLabel }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">{{ $c['inicio'] }} – {{ $c['fin'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
