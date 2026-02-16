<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                🏢 Empresas Registradas
            </h1>
        </div>

        {{-- Navegación --}}
        <div class="mb-6 flex flex-wrap gap-3">
            <a href="{{ route('webcurso.dashboard') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                🏠 Panel Principal
            </a>
            <a href="{{ route('webcurso.grupos', ['anio' => $anioSeleccionado]) }}" 
               class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm">
                👥 Ver Grupos
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
                    class="px-4 py-2 rounded-lg font-medium transition-all {{ $anioSeleccionado === $anioActual ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    {{ $anioActual }}
                </button>
                <button 
                    wire:click="cambiarAnio({{ $anioAnterior }})"
                    class="px-4 py-2 rounded-lg font-medium transition-all {{ $anioSeleccionado === $anioAnterior ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    {{ $anioAnterior }}
                </button>
            </div>
        </div>

        {{-- Estadísticas --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-4 text-white">
                <p class="text-blue-100 text-xs">TOTAL EMPRESAS</p>
                <p class="text-2xl font-bold">{{ number_format($stats->total ?? 0) }}</p>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl p-4 text-white">
                <p class="text-green-100 text-xs">PyMEs</p>
                <p class="text-2xl font-bold">{{ number_format($stats->pymes ?? 0) }}</p>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl p-4 text-white">
                <p class="text-amber-100 text-xs">CRÉDITO ASIGNADO</p>
                <p class="text-lg font-bold">{{ number_format($stats->asignado ?? 0, 0, ',', '.') }} €</p>
            </div>
            <div class="bg-gradient-to-br from-red-500 to-red-700 rounded-xl p-4 text-white">
                <p class="text-red-100 text-xs">CRÉDITO DISPUESTO</p>
                <p class="text-lg font-bold">{{ number_format($stats->dispuesto ?? 0, 0, ',', '.') }} €</p>
            </div>
            <div class="bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-xl p-4 text-white">
                <p class="text-cyan-100 text-xs">CRÉDITO DISPONIBLE</p>
                <p class="text-lg font-bold">{{ number_format($stats->disponible ?? 0, 0, ',', '.') }} €</p>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">🔍 Filtrar Empresas</h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">CIF</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroCif" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Buscar CIF...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Razón Social</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroRazonSocial" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Buscar nombre...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">PyME</label>
                    <select wire:model.live="filtroPyme" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="SI">Sí</option>
                        <option value="NO">No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nueva Creación</label>
                    <select wire:model.live="filtroNuevaCreacion" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="SI">Sí</option>
                        <option value="NO">No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Bloqueada</label>
                    <select wire:model.live="filtroBloqueada" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="SI">Sí</option>
                        <option value="NO">No</option>
                    </select>
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
                    <option value="12">12</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-sm text-gray-600">
                    Mostrando {{ $empresas->firstItem() ?? 0 }} - {{ $empresas->lastItem() ?? 0 }} de {{ $empresas->total() }}
                </span>
            </div>
            <div>
                {{ $empresas->links() }}
            </div>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50/50">
                        <tr>
                            @foreach(config('webcurso.vistas.empresas.columnas_tabla') as $columna => $label)
                                <th class="px-4 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest cursor-pointer hover:bg-gray-100 transition-colors" 
                                    wire:click="sortear('{{ $columna }}')">
                                    <div class="flex items-center gap-1">
                                        {{ $label }}
                                        @if($sortBy === $columna)
                                            <span class="text-blue-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                            <th class="px-4 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($empresas as $empresa)
                            <tr class="hover:bg-blue-50/30 transition-colors {{ $empresa->nuevo ? 'bg-green-50/50' : '' }}">
                                @foreach(config('webcurso.vistas.empresas.columnas_tabla') as $columna => $label)
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($columna === 'razon_social')
                                            <span class="font-black text-blue-600 hover:text-blue-800 cursor-pointer hover:underline decoration-2 underline-offset-4" 
                                               wire:click="abrirModalEmpresa({{ $empresa->id }})">
                                                {{ $empresa->$columna }}
                                            </span>
                                        @elseif(str_contains($columna, 'credito') || str_contains($columna, 'importe'))
                                            <span class="font-mono font-bold {{ $columna === 'credito_disponible' ? 'text-green-600' : 'text-gray-900' }}">
                                                {{ number_format($empresa->$columna, 2, ',', '.') }} €
                                            </span>
                                        @elseif($columna === 'id')
                                            <span class="text-gray-400 font-mono text-xs">#{{ $empresa->$columna }}</span>
                                        @elseif($columna === 'cif')
                                            <span class="font-mono font-bold text-gray-700">{{ $empresa->$columna }}</span>
                                        @else
                                            {{ $empresa->$columna }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <button wire:click="abrirModalEmpresa({{ $empresa->id }})"
                                            class="px-4 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-900 hover:text-white text-xs font-black transition-all uppercase tracking-tighter">
                                        Detalles
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count(config('webcurso.vistas.empresas.columnas_tabla')) + 1 }}" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="text-4xl">🔍</span>
                                        <p class="text-gray-500 font-bold">No se encontraron empresas con los filtros aplicados</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Paginación inferior --}}
        <div class="mt-6">
            {{ $empresas->links() }}
        </div>
    </div>

    {{-- Modal Compartido --}}
    @include('livewire.webcurso.partials.modal-empresa', ['viewName' => 'empresas'])
</div>

