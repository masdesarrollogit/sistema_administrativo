<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                📊 Panel de Consultas WebCurso
            </h1>
            <p class="mt-2 text-gray-600">Gestión de empresas y grupos de formación FUNDAE</p>
        </div>

        {{-- Selector de Año --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex items-center justify-center gap-4">
            <label class="font-semibold text-gray-700">Seleccionar Año:</label>
            <div class="flex gap-2">
                <button 
                    wire:click="cambiarAnio({{ $anioActual }})"
                    class="px-4 py-2 rounded-lg font-medium transition-all {{ $anioSeleccionado === $anioActual ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    {{ $anioActual }} (Actual)
                </button>
                <button 
                    wire:click="cambiarAnio({{ $anioAnterior }})"
                    class="px-4 py-2 rounded-lg font-medium transition-all {{ $anioSeleccionado === $anioAnterior ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    {{ $anioAnterior }} (Anterior)
                </button>
            </div>
        </div>

        {{-- Estadísticas --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {{-- Total Empresas --}}
            <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">EMPRESAS ({{ $anioSeleccionado }})</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totalEmpresas) }}</p>
                    </div>
                    <div class="text-4xl opacity-80">🏢</div>
                </div>
            </div>

            {{-- PyMEs --}}
            <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">PyMEs</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totalPymes) }}</p>
                    </div>
                    <div class="text-4xl opacity-80">🏭</div>
                </div>
            </div>

            {{-- Crédito Asignado --}}
            <div class="bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-amber-100 text-sm font-medium">CRÉDITO ASIGNADO</p>
                        <p class="text-2xl font-bold mt-1">{{ number_format($totalAsignado, 0, ',', '.') }} €</p>
                    </div>
                    <div class="text-4xl opacity-80">💰</div>
                </div>
            </div>

            {{-- Crédito Disponible --}}
            <div class="bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-xl p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-cyan-100 text-sm font-medium">CRÉDITO DISPONIBLE</p>
                        <p class="text-2xl font-bold mt-1">{{ number_format($totalDisponible, 0, ',', '.') }} €</p>
                    </div>
                    <div class="text-4xl opacity-80">💵</div>
                </div>
            </div>
        </div>

        {{-- Segunda fila de estadísticas --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            {{-- Total Grupos --}}
            <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">TOTAL GRUPOS</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totalGrupos) }}</p>
                        <p class="text-purple-200 text-xs mt-1">Con CIF: {{ number_format($gruposConCif) }}</p>
                    </div>
                    <div class="text-4xl opacity-80">👥</div>
                </div>
            </div>

            {{-- Empresas sin Grupos --}}
            <div class="bg-gradient-to-br from-red-500 to-red-700 rounded-xl p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100 text-sm font-medium">EMPRESAS SIN GRUPOS</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($empresasSinGrupos) }}</p>
                    </div>
                    <div class="text-4xl opacity-80">⚠️</div>
                </div>
            </div>

            {{-- Última Actualización --}}
            <div class="bg-gradient-to-br from-gray-600 to-gray-800 rounded-xl p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-300 text-sm font-medium">ÚLTIMA ACTUALIZACIÓN</p>
                        <p class="text-xl font-bold mt-1">{{ $ultimaActualizacion ?? 'Sin datos' }}</p>
                    </div>
                    <div class="text-4xl opacity-80">🕐</div>
                </div>
            </div>
        </div>

        {{-- Navegación --}}
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">🔍 Acceso Rápido</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('webcurso.empresas', ['anio' => $anioSeleccionado]) }}" 
                   class="flex items-center gap-3 p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors group">
                    <span class="text-2xl">🏢</span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-blue-700">Ver Empresas</p>
                        <p class="text-sm text-gray-500">{{ number_format($totalEmpresas) }} registros</p>
                    </div>
                </a>

                <a href="{{ route('webcurso.grupos', ['anio' => $anioSeleccionado]) }}" 
                   class="flex items-center gap-3 p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors group">
                    <span class="text-2xl">👥</span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-purple-700">Ver Grupos</p>
                        <p class="text-sm text-gray-500">{{ number_format($totalGrupos) }} registros</p>
                    </div>
                </a>

                <a href="{{ route('webcurso.empresas-sin-grupos', ['anio' => $anioSeleccionado]) }}" 
                   class="flex items-center gap-3 p-4 bg-red-50 rounded-lg hover:bg-red-100 transition-colors group">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-red-700">Empresas Sin Grupos</p>
                        <p class="text-sm text-gray-500">{{ number_format($empresasSinGrupos) }} sin asignar</p>
                    </div>
                </a>

                <a href="{{ route('webcurso.importar') }}" 
                   class="flex items-center gap-3 p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors group">
                    <span class="text-2xl">📤</span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-green-700">Importar CSV</p>
                        <p class="text-sm text-gray-500">Cargar nuevos datos</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
