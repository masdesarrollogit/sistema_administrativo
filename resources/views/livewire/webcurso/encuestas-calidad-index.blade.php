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

        {{-- ─── Rankings de cursos ─── --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                <h3 class="text-sm font-semibold text-green-700 mb-3">🏆 Cursos mejor valorados {{ $filtroAno ? "($filtroAno)" : '' }}</h3>
                @forelse($mejorValorados as $r)
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                        <span class="text-sm text-gray-700 truncate pr-2">{{ $r['curso_resuelto'] }}</span>
                        <span class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-xs text-gray-400">{{ $r['respuestas'] }}</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">{{ number_format($r['media'], 2) }}</span>
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-gray-400">Sin datos suficientes (mín. 3 respuestas por curso).</p>
                @endforelse
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                <h3 class="text-sm font-semibold text-red-700 mb-3">⚠️ Cursos peor valorados {{ $filtroAno ? "($filtroAno)" : '' }}</h3>
                @forelse($peorValorados as $r)
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                        <span class="text-sm text-gray-700 truncate pr-2">{{ $r['curso_resuelto'] }}</span>
                        <span class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-xs text-gray-400">{{ $r['respuestas'] }}</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">{{ number_format($r['media'], 2) }}</span>
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-gray-400">Sin datos suficientes (mín. 3 respuestas por curso).</p>
                @endforelse
            </div>
        </div>

        {{-- ─── Filtros ─── --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
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

                <input type="text" wire:model.live.debounce.300ms="filtroAccion"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Curso / Nº acción...">

                <input type="text" wire:model.live.debounce.300ms="search"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Nombre o email...">

                <select wire:model.live="orden" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="desc">Nota: mayor → menor</option>
                    <option value="asc">Nota: menor → mayor</option>
                </select>

                <button wire:click="limpiarFiltros" class="w-full px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm">🗑️ Limpiar</button>
            </div>
            <label class="inline-flex items-center gap-2 mt-3 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" wire:model.live="soloObservaciones" class="rounded border-gray-300 text-indigo-600">
                Solo respuestas con observación
            </label>
        </div>

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
                                        @if($e->numero_accion || $e->numero_grupo)
                                            <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-mono font-semibold">
                                                {{ $e->numero_accion ?: '?' }}/{{ $e->numero_grupo ?: '?' }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($e->curso_fecha_inicio && $e->curso_fecha_fin)
                                        <div class="text-[11px] text-gray-400 mt-0.5">
                                            {{ $e->curso_fecha_inicio->format('d/m/y') }} – {{ $e->curso_fecha_fin->format('d/m/y') }}
                                        </div>
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
