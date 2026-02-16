<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                {{-- Header --}}
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Gestión de Candidatos</h2>
                    <a href="{{ route('webcurso.candidatos.crear') }}" 
                       class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition">
                        + Nuevo Candidato
                    </a>
                </div>

                @if (session()->has('message'))
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">{{ session('message') }}</span>
                    </div>
                @endif

                {{-- Filtros --}}
                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {{-- Búsqueda --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                            <input type="text" 
                                   wire:model.live.debounce.300ms="search" 
                                   placeholder="Nombre, email o teléfono..."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        {{-- Filtro Tipo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                            <select wire:model.live="filtroTipo" 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todos</option>
                                @foreach($tiposCandidato as $tipo)
                                    <option value="{{ $tipo->codigo }}">{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Filtro Estatus --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estatus</label>
                            <select wire:model.live="filtroEstatus" 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todos</option>
                                @foreach($estatusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if($search || $filtroTipo || $filtroEstatus)
                        <div class="mt-3">
                            <button wire:click="limpiarFiltros" 
                                    class="text-sm text-indigo-600 hover:text-indigo-800">
                                Limpiar filtros
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Tabla --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Candidato
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Entidad / Tipo
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estatus
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($candidatos as $candidato)
                                <tr class="{{ $candidato->estatus === 'pausado' ? 'bg-yellow-100 hover:bg-yellow-200' : 'hover:bg-gray-50' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $candidato->nombre_contacto }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $candidato->email }}
                                        </div>
                                        @if($candidato->curso_nombre)
                                            <div class="text-xs text-indigo-600 mt-1">
                                                📚 {{ $candidato->curso_nombre }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <a href="{{ route('webcurso.candidatos.editar', $candidato) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900 transition-colors">
                                                {{ $candidato->nombre_entidad }}
                                            </a>
                                            <span class="text-xs text-gray-500">
                                                {{ $candidato->tipoCandidato->nombre }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $badgeColors = [
                                                'pendiente' => 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-600/20 hover:bg-yellow-100',
                                                'completo' => 'bg-green-50 text-green-700 ring-1 ring-green-600/20 hover:bg-green-100',
                                                'desactivado' => 'bg-red-50 text-red-700 ring-1 ring-red-600/20 hover:bg-red-100',
                                                'pausado' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 hover:bg-emerald-100',
                                            ];
                                        @endphp
                                        <button wire:click="verDetalles({{ $candidato->id }})" 
                                                title="Ver detalles del candidato"
                                                class="group inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all transform hover:scale-105 active:scale-95 shadow-sm hover:shadow {{ $badgeColors[$candidato->estatus] ?? 'bg-gray-50 text-gray-700 ring-1 ring-gray-600/20' }}">
                                            <span>{{ ucfirst($candidato->estatus) }}</span>
                                            <svg class="w-3.5 h-3.5 opacity-60 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('webcurso.candidatos.estatus', $candidato) }}" 
                                               class="inline-flex items-center px-2 py-1 bg-indigo-50 text-indigo-700 rounded-md hover:bg-indigo-100 transition-colors">
                                                🔍 Gestionar
                                            </a>
                                            
                                            @if($candidato->estatus === 'pendiente')
                                                <button wire:click="pausarCandidato({{ $candidato->id }})" 
                                                        wire:key="btn-pausar-{{ $candidato->id }}"
                                                        wire:confirm="¿Desea PAUSAR los recordatorios para este candidato?"
                                                        class="inline-flex items-center px-2 py-1 bg-amber-50 text-amber-700 rounded-md hover:bg-amber-100 transition-colors">
                                                    ⏸️ Pausar
                                                </button>
                                            @elseif($candidato->estatus === 'pausado' || $candidato->estatus === 'desactivado')
                                                <button wire:click="reactivarCandidato({{ $candidato->id }})" 
                                                        wire:key="btn-reactivar-{{ $candidato->id }}"
                                                        wire:confirm="¿Desea REACTIVAR el envío de recordatorios para este candidato?"
                                                        class="inline-flex items-center px-2 py-1 bg-emerald-50 text-emerald-700 rounded-md hover:bg-emerald-100 transition-colors">
                                                    ▶️ Reactivar
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        No se encontraron candidatos
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Paginación --}}
                <div class="mt-4">
                    {{ $candidatos->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Detalle --}}
    @if($showDetailsModal && $selectedCandidatoDetails)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cerrarDetalles"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Detalles del Candidato
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 font-bold mb-4">
                                        {{ $selectedCandidatoDetails->nombre_contacto }} ({{ $selectedCandidatoDetails->email }})
                                    </p>

                                    {{-- Progreso --}}
                                    @php
                                        $total = $selectedCandidatoDetails->requisitos->count();
                                        $completados = $selectedCandidatoDetails->requisitos->where('estado', 'completado')->count();
                                        $porcentaje = $total > 0 ? round(($completados / $total) * 100) : 0;
                                    @endphp
                                    <div class="mb-4">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span>Progreso de Requisitos</span>
                                            <span>{{ $completados }}/{{ $total }}</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $porcentaje }}%"></div>
                                        </div>
                                    </div>

                                    {{-- Lista de Requisitos --}}
                                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Requisitos</h4>
                                    <ul class="text-sm space-y-2 mb-4 max-h-40 overflow-y-auto">
                                        @foreach($selectedCandidatoDetails->requisitos as $req)
                                            <li class="flex items-center justify-between p-2 rounded {{ $req->estado === 'completado' ? 'bg-green-50' : 'bg-gray-50' }}">
                                                <span class="text-gray-700">{{ $req->tipoRequisito->nombre }}</span>
                                                <span class="text-xs font-bold {{ $req->estado === 'completado' ? 'text-green-600' : 'text-gray-500' }}">
                                                    {{ $req->estado === 'completado' ? '✅' : '⏳' }} {{ ucfirst($req->estado) }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>

                                    {{-- Recordatorios --}}
                                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Recordatorios</h4>
                                    <div class="bg-gray-50 p-3 rounded-lg text-sm">
                                        <div class="flex justify-between mb-1">
                                            <span>Enviados:</span>
                                            <span class="font-bold">{{ $selectedCandidatoDetails->recordatorios_enviados }} / {{ config('candidatos.recordatorios.max_recordatorios', 5) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Último:</span>
                                            <span class="text-gray-600">
                                                {{ $selectedCandidatoDetails->ultimo_recordatorio ? $selectedCandidatoDetails->ultimo_recordatorio->format('d/m/Y H:i') : 'Nunca' }}
                                            </span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="cerrarDetalles" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
