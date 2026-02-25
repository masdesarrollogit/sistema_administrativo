<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                👥 Grupos Registrados
            </h1>
        </div>

        {{-- Navegación --}}
        <div class="mb-6 flex flex-wrap gap-3">
            <a href="{{ route('webcurso.dashboard') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                🏠 Panel Principal
            </a>
            <a href="{{ route('webcurso.empresas', ['anio' => $anioSeleccionado]) }}" 
               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
                🏢 Ver Empresas
            </a>
            <a href="{{ route('webcurso.empresas-sin-grupos', ['anio' => $anioSeleccionado]) }}" 
               class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors text-sm">
                ⚠️ Empresas Sin Grupos
            </a>
        </div>

        {{-- Selector de Año --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex items-center justify-center gap-4">
            <label class="font-semibold text-gray-700">Año del Reporte:</label>
            <div class="flex gap-2">
                <button 
                    wire:click="cambiarAnio({{ $anioActual }})"
                    class="px-4 py-2 rounded-lg font-medium transition-all {{ $anioSeleccionado === $anioActual ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    {{ $anioActual }}
                </button>
                <button 
                    wire:click="cambiarAnio({{ $anioAnterior }})"
                    class="px-4 py-2 rounded-lg font-medium transition-all {{ $anioSeleccionado === $anioAnterior ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    {{ $anioAnterior }}
                </button>
            </div>
        </div>

        {{-- Estadísticas --}}
        <div class="bg-blue-50 rounded-xl p-4 mb-6 text-center">
            <span class="text-lg">
                📊 <strong>Total de grupos ({{ $anioSeleccionado }}):</strong> {{ number_format($stats['total']) }} |
                🆔 <strong>Con CIF válido:</strong> {{ number_format($stats['con_cif']) }} |
                ⏭️ <strong>Sin CIF:</strong> {{ number_format($stats['sin_cif']) }}
            </span>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">🔍 Filtrar Grupos ({{ $anioSeleccionado }})</h3>
            <div class="grid grid-cols-2 md:grid-cols-7 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Denominación</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroDenominacion" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500"
                           placeholder="Ej: Excel...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">CIF</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroCif" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500"
                           placeholder="Buscar CIF...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
                    <select wire:model.live="filtroEstado" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Todos</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="Válido">Válido</option>
                        <option value="Modificado">Modificado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Modalidad</label>
                    <select wire:model.live="filtroModalidad" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500">
                        <option value="">Todas</option>
                        <option value="Teleformación">Teleformación</option>
                        <option value="Presencial">Presencial</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Código</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroCodigo" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500"
                           placeholder="Código...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Acción Formativa</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroAccionFormativa" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-purple-500 focus:border-purple-500"
                           placeholder="Acción...">
                </div>
                <div class="flex items-end">
                    <button wire:click="limpiarFiltros" 
                            class="w-full px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm">
                        🗑️ Limpiar
                    </button>
                </div>
            </div>
        </div>

        {{-- Paginación superior --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <label class="text-sm text-gray-600">Mostrar:</label>
                <select wire:model.live="perPage" 
                        class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-sm text-gray-600">
                    Mostrando {{ $grupos->firstItem() ?? 0 }} - {{ $grupos->lastItem() ?? 0 }} de {{ $grupos->total() }}
                </span>
            </div>
            <div>
                {{ $grupos->links() }}
            </div>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grupo ID</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acción Form.</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase max-w-xs">Denominación</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-amber-50">CIF</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inicio</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fin</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modalidad</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Duración</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Participantes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($grupos as $grupo)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-900">{{ $grupo->id }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $grupo->grupo_id }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $grupo->codigo_grupo }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $grupo->codigo_grupo_accion_formativa }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $grupo->tipo_accion_formativa }}</td>
                                <td class="px-3 py-2 max-w-xs truncate text-gray-700 text-xs" title="{{ $grupo->denominacion }}">
                                    {{ Str::limit($grupo->denominacion, 50) }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap bg-amber-50">
                                    @if($grupo->cif)
                                        <button
                                            wire:click="abrirModalEmpresa('{{ $grupo->cif }}')"
                                            class="font-mono font-bold text-amber-700 hover:text-amber-900 hover:underline cursor-pointer transition-colors"
                                            title="Ver empresa">
                                            {{ $grupo->cif }}
                                        </button>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ $grupo->inicio ? $grupo->inicio->format('d/m/Y') : '' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ $grupo->fin ? $grupo->fin->format('d/m/Y') : '' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $grupo->modalidad }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-center text-gray-600">{{ $grupo->duracion }}h</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="font-semibold
                                        {{ $grupo->estado === 'Finalizado' ? 'text-green-600' : '' }}
                                        {{ $grupo->estado === 'Válido' ? 'text-blue-600' : '' }}
                                        {{ $grupo->estado === 'Modificado' ? 'text-orange-500' : '' }}
                                    ">
                                        {{ $grupo->estado }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-center text-gray-600">{{ $grupo->numero_participantes }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-8 text-center text-gray-500">
                                    No se encontraron grupos con los filtros aplicados
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Paginación inferior --}}
        <div class="mt-4">
            {{ $grupos->links() }}
        </div>
    </div>

    {{-- ========== MODAL EMPRESA POR CIF ========== --}}
    @if($mostrarModal)
        <div class="fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 overflow-y-auto"
             wire:click.self="cerrarModalEmpresa">
            <div class="flex items-start justify-center min-h-screen p-4 py-10">
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">

                    {{-- Header --}}
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center sticky top-0 z-10">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            🏢 Detalle de Empresa
                        </h3>
                        <button wire:click="cerrarModalEmpresa" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="p-6">
                        @if($empresaModal)
                            <div class="bg-blue-50 rounded-xl p-5 border border-blue-100 shadow-sm">
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-1">Empresa</p>
                                <p class="font-black text-gray-900 text-xl leading-tight mb-4">
                                    {{ $empresaModal['razon_social'] }}
                                </p>

                                <div class="grid grid-cols-1 gap-y-3">
                                    <div class="flex justify-between items-center py-1.5 border-b border-blue-100/50">
                                        <span class="text-xs font-bold text-gray-500 uppercase">CIF</span>
                                        <span class="text-sm font-bold font-mono text-gray-900">{{ $empresaModal['cif'] }}</span>
                                    </div>

                                    <div class="flex justify-between items-center py-2 mt-1 bg-white/60 px-3 rounded-lg border border-blue-100/30">
                                        <span class="text-xs font-black text-blue-600 uppercase">Saldo Disponible</span>
                                        @if($empresaModal['credito_disponible'] !== null)
                                            <span class="text-xl font-black text-green-600">
                                                {{ $empresaModal['saldo_formateado'] }}
                                            </span>
                                        @else
                                            <span class="text-sm font-bold text-gray-400 italic">
                                                Empresa no registrada
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
