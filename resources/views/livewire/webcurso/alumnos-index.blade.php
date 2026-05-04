<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Alumnos</h1>
        </div>

        {{-- Mensajes --}}
        @if(session('message'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                {{ session('message') }}
            </div>
        @endif

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" wire:model.live.debounce.300ms="search"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Nombre, apellido, NIF, email...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Empresa</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroEmpresa"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="CIF o razón social...">
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
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tipo</label>
                    <select wire:model.live="filtroTipo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Todos</option>
                        <option value="fundae">Con grupos FUNDAE</option>
                        <option value="autonomo">Autónomos (2x1)</option>
                        <option value="legacy">Con historial legacy</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button wire:click="limpiarFiltros" class="text-sm text-indigo-600 hover:text-indigo-800">Limpiar filtros</button>
                </div>
            </div>

            {{-- Filtros por fechas --}}
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mt-3 pt-3 border-t border-gray-100">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Año del curso</label>
                    <select wire:model.live="filtroAno"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Todos los años</option>
                        @foreach($aniosDisponibles as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Desde</label>
                    <input type="date" wire:model.live="filtroDesde"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Hasta</label>
                    <input type="date" wire:model.live="filtroHasta"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="md:col-span-3 flex items-end">
                    <p class="text-xs text-gray-500">
                        Filtra alumnos con cursos cuya fecha de inicio cumpla el criterio. Busca en grupos FUNDAE, participantes bonificados, autónomos y historial legacy.
                    </p>
                </div>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alumno</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIF</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grupos</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($alumnos as $alumno)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900">{{ $alumno->nombre_completo }}</span>
                                    @if($alumno->autonomos_total > 0)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200"
                                              title="{{ $alumno->autonomos_total }} matrícula{{ $alumno->autonomos_total > 1 ? 's' : '' }} autónoma{{ $alumno->autonomos_total > 1 ? 's' : '' }}">
                                            2x1
                                        </span>
                                    @endif
                                </div>
                                @if($alumno->telefono)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $alumno->telefono }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $alumno->nif }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $alumno->email ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-700">{{ $alumno->empresa?->razon_social ?? '—' }}</div>
                                @if($alumno->empresa?->cif)
                                    <div class="text-xs text-gray-500">{{ $alumno->empresa->cif }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1 items-center">
                                    @if($alumno->grupos_total > 0)
                                        @if($alumno->grupos_activos > 0)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                {{ $alumno->grupos_activos }} activo{{ $alumno->grupos_activos > 1 ? 's' : '' }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-gray-500">{{ $alumno->grupos_total }} FUNDAE</span>
                                    @endif
                                    @if($alumno->bonificados_total > 0 && $alumno->grupos_total == 0)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800"
                                              title="Participaciones importadas de FUNDAE">
                                            {{ $alumno->bonificados_total }} FUNDAE
                                        </span>
                                    @endif
                                    @if($alumno->autonomos_total > 0)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">
                                            {{ $alumno->autonomos_total }} autónomo{{ $alumno->autonomos_total > 1 ? 's' : '' }}
                                        </span>
                                    @endif
                                    @if($alumno->legacy_total > 0)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-violet-100 text-violet-700"
                                              title="Cursos realizados en sistema legacy webcourses2014">
                                            {{ $alumno->legacy_total }} legacy
                                        </span>
                                    @endif
                                    @if($alumno->grupos_total == 0 && $alumno->bonificados_total == 0 && $alumno->autonomos_total == 0 && $alumno->legacy_total == 0)
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="toggleActivo({{ $alumno->id }})"
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full cursor-pointer {{ $alumno->activo ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200' }}">
                                    {{ $alumno->activo ? 'Activo' : 'Inactivo' }}
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <button wire:click="abrirModalEditar({{ $alumno->id }})"
                                            class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                        Editar
                                    </button>
                                    @if($alumno->grupos_total > 0 || $alumno->autonomos_total > 0 || $alumno->bonificados_total > 0 || $alumno->legacy_total > 0)
                                        <button wire:click="abrirModalGrupos({{ $alumno->id }})"
                                                class="text-gray-500 hover:text-gray-700 text-sm">
                                            Historial
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                No se encontraron alumnos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $alumnos->links() }}
            </div>
        </div>
    </div>

    {{-- Modal Editar Alumno --}}
    @if($mostrarModalEditar)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black/50" wire:click="cerrarModalEditar"></div>
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Editar Alumno</h2>

                <form wire:submit="guardar" class="space-y-4">
                    {{-- Nombre y apellidos --}}
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primer apellido *</label>
                            <input type="text" wire:model="apellido1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('apellido1') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Segundo apellido</label>
                            <input type="text" wire:model="apellido2"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>

                    {{-- NIF y email --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">NIF *</label>
                            <input type="text" wire:model="nif"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('nif') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" wire:model="email"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Teléfono y fecha nacimiento --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <input type="text" wire:model="telefono"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('telefono') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de nacimiento</label>
                            <input type="date" wire:model="fechaNacimiento"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('fechaNacimiento') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Sexo y jornada laboral --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sexo</label>
                            <select wire:model="sexo"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">— Sin especificar —</option>
                                <option value="H">Hombre</option>
                                <option value="M">Mujer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jornada laboral *</label>
                            <select wire:model="jornadaLaboral"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="1">Completa</option>
                                <option value="2">Media</option>
                                <option value="3">Parcial</option>
                                <option value="4">Por horas</option>
                            </select>
                        </div>
                    </div>

                    {{-- Datos FUNDAE --}}
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Datos FUNDAE</p>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">NISS</label>
                                <input type="text" wire:model="niss" maxlength="12"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="12 dígitos">
                                @error('niss') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CCC (cuenta cotización)</label>
                                <input type="text" wire:model="ccc" maxlength="11"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="11 dígitos">
                                @error('ccc') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Grupo cotización TGSS</label>
                                <input type="text" wire:model="grupoCotizacionTgss" maxlength="5"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="Ej: 07">
                                @error('grupoCotizacionTgss') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nivel de estudios</label>
                                <select wire:model="nivelEstudios"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">— Sin especificar —</option>
                                    <option value="1">1 — Menos que primaria</option>
                                    <option value="2">2 — Educación primaria</option>
                                    <option value="3">3 — ESO / EGB</option>
                                    <option value="4">4 — Bachillerato / FP medio</option>
                                    <option value="5">5 — Certificado prof. nivel 3</option>
                                    <option value="6">6 — FP superior</option>
                                    <option value="7">7 — Diplomatura / Grado</option>
                                    <option value="8">8 — Licenciatura / Máster</option>
                                    <option value="9">9 — Doctorado</option>
                                    <option value="10">10 — Otras titulaciones</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría profesional</label>
                                <select wire:model="categoriaProfesional"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">— Sin especificar —</option>
                                    <option value="1">1 — Directivo</option>
                                    <option value="2">2 — Mando intermedio</option>
                                    <option value="3">3 — Técnico</option>
                                    <option value="4">4 — Cualificado</option>
                                    <option value="5">5 — Baja cualificación</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="cerrarModalEditar"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                            Actualizar
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
    @endif

    {{-- Modal Historial de Grupos --}}
    @if($mostrarModalGrupos)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black/50" wire:click="cerrarModalGrupos"></div>
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Historial</h2>
                        <p class="text-sm text-gray-500">{{ $alumnoGruposNombre }}</p>
                    </div>
                </div>

                @if($gruposDelAlumno && $gruposDelAlumno->isNotEmpty())
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700">FUNDAE</span>
                            Grupos bonificados
                        </h3>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acción formativa</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fechas</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Moodle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($gruposDelAlumno as $grupo)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ $grupo->accionFormativa?->denominacion ?? '—' }}</div>
                                            <div class="text-xs text-gray-500">{{ $grupo->accionFormativa?->numero_accion }}/{{ $grupo->id_grupo_fundae }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $grupo->empresa?->razon_social ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $grupo->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                            @if($grupo->fecha_fin)
                                                <span class="text-gray-400">→</span>
                                                {{ $grupo->fecha_fin->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @php
                                                $estadoClases = match($grupo->estado) {
                                                    'abierto'    => 'bg-blue-100 text-blue-800',
                                                    'comunicado' => 'bg-amber-100 text-amber-800',
                                                    'en_curso'   => 'bg-green-100 text-green-800',
                                                    'completado' => 'bg-gray-100 text-gray-700',
                                                    'cancelado'  => 'bg-red-100 text-red-800',
                                                    default      => 'bg-gray-100 text-gray-600',
                                                };
                                            @endphp
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $estadoClases }}">
                                                {{ ucfirst(str_replace('_', ' ', $grupo->estado)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @php $estadoMoodle = $grupo->pivot->estado_moodle ?? 'pendiente'; @endphp
                                            @if($estadoMoodle === 'matriculado')
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Matriculado</span>
                                            @elseif($estadoMoodle === 'aulasystem')
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">Aulasystem</span>
                                            @elseif($estadoMoodle === 'error')
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Error</span>
                                            @elseif($estadoMoodle === 'creado')
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Creado</span>
                                            @else
                                                <span class="text-xs text-gray-400">Pendiente</span>
                                            @endif
                                            @if($grupo->pivot->moodle_username)
                                                <div class="text-xs text-gray-400 mt-1">{{ $grupo->pivot->moodle_username }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($bonificadosDelAlumno && $bonificadosDelAlumno->isNotEmpty())
                    <div class="mt-4">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800">IMPORTADO</span>
                            Participación FUNDAE (importada)
                        </h3>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grupo</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">PIF</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fechas</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado grupo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($bonificadosDelAlumno as $pb)
                                    <tr class="hover:bg-emerald-50">
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $pb->id_codigo_grupo ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $pb->codigo_pif ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $pb->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                            @if($pb->fecha_fin)
                                                <span class="text-gray-400">→</span>
                                                {{ $pb->fecha_fin->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                                {{ $pb->estado ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                                {{ $pb->estado_grupo ?? '—' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($autonomosDelAlumno && $autonomosDelAlumno->isNotEmpty())
                    <div class="mt-4">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700 border border-amber-200">2x1</span>
                            Matrículas autónomas
                        </h3>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acción formativa</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tutor</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fechas</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado Moodle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($autonomosDelAlumno as $ma)
                                    <tr class="hover:bg-amber-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ $ma->accionFormativa?->denominacion_limpia ?? '—' }}</div>
                                            <div class="text-xs text-gray-500">{{ $ma->accionFormativa?->numero_accion }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $ma->tutor?->nombre_completo ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $ma->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                            @if($ma->fecha_fin)
                                                <span class="text-gray-400">→</span>
                                                {{ $ma->fecha_fin->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($ma->estado === 'matriculado')
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Matriculado</span>
                                                @if($ma->moodle_username)
                                                    <div class="text-xs text-gray-400 mt-1">{{ $ma->moodle_username }}</div>
                                                @endif
                                            @elseif($ma->estado === 'error')
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Error</span>
                                            @else
                                                <span class="text-xs text-gray-400">Pendiente</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($legacyDelAlumno && $legacyDelAlumno->isNotEmpty())
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2 flex items-center gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-violet-100 text-violet-700">LEGACY</span>
                            Historial webcourses2014
                        </h3>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Curso (legacy)</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acción formativa</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grupo</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fechas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($legacyDelAlumno as $cl)
                                    @php
                                        $accionPanel = $cl->formation_group_alpha ? ($accionesPorNumero[$cl->formation_group_alpha] ?? null) : null;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ $cl->curso_titulo ?? '—' }}</div>
                                            <div class="text-xs text-gray-500">
                                                @if($cl->curso_short_name) {{ $cl->curso_short_name }} · @endif
                                                {{ $cl->curso_horas ? $cl->curso_horas.'h' : '' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($accionPanel)
                                                <div class="font-medium text-gray-900">{{ $accionPanel->denominacion }}</div>
                                                <div class="text-xs text-gray-500">Acción Nº {{ $accionPanel->numero_accion }} · {{ $accionPanel->horas }}h</div>
                                            @elseif($cl->formation_group_alpha)
                                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 text-gray-700"
                                                      title="Acción Nº {{ $cl->formation_group_alpha }} no encontrada en el Panel">
                                                    Acción {{ $cl->formation_group_alpha }} (no en Panel)
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            @if($cl->formation_group_alpha && $cl->formation_group_number)
                                                @if($cl->grupo_id_fundae)
                                                    <span class="text-xs text-gray-500 font-mono">({{ $cl->grupo_id_fundae }})</span>
                                                @endif
                                                <span class="inline-flex px-2 py-0.5 text-xs font-mono font-semibold rounded bg-violet-50 text-violet-700">
                                                    {{ $cl->formation_group_alpha }}/{{ $cl->formation_group_number }}
                                                </span>
                                                @if($cl->origen_enriquecimiento)
                                                    <div class="text-[10px] text-gray-400 mt-0.5" title="Origen del dato">
                                                        @if($cl->origen_enriquecimiento === 'grupos_fundae')
                                                            via grupos FUNDAE
                                                        @elseif($cl->origen_enriquecimiento === 'participantes_bonificados')
                                                            via participantes bonificados
                                                        @endif
                                                    </div>
                                                @endif
                                            @elseif($cl->formation_group_number)
                                                <span class="text-xs text-gray-700">Grupo {{ $cl->formation_group_number }}</span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 text-xs">{{ $cl->legacy_company_text ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $cl->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                            @if($cl->fecha_fin)
                                                <span class="text-gray-400">→</span>
                                                {{ $cl->fecha_fin->format('d/m/Y') }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex justify-end pt-4 border-t border-gray-200 mt-4">
                    <button wire:click="cerrarModalGrupos"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
                        Cerrar
                    </button>
                </div>
            </div>
            </div>
        </div>
    @endif
</div>
