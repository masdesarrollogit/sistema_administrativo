<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-3xl font-bold text-gray-900">Tutores</h1>
            <button wire:click="abrirModalCrear"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                + Nuevo Tutor
            </button>
        </div>

        {{-- Mensajes --}}
        @if(session('message'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                {{ session('message') }}
            </div>
        @endif

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" wire:model.live.debounce.300ms="busqueda"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Nombre, apellido, NIF...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tipo</label>
                    <select wire:model.live="filtroTipo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Todos</option>
                        <option value="interno">Interno</option>
                        <option value="externo">Externo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
                    <select wire:model.live="filtroActivo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button wire:click="limpiarFiltros" class="text-sm text-indigo-600 hover:text-indigo-800">Limpiar filtros</button>
                </div>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tutor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIF</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contacto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alumnos T1 / T2</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($tutores as $tutor)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $tutor->nombre_completo }}</div>
                                <div class="text-xs text-gray-500">{{ $tutor->iniciales }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $tutor->nif }}</td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-700">{{ $tutor->email }}</div>
                                <div class="text-xs text-gray-500">{{ $tutor->telefono }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $tutor->tipo === 'interno' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ ucfirst($tutor->tipo) }}
                                </span>
                                @if($tutor->moodle_username)
                                    <div class="text-xs text-indigo-600 mt-1">@{{ $tutor->moodle_username }}</div>
                                @else
                                    <div class="text-xs text-gray-400 mt-1">Sin usuario Moodle</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                @php
                                    $t1 = $tutor->cargaHoyEnTramo('tramo_1');
                                    $t2 = $tutor->cargaHoyEnTramo('tramo_2');
                                @endphp
                                <span title="Alumnos simultáneos hoy en T1 (mañana)" class="{{ $t1 >= 70 ? 'text-red-600 font-semibold' : '' }}">{{ $t1 }}/80</span>
                                <span class="text-gray-400 mx-1">|</span>
                                <span title="Alumnos simultáneos hoy en T2 (tarde)" class="{{ $t2 >= 70 ? 'text-red-600 font-semibold' : '' }}">{{ $t2 }}/80</span>
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="toggleActivo({{ $tutor->id }})"
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full cursor-pointer {{ $tutor->activo ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200' }}">
                                    {{ $tutor->activo ? 'Activo' : 'Inactivo' }}
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="abrirModalEditar({{ $tutor->id }})"
                                        class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                    Editar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                No se encontraron tutores.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $tutores->links() }}
            </div>
        </div>
    </div>

    {{-- Modal Crear/Editar --}}
    @if($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/50" wire:click="cerrarModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    {{ $tutorEditandoId ? 'Editar Tutor' : 'Nuevo Tutor' }}
                </h2>

                <form wire:submit="guardar" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">NIF *</label>
                            <input type="text" wire:model="nif"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('nif') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primer Apellido *</label>
                            <input type="text" wire:model="apellido1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('apellido1') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Segundo Apellido</label>
                            <input type="text" wire:model="apellido2"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" wire:model="email"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefono *</label>
                            <input type="text" wire:model="telefono"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('telefono') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                        <select wire:model="tipo"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="interno">Interno (WebCurso)</option>
                            <option value="externo">Externo</option>
                        </select>
                    </div>

                    @if($tutorEditandoId)
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="activo" id="activo" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="activo" class="text-sm text-gray-700">Tutor activo</label>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Usuario en Moodle
                            <span class="text-xs font-normal text-gray-400 ml-1">(opcional — necesario para autodetectar el aula)</span>
                        </label>
                        <input type="text" wire:model="moodleUsername"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Ej: david.guerra">
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="cerrarModal"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                            {{ $tutorEditandoId ? 'Actualizar' : 'Crear Tutor' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
