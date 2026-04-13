<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Acciones Formativas FUNDAE</h1>
            <p class="text-sm text-gray-500 mt-1">Importadas desde AccionesFormativas.xls de FUNDAE</p>
        </div>

        {{-- Estadisticas --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-xl p-4 text-white">
                <p class="text-indigo-100 text-xs">TOTAL ACCIONES</p>
                <p class="text-2xl font-bold">{{ number_format($totalAcciones) }}</p>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl p-4 text-white">
                <p class="text-green-100 text-xs">VINCULADAS CON MOODLE</p>
                <p class="text-2xl font-bold">{{ number_format($totalVinculadas) }}</p>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl p-4 text-white">
                <p class="text-amber-100 text-xs">SIN VINCULAR</p>
                <p class="text-2xl font-bold">{{ number_format($totalAcciones - $totalVinculadas) }}</p>
            </div>
            <div class="bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-xl p-4 text-white">
                <p class="text-cyan-100 text-xs">% VINCULACION</p>
                <p class="text-2xl font-bold">{{ $totalAcciones > 0 ? round(($totalVinculadas / $totalAcciones) * 100) : 0 }}%</p>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" wire:model.live.debounce.300ms="busqueda"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Denominacion o numero...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
                    <select wire:model.live="filtroEstado"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Todos</option>
                        <option value="Alta">Alta</option>
                        <option value="Baja">Baja</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Area Profesional</label>
                    <select wire:model.live="filtroAreaProfesional"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Todas</option>
                        @foreach($areasProfesionales as $area)
                            <option value="{{ $area }}">{{ $area }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Plataforma</label>
                    <select wire:model.live="filtroPlataforma"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Todas</option>
                        @foreach($plataformas as $nif => $label)
                            <option value="{{ $nif }}">{{ $nif === 'B65828857' ? 'aula.1curso.com (m)' : 'www.plataformateleformacion.com (a)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button wire:click="limpiarFiltros" class="text-sm text-indigo-600 hover:text-indigo-800">Limpiar filtros</button>
                </div>
            </div>
        </div>

        {{-- Mensaje flash --}}
        @if(session('message'))
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{{ session('message') }}</div>
        @endif

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">N.Accion</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Denominacion</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Horas</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plataforma</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cursos Moodle</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($acciones as $accion)
                        {{-- Fila principal --}}
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-indigo-100 text-indigo-800 text-sm font-bold">
                                    {{ $accion->numero_accion }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $accion->denominacion_limpia }}</div>
                                <div class="text-xs text-gray-400">{{ $accion->denominacion }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $accion->horas }}h</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-700">
                                    {{ $accion->codigo_actividad ?? $accion->area_profesional ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($accion->codigo_plataforma === 'm')
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-700">aula.1curso.com</span>
                                @elseif($accion->codigo_plataforma === 'a')
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded bg-amber-100 text-amber-700">www.plataformateleformacion.com</span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $accion->estado === 'Alta' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $accion->estado ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @if($accion->moodle_cursos_count > 0)
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ $accion->moodle_cursos_count }} curso(s)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-500">
                                            Sin vincular
                                        </span>
                                    @endif
                                    <button wire:click="abrirVinculacion({{ $accion->id }})"
                                            class="px-2 py-1 text-xs bg-indigo-50 text-indigo-700 rounded hover:bg-indigo-100 font-medium">
                                        + Vincular
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- Fila de vinculación inline (se muestra al pulsar "+ Vincular") --}}
                        @if($accionVinculandoId === $accion->id)
                            <tr>
                                <td colspan="7" class="px-4 py-4 bg-indigo-50 border-b-2 border-indigo-300">

                                    {{-- Vínculos existentes --}}
                                    @php $vinculos = $accion->moodleCursos()->get(); @endphp
                                    @if($vinculos->isNotEmpty())
                                        <div class="mb-4">
                                            <p class="text-xs font-semibold text-gray-600 uppercase mb-2">Cursos vinculados</p>
                                            <div class="space-y-1">
                                                @foreach($vinculos as $v)
                                                    <div class="flex items-center justify-between bg-white rounded px-3 py-2 border border-gray-200 text-sm">
                                                        <span>
                                                            <span class="font-medium text-gray-900">ID {{ $v->moodle_course_id }}</span>
                                                            — {{ $v->moodle_fullname }}
                                                            <span class="ml-2 px-1.5 py-0.5 text-xs rounded
                                                                {{ $v->tipo === 'activa' ? 'bg-green-100 text-green-700' : '' }}
                                                                {{ $v->tipo === 'plantilla' ? 'bg-blue-100 text-blue-700' : '' }}
                                                                {{ $v->tipo === 'repaso' ? 'bg-amber-100 text-amber-700' : '' }}
                                                                {{ $v->tipo === 'desactualizado' ? 'bg-gray-100 text-gray-500' : '' }}">
                                                                {{ $v->tipo }}
                                                            </span>
                                                        </span>
                                                        <button wire:click="eliminarVinculo({{ $v->id }})"
                                                                wire:confirm="¿Eliminar este vínculo?"
                                                                class="text-red-400 hover:text-red-600 text-xs ml-4">Eliminar</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Formulario nuevo vínculo --}}
                                    <p class="text-xs font-semibold text-gray-600 uppercase mb-2">Añadir curso de Moodle</p>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 bg-white rounded-lg p-3 border border-indigo-200">
                                        <div class="col-span-2 relative">
                                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                                Buscar curso en Moodle *
                                                @if($vinculoMoodleCourseId)
                                                    <span class="text-green-600 font-normal">✓ ID {{ $vinculoMoodleCourseId }} seleccionado</span>
                                                @endif
                                            </label>
                                            <div class="relative">
                                                <input type="text" wire:model.live.debounce.400ms="vinculoBusqueda"
                                                       class="w-full px-2 py-1.5 border rounded text-sm {{ $vinculoMoodleCourseId ? 'border-green-300 bg-green-50' : 'border-gray-300 focus:ring-indigo-500 focus:border-indigo-500' }}"
                                                       placeholder="Escribe al menos 3 letras del nombre del curso...">
                                                <span wire:loading wire:target="updatedVinculoBusqueda"
                                                      class="absolute right-2 top-2 text-xs text-gray-400">Buscando...</span>
                                            </div>
                                            {{-- Sugerencias --}}
                                            @if(count($vinculoSugerencias) > 0)
                                                <div class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    @foreach($vinculoSugerencias as $s)
                                                        <button wire:click="seleccionarCursoMoodle({{ $s['id'] }}, '{{ addslashes($s['fullname']) }}')"
                                                                class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 border-b border-gray-100 last:border-0">
                                                            <span class="font-medium text-gray-800">{{ $s['fullname'] }}</span>
                                                            <span class="ml-2 text-xs text-gray-400">ID {{ $s['id'] }}</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @elseif(strlen($vinculoBusqueda) >= 3 && !$vinculoBuscando && !$vinculoMoodleCourseId)
                                                <p class="text-xs text-gray-400 mt-1">Sin resultados para "{{ $vinculoBusqueda }}"</p>
                                            @endif
                                            @error('vinculoMoodleCourseId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                            @error('vinculoMoodleFullname') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Tipo *</label>
                                            <select wire:model.live="vinculoTipo"
                                                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="activa">Activa</option>
                                                <option value="plantilla">Plantilla (curso base)</option>
                                                <option value="repaso">Repaso</option>
                                                <option value="desactualizado">Desactualizado</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 mt-3">
                                        <button wire:click="guardarVinculo"
                                                class="px-4 py-1.5 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700 font-medium">
                                            Guardar vínculo
                                        </button>
                                        <button wire:click="cerrarVinculacion"
                                                class="px-4 py-1.5 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">
                                            Cancelar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endif

                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                No se encontraron acciones formativas. Importa el archivo XLS desde "Importar Archivos".
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $acciones->links() }}
            </div>
        </div>
    </div>
</div>
