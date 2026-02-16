<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                📤 Importar Archivos CSV
            </h1>
            <p class="mt-2 text-gray-600">Sube los archivos CSV de empresas y grupos para actualizar la base de datos</p>
        </div>

        {{-- Navegación --}}
        <div class="mb-6 flex gap-4">
            <a href="{{ route('webcurso.dashboard') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                🏠 Volver al Panel
            </a>
        </div>

        {{-- Formulario --}}
        <div class="bg-white rounded-xl shadow-sm p-6">
            <form wire:submit="procesar">
                {{-- Selector de año --}}
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="esAnterior" 
                               class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                        <span class="font-medium text-gray-700">
                            Importar para el <strong>año anterior</strong> ({{ date('Y') - 1 }})
                        </span>
                    </label>
                    <p class="mt-2 text-sm text-gray-500 ml-8">
                        Si no marcas esta opción, los datos se importarán para el año actual ({{ date('Y') }})
                    </p>
                </div>

                @error('general')
                    <div class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg">
                        ❌ {{ $message }}
                    </div>
                @enderror

                {{-- Archivo de Empresas --}}
                <div class="mb-6 p-6 border-2 border-dashed border-gray-300 rounded-xl hover:border-blue-400 transition-colors">
                    <div class="text-center">
                        <span class="text-4xl">🏢</span>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900">Archivo de Empresas</h3>
                        <p class="text-sm text-gray-500 mb-4">Formato CSV con separador punto y coma (;)</p>
                        
                        <input type="file" wire:model="archivoEmpresas" accept=".csv,.txt"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        
                        @error('archivoEmpresas')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @if($archivoEmpresas)
                            <p class="mt-2 text-sm text-green-600">
                                ✅ {{ $archivoEmpresas->getClientOriginalName() }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Archivo de Grupos --}}
                <div class="mb-6 p-6 border-2 border-dashed border-gray-300 rounded-xl hover:border-purple-400 transition-colors">
                    <div class="text-center">
                        <span class="text-4xl">👥</span>
                        <h3 class="mt-2 text-lg font-semibold text-gray-900">Archivo de Grupos</h3>
                        <p class="text-sm text-gray-500 mb-4">Formato CSV con separador punto y coma (;)</p>
                        
                        <input type="file" wire:model="archivoGrupos" accept=".csv,.txt"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 cursor-pointer">
                        
                        @error('archivoGrupos')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @if($archivoGrupos)
                            <p class="mt-2 text-sm text-green-600">
                                ✅ {{ $archivoGrupos->getClientOriginalName() }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Información importante --}}
                <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <h4 class="font-semibold text-amber-800 flex items-center gap-2">
                        ⚠️ Información Importante
                    </h4>
                    <ul class="mt-2 text-sm text-amber-700 list-disc list-inside space-y-1">
                        <li><strong>Empresas:</strong> Se actualizarán por CIF (UPSERT). Los registros existentes se modificarán.</li>
                        <li><strong>Grupos:</strong> Se <strong class="text-red-600">eliminarán todos</strong> los grupos existentes y se cargarán los nuevos.</li>
                        <li>Asegúrate de que los archivos usen <strong>punto y coma (;)</strong> como separador.</li>
                    </ul>
                </div>

                {{-- Botones --}}
                <div class="flex gap-4">
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            class="flex-1 py-3 px-6 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2"
                            {{ $procesando ? 'disabled' : '' }}>
                        <span wire:loading.remove wire:target="procesar">📤 Procesar Archivos</span>
                        <span wire:loading wire:target="procesar">
                            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Procesando...
                        </span>
                    </button>

                    <button type="button" wire:click="limpiar"
                            class="py-3 px-6 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600 transition-colors">
                        🗑️ Limpiar
                    </button>
                </div>
            </form>
        </div>

        {{-- Resultado --}}
        @if($resultado)
            <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">📊 Resultado de la Importación</h3>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="p-4 bg-green-50 rounded-lg text-center">
                        <p class="text-3xl font-bold text-green-600">{{ $resultado['procesados'] }}</p>
                        <p class="text-sm text-green-700">Registros procesados</p>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg text-center">
                        <p class="text-3xl font-bold text-red-600">{{ $resultado['errores'] }}</p>
                        <p class="text-sm text-red-700">Errores</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <a href="{{ route('webcurso.empresas') }}" 
                       class="flex-1 py-2 px-4 bg-blue-600 text-white text-center rounded-lg hover:bg-blue-700">
                        🏢 Ver Empresas
                    </a>
                    <a href="{{ route('webcurso.grupos') }}" 
                       class="flex-1 py-2 px-4 bg-purple-600 text-white text-center rounded-lg hover:bg-purple-700">
                        👥 Ver Grupos
                    </a>
                </div>
            </div>
        @endif

        {{-- Logs --}}
        @if(count($logs) > 0)
            <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">📝 Log de Procesamiento</h3>
                
                <div class="max-h-96 overflow-y-auto space-y-2">
                    @foreach($logs as $log)
                        <div class="p-3 rounded-lg text-sm
                            {{ $log['tipo'] === 'error' ? 'bg-red-50 text-red-700 border-l-4 border-red-500' : '' }}
                            {{ $log['tipo'] === 'success' ? 'bg-green-50 text-green-700 border-l-4 border-green-500' : '' }}
                            {{ $log['tipo'] === 'warning' ? 'bg-amber-50 text-amber-700 border-l-4 border-amber-500' : '' }}
                            {{ $log['tipo'] === 'info' ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-500' : '' }}
                        ">
                            {{ $log['mensaje'] }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
