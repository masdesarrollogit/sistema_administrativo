<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Cabecera --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Contratos de Encomienda</h1>
                <p class="text-sm text-gray-500">Contratos firmados online en el sistema externo y su estado en el Panel.</p>
            </div>
            <button type="button" wire:click="sincronizarAhora" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="sincronizarAhora">↻ Sincronizar ahora</span>
                <span wire:loading wire:target="sincronizarAhora">Sincronizando…</span>
            </button>
        </div>

        {{-- Mensaje flash --}}
        @if(session('message-encomienda'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-2 mb-4 text-sm">{{ session('message-encomienda') }}</div>
        @endif

        {{-- KPIs --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <p class="text-xs text-gray-500 uppercase">Total contratos</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white rounded-lg border border-amber-200 p-4">
                <p class="text-xs text-amber-600 uppercase">Pendientes de empresa</p>
                <p class="text-2xl font-bold text-amber-700">{{ $stats['pendiente_empresa'] }}</p>
            </div>
            <div class="bg-white rounded-lg border border-green-200 p-4">
                <p class="text-xs text-green-600 uppercase">Candidato creado</p>
                <p class="text-2xl font-bold text-green-700">{{ $stats['candidato_creado'] }}</p>
            </div>
        </div>

        {{-- Resumen de sincronización --}}
        @if($resumenSync)
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6 relative">
                <button wire:click="limpiarResumen" class="absolute top-2 right-3 text-indigo-400 hover:text-indigo-700 text-lg leading-none">&times;</button>
                <p class="text-xs font-semibold text-indigo-700 uppercase mb-1">Resultado de la sincronización</p>
                <pre class="text-xs text-indigo-900 whitespace-pre-wrap font-mono">{{ $resumenSync }}</pre>
            </div>
        @endif

        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 mb-4">
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar CIF, razón social, firmante, referencia…"
                class="flex-1 min-w-[240px] rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <select wire:model.live="filtroEstado" class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los estados</option>
                <option value="pendiente_empresa">Pendiente de empresa</option>
                <option value="candidato_creado">Candidato creado</option>
                <option value="error">Error</option>
            </select>
            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" wire:model.live="verDescartados" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Ver descartados @if($stats['descartados'])<span class="text-gray-400">({{ $stats['descartados'] }})</span>@endif
            </label>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Firmante</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado externo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Procesamiento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alumnos</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Candidato</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Firmado</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($contratos as $c)
                            <tr class="hover:bg-gray-50 {{ $c->descartado_en ? 'opacity-60' : ($c->estado_procesamiento === 'pendiente_empresa' ? 'bg-amber-50/40' : '') }}">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $c->empresa_razon_social ?? '—' }}</div>
                                    <div class="text-xs text-gray-500 font-mono">{{ $c->empresa_cif ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900">{{ $c->firmante_nombre ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ $c->email ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600">{{ $c->estado_externo ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($c->estado_procesamiento === 'candidato_creado')
                                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Candidato creado</span>
                                    @elseif($c->estado_procesamiento === 'pendiente_empresa')
                                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">Pendiente empresa</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-semibold" title="{{ $c->error_message }}">Error</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <span class="font-semibold">{{ $c->alumnos_pendientes_count }}</span>
                                    <span class="text-gray-400">/ {{ $c->alumnos_count }}</span>
                                    <span class="text-xs text-gray-400 block">pend. / total</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($c->candidato)
                                        <a href="{{ route('webcurso.candidatos.estatus', $c->candidato) }}" class="text-indigo-600 hover:underline">{{ $c->candidato->nombre_contacto }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    {{ $c->aceptado_en?->format('d/m/Y H:i') ?? '—' }}
                                    <span class="block text-gray-400 font-mono">{{ $c->referencia_aceptacion }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($c->descartado_en)
                                        <button type="button" wire:click="restaurar({{ $c->id }})"
                                            class="px-2.5 py-1 rounded bg-gray-100 text-gray-700 text-xs font-semibold hover:bg-gray-200">
                                            ↩ Restaurar
                                        </button>
                                    @else
                                        <button type="button" wire:click="descartar({{ $c->id }})"
                                            wire:confirm="¿Descartar este contrato? Se ocultará y el sync ya no lo traerá. El candidato/alumno ya creados NO se borran."
                                            class="px-2.5 py-1 rounded bg-red-50 text-red-600 text-xs font-semibold hover:bg-red-100 border border-red-200">
                                            🗑 Descartar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    {{ $verDescartados ? 'No hay contratos descartados.' : 'No hay contratos de encomienda.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $contratos->links() }}</div>
    </div>
</div>
