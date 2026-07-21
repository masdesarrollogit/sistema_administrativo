<div class="space-y-6">
    {{-- Mensajes --}}
    @if(session('message-matricula'))
        <div class="p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{{ session('message-matricula') }}</div>
    @endif
    @if(session('error-matricula'))
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{{ session('error-matricula') }}</div>
    @endif

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- GRUPOS FORMATIVOS                              --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Grupos Formativos</h3>
            <button wire:click="abrirFormGrupo" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">
                + Nuevo Grupo
            </button>
        </div>

        {{-- Formulario crear grupo --}}
        @if($mostrarFormGrupo)
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                <h4 class="font-semibold text-gray-800 mb-3">Crear Grupo Formativo</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Accion formativa --}}
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Accion Formativa *</label>
                        <input type="text" wire:model.live.debounce.300ms="busquedaAccion"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Buscar por denominacion o numero...">
                        @if(!empty($resultadosAccion))
                            <div class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                @foreach($resultadosAccion as $r)
                                    <button type="button" wire:click="seleccionarAccion({{ $r['id'] }})"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 border-b border-gray-100 flex items-center justify-between gap-2">
                                        <span>{{ $r['label'] }}</span>
                                        @if(($r['plataforma'] ?? null) === 'a')
                                            <span class="flex-shrink-0 inline-flex px-1.5 py-0.5 text-xs rounded bg-amber-100 text-amber-700 font-medium">Aulasystem</span>
                                        @elseif(($r['plataforma'] ?? null) === 'm')
                                            <span class="flex-shrink-0 inline-flex px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-700 font-medium">Moodle</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @error('nuevaAccionFormativaId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Tutor --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tutor *</label>
                        <select wire:model="nuevoTutorId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Seleccionar tutor...</option>
                            @foreach($tutores as $tutor)
                                <option value="{{ $tutor->id }}">{{ $tutor->nombre_completo }} ({{ $tutor->tipo }})</option>
                            @endforeach
                        </select>
                        @error('nuevoTutorId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Tramo --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tramo Horario *</label>
                        <select wire:model="nuevoTramo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="tramo_1">Tramo 1 (8:00 - 11:00)</option>
                            <option value="tramo_2">Tramo 2 (15:00 - 18:00)</option>
                        </select>
                    </div>

                    {{-- Jornada laboral --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Jornada Laboral</label>
                        <select wire:model="nuevaJornadaLaboral" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="1">1 - Jornada completa</option>
                            <option value="2">2 - Media jornada</option>
                        </select>
                    </div>

                    {{-- Fechas --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Inicio *</label>
                        <input type="date" wire:model="nuevaFechaInicio" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @error('nuevaFechaInicio') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Fin *</label>
                        <input type="date" wire:model="nuevaFechaFin" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @error('nuevaFechaFin') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Días (calcula fin)</label>
                        <input type="number" wire:model.live="nuevasDias" min="1" max="365"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Ej: 30">
                        <p class="text-xs text-gray-400 mt-1">Rellena para calcular fecha fin automáticamente</p>
                    </div>

                    {{-- Descripcion --}}
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Descripcion (opcional, se genera automaticamente)</label>
                        <input type="text" wire:model="nuevaDescripcion" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Se genera automaticamente al agregar alumnos">
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button wire:click="crearGrupo" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">Crear Grupo</button>
                    <button wire:click="$set('mostrarFormGrupo', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Cancelar</button>
                </div>
            </div>
        @endif

        {{-- Lista de grupos --}}
        @if($grupos->isNotEmpty())
            <div class="space-y-4">
                @foreach($grupos as $grupo)
                    @php $gestionando = $grupoSeleccionadoId === $grupo->id; @endphp
                    <div class="border rounded-lg {{ $gestionando ? 'border-indigo-400 bg-indigo-50/30' : 'border-gray-200' }}">

                        {{-- Cabecera del grupo --}}
                        <div class="p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-gray-900">
                                            #{{ $grupo->accionFormativa->numero_accion }} — {{ Str::limit($grupo->accionFormativa->denominacion_limpia, 55) }}
                                        </span>
                                        @php
                                            $coloresEstado = [
                                                'abierto'    => 'bg-blue-100 text-blue-800',
                                                'comunicado' => 'bg-purple-100 text-purple-800',
                                                'en_curso'   => 'bg-green-100 text-green-800',
                                                'completado' => 'bg-emerald-100 text-emerald-800',
                                                'cancelado'  => 'bg-gray-100 text-gray-600',
                                            ];
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $coloresEstado[$grupo->estado] ?? '' }}">
                                            {{ ucfirst($grupo->estado) }}
                                        </span>
                                        @if($grupo->codigo_fundae)
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">FUNDAE: {{ $grupo->codigo_fundae }}</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        {{ $grupo->tutor->nombre_completo }}
                                        &nbsp;|&nbsp; {{ $grupo->tramo_label }}
                                        &nbsp;|&nbsp; {{ $grupo->fecha_inicio->format('d/m/Y') }} – {{ $grupo->fecha_fin->format('d/m/Y') }}
                                        &nbsp;|&nbsp; {{ $grupo->alumnos->count() }} alumno(s)
                                    </div>
                                    @if($grupo->descripcion)
                                        <div class="text-xs text-gray-500 mt-1 italic">{{ $grupo->descripcion }}</div>
                                    @endif
                                </div>

                                {{-- Botones de acción --}}
                                <div class="flex gap-1 flex-wrap flex-shrink-0 ml-2">
                                    @if($grupo->estado === 'abierto')
                                        @if(!$gestionando)
                                            <button wire:click="seleccionarGrupo({{ $grupo->id }})"
                                                    class="px-2 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700 font-medium">
                                                Gestionar
                                            </button>
                                        @else
                                            <button wire:click="deseleccionarGrupo"
                                                    class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                                Cerrar
                                            </button>
                                        @endif
                                        <button wire:click="abrirEditarGrupo({{ $grupo->id }})"
                                                class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-xs hover:bg-amber-200">
                                            Editar
                                        </button>
                                        <button wire:click="eliminarGrupo({{ $grupo->id }})"
                                                wire:confirm="¿Eliminar este grupo? Se desvinculan todos los alumnos."
                                                class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">
                                            Eliminar
                                        </button>
                                    @endif
                                    @if(in_array($grupo->estado, ['abierto', 'comunicado']))
                                        @if(!$grupo->id_grupo_fundae)
                                            <button wire:click="generarIdGrupoFundae({{ $grupo->id }})"
                                                    class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-xs hover:bg-amber-200">
                                                ID FUNDAE
                                            </button>
                                        @endif
                                        @if($grupo->alumnos->count() > 0)
                                            <button wire:click="descargarXmlInicioGrupo({{ $grupo->id }})"
                                                    class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs hover:bg-purple-200">
                                                XML Inicio
                                            </button>
                                        @else
                                            <span class="px-2 py-1 bg-gray-100 text-gray-400 rounded text-xs cursor-not-allowed" title="Agrega alumnos primero">
                                                XML Inicio
                                            </span>
                                        @endif
                                    @endif
                                    @if($grupo->estado === 'abierto' && $grupo->id_grupo_fundae && $grupo->alumnos->count() > 0)
                                        @if($gruposEnFundae[$grupo->id] ?? false)
                                            {{-- Grupo ya confirmado en FUNDAE (tabla grupos importada) --}}
                                            <button wire:click="marcarComunicado({{ $grupo->id }})"
                                                    wire:confirm="El grupo {{ $grupo->accionFormativa->numero_accion }}/{{ $grupo->id_grupo_fundae }} ya existe en FUNDAE. ¿Marcarlo como comunicado?"
                                                    class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200 font-medium">
                                                ✓ Ya en FUNDAE
                                            </button>
                                        @else
                                            {{-- Subir PDF de notificación FUNDAE para validar y marcar comunicado --}}
                                            <button wire:click="abrirSubirPdf({{ $grupo->id }})"
                                                    class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200">
                                                📄 PDF FUNDAE
                                            </button>
                                        @endif
                                    @endif
                                    @if($grupo->estado === 'comunicado')
                                        @if($grupo->accionFormativa->codigo_plataforma === 'a')
                                            {{-- Plataforma aulasystem: matriculación manual --}}
                                            <button wire:click="marcarMatriculadoAulasystem({{ $grupo->id }})"
                                                    wire:confirm="¿Marcar todos los alumnos como matriculados en Aulasystem?"
                                                    class="px-2 py-1 text-white rounded text-xs font-medium hover:opacity-80"
                                                    style="background-color: #f59e0b;">
                                                Matriculado en Aulasystem
                                            </button>
                                        @else
                                            {{-- Plataforma Moodle: matriculación via API --}}
                                            @php
                                                $cursosVinculados = $grupo->accionFormativa->moodleCursos()->with('tutores')->where('tipo', 'activa')->get();
                                                $aulasDelTutor    = $cursosVinculados->filter(fn ($cv) => $cv->tutores->contains('id', $grupo->tutor_id));
                                                // Solo cuando el aula no está resuelta: varias vinculadas y ninguna
                                                // (o más de una) con este tutor. En cuanto se registra, desaparece.
                                                $necesitaSelector = $cursosVinculados->count() > 1
                                                    && !$grupo->moodle_course_id
                                                    && $aulasDelTutor->count() !== 1;
                                            @endphp
                                            @if($necesitaSelector)
                                                <select wire:model="moodleCursosPorGrupo.{{ $grupo->id }}"
                                                        class="px-2 py-1 border border-gray-300 rounded text-xs max-w-xs">
                                                    <option value="">Selecciona aula Moodle</option>
                                                    @foreach($cursosVinculados as $cv)
                                                        <option value="{{ $cv->moodle_course_id }}">{{ $cv->moodle_fullname }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                            <button wire:click="ejecutarEnMoodle({{ $grupo->id }})"
                                                    wire:confirm="¿Matricular alumnos en Moodle?"
                                                    class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                                {{ $grupo->moodle_course_id ? 'Re-matricular' : 'Matricular en Moodle' }}
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Formulario subir PDF notificación FUNDAE --}}
                        @if($pdfGrupoId === $grupo->id)
                            <div class="border-t border-indigo-200 bg-indigo-50 px-4 py-3">
                                <p class="text-xs font-semibold text-indigo-700 uppercase mb-2">Subir notificación FUNDAE (PDF)</p>
                                <p class="text-xs text-indigo-600 mb-3">
                                    Sube el PDF "Notificación: Inicio de Grupo" descargado del portal FUNDAE después de cargar el XML.
                                    El sistema validará que corresponde al grupo <strong>{{ $grupo->accionFormativa->numero_accion }}/{{ $grupo->id_grupo_fundae }}</strong>
                                    y lo marcará como <strong>Comunicado</strong>.
                                </p>
                                <div class="flex items-center gap-3">
                                    <input type="file"
                                           wire:model="pdfNotificacion"
                                           accept=".pdf"
                                           class="text-sm text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:bg-indigo-100 file:text-indigo-700 hover:file:bg-indigo-200">
                                    <button wire:click="procesarPdfNotificacion"
                                            wire:loading.attr="disabled"
                                            class="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700 font-medium">
                                        <span wire:loading.remove wire:target="procesarPdfNotificacion">Validar y procesar</span>
                                        <span wire:loading wire:target="procesarPdfNotificacion">Procesando...</span>
                                    </button>
                                    <button wire:click="cerrarSubirPdf"
                                            class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                        Cancelar
                                    </button>
                                </div>
                                @error('pdfNotificacion')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        {{-- Formulario edición inline del grupo --}}
                        @if($editandoGrupoId === $grupo->id)
                            <div class="border-t border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-xs font-semibold text-amber-700 uppercase mb-3">Editar grupo</p>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Tutor *</label>
                                        <select wire:model="editGrupoTutorId" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                            <option value="">Seleccionar...</option>
                                            @foreach($tutores as $t)
                                                <option value="{{ $t->id }}">{{ $t->nombre_completo }}</option>
                                            @endforeach
                                        </select>
                                        @error('editGrupoTutorId') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Tramo *</label>
                                        <select wire:model="editGrupoTramo" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                            <option value="tramo_1">T1 (8:00-11:00)</option>
                                            <option value="tramo_2">T2 (15:00-18:00)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Jornada</label>
                                        <select wire:model="editGrupoJornada" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                            <option value="1">Completa</option>
                                            <option value="2">Media jornada</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Fecha inicio *</label>
                                        <input type="date" wire:model="editGrupoFechaInicio" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                        @error('editGrupoFechaInicio') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Fecha fin *</label>
                                        <input type="date" wire:model="editGrupoFechaFin" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                        @error('editGrupoFechaFin') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Días (calcula fin)</label>
                                        <input type="number" wire:model.live="editDias" min="1" max="365"
                                               class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm"
                                               placeholder="Ej: 30">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Descripción</label>
                                        <input type="text" wire:model="editGrupoDescripcion" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm" placeholder="Opcional">
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-3">
                                    <button wire:click="actualizarGrupo" class="px-3 py-1.5 bg-amber-600 text-white rounded text-sm hover:bg-amber-700">Guardar</button>
                                    <button wire:click="cerrarEditarGrupo" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">Cancelar</button>
                                </div>
                            </div>
                        @endif

                        {{-- Alumnos ya asignados al grupo --}}
                        @if($grupo->alumnos->isNotEmpty())
                            <div class="border-t border-gray-200 px-4 pb-3 pt-2">
                                <table class="min-w-full text-xs">
                                    <thead>
                                        <tr class="text-gray-400 uppercase text-left">
                                            <th class="py-1 pr-4">Alumno</th>
                                            <th class="py-1 pr-4">Email</th>
                                            <th class="py-1 pr-4">NIF</th>
                                            <th class="py-1 pr-4">Moodle</th>
                                            <th class="py-1"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($grupo->alumnos as $alumnoGrupo)
                                            <tr class="{{ $editandoAlumnoId === $alumnoGrupo->id ? 'bg-amber-50' : '' }}">
                                                <td class="py-1 pr-4 font-medium text-gray-900">{{ $alumnoGrupo->nombre_completo }}</td>
                                                <td class="py-1 pr-4">
                                                    @if($alumnoGrupo->email)
                                                        <a href="mailto:{{ $alumnoGrupo->email }}"
                                                           class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $alumnoGrupo->email }}</a>
                                                    @else
                                                        <span class="text-amber-600">sin email</span>
                                                    @endif
                                                </td>
                                                <td class="py-1 pr-4 text-gray-600">{{ $alumnoGrupo->nif }}</td>
                                                <td class="py-1 pr-4">
                                                    @php $em = $alumnoGrupo->pivot->estado_moodle; @endphp
                                                    @if($em === 'aulasystem')
                                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: #f59e0b;">aulasystem</span>
                                                    @else
                                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs font-medium
                                                            {{ $em === 'matriculado' ? 'bg-green-100 text-green-800' : ($em === 'error' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600') }}">
                                                            {{ $em ?? 'pendiente' }}
                                                        </span>
                                                    @endif
                                                    {{-- El usuario de Moodle es el email; solo se muestra si difiere
                                                         (caso del alumno sin email, que matricula como {nif}@webcurso.es) --}}
                                                    @if($alumnoGrupo->pivot->moodle_username && $alumnoGrupo->pivot->moodle_username !== $alumnoGrupo->email)
                                                        <span class="text-gray-400 ml-1">{{ $alumnoGrupo->pivot->moodle_username }}</span>
                                                    @endif
                                                </td>
                                                <td class="py-1 flex gap-2">
                                                    @if($alumnoGrupo->pivot->ficha_inscripcion_path)
                                                        <button wire:click="descargarFichaPdf({{ $grupo->id }}, {{ $alumnoGrupo->id }})"
                                                                title="Descargar Ficha PDF archivada"
                                                                class="text-purple-600 hover:text-purple-800 text-xs">📎 PDF</button>
                                                    @endif
                                                    @if(in_array($grupo->estado, ['abierto', 'en_curso']))
                                                        <button wire:click="abrirEditarAlumno({{ $alumnoGrupo->id }})"
                                                                class="text-amber-600 hover:text-amber-800 text-xs">Editar</button>
                                                    @endif
                                                    @if($grupo->estado === 'abierto')
                                                        <button wire:click="quitarAlumnoDelGrupo({{ $grupo->id }}, {{ $alumnoGrupo->id }})"
                                                                class="text-red-400 hover:text-red-600 text-xs">Quitar</button>
                                                    @endif
                                                </td>
                                            </tr>
                                            {{-- Formulario edición inline del alumno --}}
                                            @if($editandoAlumnoId === $alumnoGrupo->id)
                                                <tr>
                                                    <td colspan="5" class="py-2 px-2">
                                                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                                            <p class="text-xs font-semibold text-amber-700 uppercase mb-2">Editar alumno</p>
                                                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                                <div>
                                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nombre *</label>
                                                                    <input type="text" wire:model="editAlumnoNombre" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                                                    @error('editAlumnoNombre') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                                </div>
                                                                <div>
                                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Apellido 1 *</label>
                                                                    <input type="text" wire:model="editAlumnoApellido1" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                                                    @error('editAlumnoApellido1') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                                </div>
                                                                <div>
                                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Apellido 2</label>
                                                                    <input type="text" wire:model="editAlumnoApellido2" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                                                </div>
                                                                <div>
                                                                    <label class="block text-xs font-medium text-gray-600 mb-1">NIF *</label>
                                                                    <input type="text" wire:model="editAlumnoNif" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                                                    @error('editAlumnoNif') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                                </div>
                                                                <div>
                                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                                                                    <input type="email" wire:model="editAlumnoEmail" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                                                    @error('editAlumnoEmail') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                                </div>
                                                                <div>
                                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Teléfono</label>
                                                                    <input type="text" wire:model="editAlumnoTelefono" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                                                </div>
                                                            </div>
                                                            <div class="flex gap-2 mt-2">
                                                                <button wire:click="actualizarAlumno" class="px-3 py-1 bg-amber-600 text-white rounded text-sm hover:bg-amber-700">Guardar</button>
                                                                <button wire:click="cerrarEditarAlumno" class="px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">Cancelar</button>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- ══════════════════════════════════════════════════════ --}}
                        {{-- PANEL DE GESTIÓN (inline, solo cuando estado=abierto) --}}
                        {{-- ══════════════════════════════════════════════════════ --}}
                        @if($gestionando && $grupo->estado === 'abierto')
                            <div class="border-t-2 border-indigo-300 bg-indigo-50 px-4 py-4 space-y-4">

                                {{-- ── Sección 1: Alumno individual (siempre visible) ── --}}
                                <div>
                                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Nuevo alumno individual</p>
                                    @if($mostrarFormAlumno)
                                        <div class="p-3 bg-white border border-indigo-200 rounded-lg">
                                            <form wire:submit="guardarAlumno" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nombre *</label>
                                                    <input type="text" wire:model="alumnoNombre"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    @error('alumnoNombre') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Apellido 1 *</label>
                                                    <input type="text" wire:model="alumnoApellido1"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    @error('alumnoApellido1') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Apellido 2</label>
                                                    <input type="text" wire:model="alumnoApellido2"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">NIF *</label>
                                                    <input type="text" wire:model="alumnoNif"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    @error('alumnoNif') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                                                    <input type="email" wire:model.blur="alumnoEmail"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    @error('alumnoEmail') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">Telefono</label>
                                                    <input type="text" wire:model="alumnoTelefono"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                </div>
                                                <div class="col-span-full flex gap-2">
                                                    <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700">Guardar</button>
                                                    <button type="button" wire:click="$set('mostrarFormAlumno', false)"
                                                            class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">Cancelar</button>
                                                </div>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                {{-- ── Sección 2: Importar desde archivo (toggle) ── --}}
                                <div>
                                    <button wire:click="$toggle('mostrarImportAlumnos')"
                                            class="text-xs text-teal-700 hover:text-teal-900 font-semibold underline underline-offset-2">
                                        {{ $mostrarImportAlumnos ? '▲ Ocultar subida masiva' : '▼ Subida masiva' }}
                                    </button>

                                    @if($mostrarImportAlumnos)
                                        <div class="mt-2 p-3 bg-white border border-teal-200 rounded-lg">
                                            <p class="text-xs text-gray-500 mb-3">
                                                Sube la <strong>Ficha de Inscripción</strong> Excel que enviaste al cliente.
                                                Se importan: nombre, apellidos, NIF, email, teléfono y datos FUNDAE.
                                            </p>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <input type="file"
                                                       wire:model="archivoAlumnos"
                                                       accept=".csv,.xls,.xlsx,.txt"
                                                       class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 cursor-pointer">

                                                <span wire:loading wire:target="archivoAlumnos"
                                                      class="text-xs text-gray-500 italic">
                                                    Cargando archivo...
                                                </span>

                                                @if($archivoAlumnos)
                                                    <button wire:click="importarAlumnosDesdeArchivo"
                                                            wire:loading.attr="disabled"
                                                            wire:target="importarAlumnosDesdeArchivo"
                                                            class="px-3 py-1.5 text-white rounded text-sm font-medium disabled:opacity-50 hover:opacity-80"
                                                            style="background-color: #0d9488;">
                                                        Importar
                                                    </button>
                                                @endif
                                            </div>
                                            @error('archivoAlumnos') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                </div>

                                {{-- ── Sección 3: Subir Ficha PDF (1 alumno por PDF, varios PDFs) ── --}}
                                <div>
                                    <button wire:click="toggleSubidaPdf"
                                            class="text-xs text-purple-700 hover:text-purple-900 font-semibold underline underline-offset-2">
                                        {{ $mostrarSubidaPdf ? '▲ Ocultar subida de Fichas PDF' : '▼ Subir Ficha PDF (Encomienda / Inscripción)' }}
                                    </button>

                                    @if($mostrarSubidaPdf)
                                        <div class="mt-2 p-3 bg-white border border-purple-200 rounded-lg space-y-3">
                                            <p class="text-xs text-gray-500">
                                                Sube uno o varios PDFs (Ficha de Inscripción o Contrato de Encomienda WebCurso 2026).
                                                Se extraen automáticamente los datos del alumno y se muestra un preview editable
                                                antes de guardar. Máximo 20 PDFs, 10 MB cada uno.
                                            </p>

                                            <div class="flex flex-wrap items-center gap-3">
                                                <input type="file"
                                                       wire:model="archivosPdf"
                                                       accept="application/pdf"
                                                       multiple
                                                       class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 cursor-pointer">

                                                <span wire:loading wire:target="archivosPdf"
                                                      class="text-xs text-gray-500 italic">Cargando…</span>

                                                @if(!empty($archivosPdf))
                                                    <button wire:click="procesarPdfs"
                                                            wire:loading.attr="disabled"
                                                            wire:target="procesarPdfs"
                                                            class="px-3 py-1.5 bg-purple-600 text-white rounded text-sm font-medium hover:bg-purple-700 disabled:opacity-50">
                                                        Procesar {{ count($archivosPdf) }} PDF{{ count($archivosPdf) > 1 ? 's' : '' }}
                                                    </button>
                                                @endif
                                            </div>
                                            @error('archivosPdf') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                            @error('archivosPdf.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                            {{-- Preview de PDFs procesados --}}
                                            @if(!empty($previewPdfs))
                                                <div class="border-t border-purple-100 pt-3 mt-3">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <p class="text-xs font-semibold text-gray-700 uppercase tracking-wide">
                                                            Preview ({{ count($previewPdfs) }} PDF{{ count($previewPdfs) > 1 ? 's' : '' }})
                                                        </p>
                                                        <span class="text-xs text-gray-500">
                                                            <span class="inline-block w-2 h-2 bg-yellow-300 rounded mr-1"></span>inferido
                                                            <span class="inline-block w-2 h-2 bg-red-400 rounded ml-2 mr-1"></span>obligatorio faltante
                                                        </span>
                                                    </div>

                                                    <div class="space-y-3">
                                                        @foreach($previewPdfs as $i => $fila)
                                                            @php
                                                                $tipoBadgeClass = match($fila['tipo']) {
                                                                    'ficha'      => 'bg-blue-100 text-blue-700 border-blue-200',
                                                                    'encomienda' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                                                    'ilegible'   => 'bg-red-100 text-red-700 border-red-200',
                                                                    default      => 'bg-gray-100 text-gray-700 border-gray-200',
                                                                };
                                                                $tipoLabel = match($fila['tipo']) {
                                                                    'ficha'      => 'Ficha',
                                                                    'encomienda' => 'Encomienda',
                                                                    'ilegible'   => 'Ilegible · manual',
                                                                    default      => 'Desconocido',
                                                                };
                                                                $estadoBadge = match($fila['estado']) {
                                                                    'crear'      => ['bg-emerald-100 text-emerald-700 border-emerald-200', 'Crear'],
                                                                    'actualizar' => ['bg-amber-100 text-amber-700 border-amber-200', 'Actualizar'],
                                                                    'duplicado'  => ['bg-gray-200 text-gray-600 border-gray-300', 'Duplicado · ignorado'],
                                                                    default      => ['bg-gray-100 text-gray-700 border-gray-200', 'Pendiente'],
                                                                };
                                                                $inferidos = $fila['inferidos'] ?? [];
                                                                $faltantes = $fila['faltantes'] ?? [];
                                                                $esOcr = ($fila['origen'] ?? null) === 'ocr';
                                                            @endphp

                                                            <div class="border rounded-lg p-3 {{ $fila['estado'] === 'duplicado' ? 'bg-gray-50 opacity-70' : 'bg-white' }}">
                                                                <div class="flex items-center justify-between mb-2">
                                                                    <div class="flex items-center gap-2 text-xs">
                                                                        <span class="inline-block px-2 py-0.5 rounded border {{ $tipoBadgeClass }} font-semibold">{{ $tipoLabel }}</span>
                                                                        @if($esOcr)
                                                                            <span class="inline-block px-2 py-0.5 rounded border bg-orange-100 text-orange-700 border-orange-200 font-semibold"
                                                                                  title="Datos extraídos vía OCR — revisa con cuidado">🔍 OCR</span>
                                                                        @endif
                                                                        <span class="inline-block px-2 py-0.5 rounded border {{ $estadoBadge[0] }} font-semibold">{{ $estadoBadge[1] }}</span>
                                                                        <span class="text-gray-500 truncate max-w-md" title="{{ $fila['archivo_nombre'] }}">📄 {{ $fila['archivo_nombre'] }}</span>
                                                                    </div>
                                                                    <button wire:click="quitarPdfPreview({{ $i }})"
                                                                            class="text-xs text-red-600 hover:text-red-800 font-semibold">✕ Quitar</button>
                                                                </div>

                                                                @if(!empty($fila['avisos']))
                                                                    <div class="mb-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
                                                                        @foreach($fila['avisos'] as $aviso)
                                                                            <p>⚠ {{ $aviso }}</p>
                                                                        @endforeach
                                                                    </div>
                                                                @endif

                                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                                                    @foreach(['nombre' => 'Nombre *', 'apellido1' => 'Apellido 1 *', 'apellido2' => 'Apellido 2', 'nif' => 'NIF *', 'email' => 'Email *', 'telefono' => 'Teléfono', 'fecha_nacimiento' => 'Fecha nac.', 'niss' => 'NISS'] as $campo => $label)
                                                                        @php
                                                                            $bg = '';
                                                                            if (in_array($campo, $inferidos, true)) $bg = 'bg-yellow-50 border-yellow-300';
                                                                            elseif (in_array($campo, $faltantes, true)) $bg = 'bg-red-50 border-red-300';
                                                                            $diff = $fila['diff'][$campo] ?? null;
                                                                        @endphp
                                                                        <div>
                                                                            <label class="block text-[10px] text-gray-500 mb-0.5">{{ $label }}</label>
                                                                            <input type="text"
                                                                                   wire:model.lazy="previewPdfs.{{ $i }}.datos.{{ $campo }}"
                                                                                   class="w-full px-2 py-1 border rounded text-xs {{ $bg }}"
                                                                                   @if($diff) title="antes: {{ $diff['antes'] ?: '—' }} → ahora: {{ $diff['ahora'] }}" @endif>
                                                                        </div>
                                                                    @endforeach

                                                                    <div>
                                                                        <label class="block text-[10px] text-gray-500 mb-0.5">Sexo</label>
                                                                        <select wire:model.lazy="previewPdfs.{{ $i }}.datos.sexo"
                                                                                class="w-full px-2 py-1 border rounded text-xs">
                                                                            <option value="">—</option>
                                                                            <option value="H">H</option>
                                                                            <option value="M">M</option>
                                                                        </select>
                                                                    </div>

                                                                    <div>
                                                                        <label class="block text-[10px] text-gray-500 mb-0.5">
                                                                            Grupo cotización TGSS
                                                                            @if(in_array('grupo_cotizacion_tgss', $inferidos)) <span class="text-yellow-700">⚠</span> @endif
                                                                        </label>
                                                                        <select wire:model.lazy="previewPdfs.{{ $i }}.datos.grupo_cotizacion_tgss"
                                                                                class="w-full px-2 py-1 border rounded text-xs {{ in_array('grupo_cotizacion_tgss', $inferidos) ? 'bg-yellow-50 border-yellow-300' : '' }}">
                                                                            <option value="">—</option>
                                                                            @foreach(range(1, 11) as $n)
                                                                                <option value="{{ $n }}">{{ $n }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>

                                                                    <div>
                                                                        <label class="block text-[10px] text-gray-500 mb-0.5">
                                                                            Nivel estudios
                                                                            @if(in_array('nivel_estudios', $inferidos)) <span class="text-yellow-700">⚠ inferido</span> @endif
                                                                        </label>
                                                                        <select wire:model.lazy="previewPdfs.{{ $i }}.datos.nivel_estudios"
                                                                                class="w-full px-2 py-1 border rounded text-xs {{ in_array('nivel_estudios', $inferidos) ? 'bg-yellow-50 border-yellow-300' : '' }}">
                                                                            <option value="">—</option>
                                                                            <option value="1">1 - Menos que primaria</option>
                                                                            <option value="2">2 - Primaria</option>
                                                                            <option value="3">3 - ESO/EGB</option>
                                                                            <option value="4">4 - Bachillerato/FP medio</option>
                                                                            <option value="5">5 - Postsecundaria no superior</option>
                                                                            <option value="6">6 - Técnico Sup./FP superior</option>
                                                                            <option value="7">7 - Diplomatura/Grado</option>
                                                                            <option value="8">8 - Licenciatura/Máster</option>
                                                                            <option value="9">9 - Doctorado</option>
                                                                            <option value="10">10 - Otras</option>
                                                                        </select>
                                                                    </div>

                                                                    <div>
                                                                        <label class="block text-[10px] text-gray-500 mb-0.5">Categoría profesional</label>
                                                                        <select wire:model.lazy="previewPdfs.{{ $i }}.datos.categoria_profesional"
                                                                                class="w-full px-2 py-1 border rounded text-xs">
                                                                            <option value="">—</option>
                                                                            <option value="1">1 - Directivo</option>
                                                                            <option value="2">2 - Mando intermedio</option>
                                                                            <option value="3">3 - Técnico</option>
                                                                            <option value="4">4 - Trabajador cualificado</option>
                                                                            <option value="5">5 - Baja cualificación</option>
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                @if($fila['datos']['curso_pdf'] || $fila['datos']['horas_pdf'] || $fila['datos']['cargo'] || $fila['datos']['empresa_razon_social'])
                                                                    <div class="mt-2 pt-2 border-t border-gray-100 text-[11px] text-gray-500 flex flex-wrap gap-3">
                                                                        @if($fila['datos']['curso_pdf']) <span>📚 Curso: <span class="text-gray-700">{{ $fila['datos']['curso_pdf'] }}</span></span> @endif
                                                                        @if($fila['datos']['horas_pdf']) <span>⏱ Horas: <span class="text-gray-700">{{ $fila['datos']['horas_pdf'] }}</span></span> @endif
                                                                        @if($fila['datos']['cargo']) <span>💼 Cargo: <span class="text-gray-700">{{ $fila['datos']['cargo'] }}</span></span> @endif
                                                                        @if($fila['datos']['empresa_razon_social']) <span>🏢 Empresa: <span class="text-gray-700">{{ $fila['datos']['empresa_razon_social'] }}</span></span> @endif
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    <div class="mt-3 flex justify-end gap-2">
                                                        <button wire:click="$set('previewPdfs', [])"
                                                                class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900">Cancelar</button>
                                                        <button wire:click="confirmarSubidaPdf"
                                                                wire:loading.attr="disabled"
                                                                wire:target="confirmarSubidaPdf"
                                                                class="px-4 py-1.5 bg-purple-600 text-white rounded text-sm font-semibold hover:bg-purple-700 disabled:opacity-50">
                                                            Confirmar y agregar al grupo
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                {{-- ── Sección 4: Seleccionar alumnos ya registrados ── --}}
                                <div>
                                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Seleccionar alumnos de la empresa</p>
                                    @if($alumnos->isNotEmpty())
                                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Alumno</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">NIF</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                                        <th class="px-3 py-2 w-8"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    @foreach($alumnos as $alumnoDisp)
                                                        @php
                                                            $yaEnGrupo      = $grupo->alumnos->contains($alumnoDisp->id);
                                                            $tieneOtroGrupo = !$yaEnGrupo && $alumnoDisp->tieneGrupoActivoEnPeriodo($grupo->fecha_inicio, $grupo->fecha_fin, $grupo->id);
                                                            $bloqueado      = $tieneOtroGrupo;
                                                        @endphp
                                                        <tr wire:click="{{ $bloqueado ? '' : "toggleAlumnoEnGrupo({$grupo->id}, {$alumnoDisp->id})" }}"
                                                            class="{{ $bloqueado ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-indigo-50' }} {{ $yaEnGrupo ? 'bg-indigo-50' : '' }}">
                                                            <td class="px-3 py-2 font-medium text-gray-900">
                                                                <span class="inline-flex items-center gap-2">
                                                                    @if($yaEnGrupo)
                                                                        <span class="w-4 h-4 rounded bg-indigo-600 flex items-center justify-center text-white text-xs">✓</span>
                                                                    @elseif(!$bloqueado)
                                                                        <span class="w-4 h-4 rounded border-2 border-gray-300 inline-block"></span>
                                                                    @else
                                                                        <span class="w-4 h-4 rounded border-2 border-gray-200 inline-block bg-gray-100"></span>
                                                                    @endif
                                                                    {{ $alumnoDisp->nombre_completo }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 text-gray-600">{{ $alumnoDisp->nif }}</td>
                                                            <td class="px-3 py-2 text-gray-500 text-xs">{{ $alumnoDisp->email ?? '—' }}</td>
                                                            <td class="px-3 py-2">
                                                                @if($yaEnGrupo)
                                                                    <span class="text-xs text-indigo-700 font-semibold">En este grupo</span>
                                                                @elseif($tieneOtroGrupo)
                                                                    <span class="text-xs text-amber-600">En otro grupo activo</span>
                                                                @else
                                                                    <span class="text-xs text-green-600">Disponible</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2 text-right">
                                                                @if($yaEnGrupo)
                                                                    <span class="text-xs text-gray-400">Clic para quitar</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-500 text-center py-3">
                                            No hay alumnos registrados para esta empresa. Usa el formulario individual o importa desde archivo.
                                        </p>
                                    @endif
                                </div>

                                {{-- ── Sección 5: Alumnos desde encomienda (sistema externo) ── --}}
                                @if($alumnosEncomienda->isNotEmpty())
                                    <div>
                                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">
                                            <span class="inline-flex items-center gap-1">
                                                <span class="px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-bold">ENCOMIENDA</span>
                                                Alumnos del contrato firmado online
                                            </span>
                                        </p>
                                        <div class="bg-white rounded-lg border border-indigo-200 overflow-hidden">
                                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                <thead class="bg-indigo-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Alumno</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">NIF</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Curso de interés</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contrato</th>
                                                        <th class="px-3 py-2 w-24"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    @foreach($alumnosEncomienda as $stg)
                                                        @php
                                                            $solapa = $stg->nif
                                                                ? \App\Models\Alumno::where('nif', $stg->nif)->where('empresa_id', $candidato->empresa_id)->first()?->tieneGrupoActivoEnPeriodo($grupo->fecha_inicio, $grupo->fecha_fin, $grupo->id)
                                                                : false;
                                                        @endphp
                                                        <tr class="hover:bg-indigo-50/40">
                                                            <td class="px-3 py-2 font-medium text-gray-900">{{ $stg->nombre_completo }}</td>
                                                            <td class="px-3 py-2 text-gray-600">{{ $stg->nif ?? '—' }}</td>
                                                            <td class="px-3 py-2 text-gray-500 text-xs">
                                                                {{ $stg->curso_interes ?? '—' }}
                                                                @if($stg->horas)<span class="text-gray-400">· {{ $stg->horas }}h</span>@endif
                                                            </td>
                                                            <td class="px-3 py-2 text-gray-400 text-[11px] font-mono">{{ $stg->contrato?->referencia_aceptacion ?? '—' }}</td>
                                                            <td class="px-3 py-2 text-right">
                                                                @if($solapa)
                                                                    <span class="text-xs text-amber-600">En otro grupo activo</span>
                                                                @else
                                                                    <button type="button"
                                                                        wire:click="materializarAlumnoEncomienda({{ $grupo->id }}, {{ $stg->id }})"
                                                                        wire:loading.attr="disabled"
                                                                        class="px-2.5 py-1 rounded bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700">
                                                                        + Añadir
                                                                    </button>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-1">Al añadir, se crea el alumno y se enlaza automáticamente el PDF del contrato firmado (si está disponible).</p>
                                    </div>
                                @endif

                                {{-- Aviso si el grupo cierra pronto --}}
                                @if(!$grupo->estaAbierto())
                                    <div class="p-2 bg-amber-50 border border-amber-300 rounded text-xs text-amber-800">
                                        Este grupo inicia en menos de 2 días. No se pueden agregar alumnos nuevos.
                                    </div>
                                @endif

                            </div>
                        @endif

                    </div>{{-- fin tarjeta grupo --}}
                @endforeach
            </div>
        @else
            <p class="text-gray-500 text-sm text-center py-6">No hay grupos formativos. Crea uno para empezar.</p>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- AUTÓNOMOS (2x1)                                --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">
                Autónomos
                <span class="text-sm font-normal text-gray-500 ml-1">(oferta 2x1)</span>
            </h3>
            <button wire:click="abrirFormAutonomo" class="px-3 py-1.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-medium">
                + Nuevo Autónomo
            </button>
        </div>

        {{-- Formulario crear matrícula autónoma --}}
        @if($mostrarFormAutonomo)
            <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <h4 class="font-semibold text-gray-800 mb-3">Nueva matrícula de autónomo</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Acción formativa --}}
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Acción Formativa *</label>
                        <input type="text" wire:model.live.debounce.300ms="autonomoBusquedaAccion"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-amber-500 focus:border-amber-500"
                               placeholder="Buscar por denominación o número...">
                        @if(!empty($autonomoResultadosAccion))
                            <div class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                @foreach($autonomoResultadosAccion as $r)
                                    <button type="button" wire:click="seleccionarAccionAutonomo({{ $r['id'] }})"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-amber-50 border-b border-gray-100 flex items-center justify-between gap-2">
                                        <span>{{ $r['label'] }}</span>
                                        @if(($r['plataforma'] ?? null) === 'a')
                                            <span class="flex-shrink-0 inline-flex px-1.5 py-0.5 text-xs rounded bg-amber-100 text-amber-700 font-medium">Aulasystem</span>
                                        @elseif(($r['plataforma'] ?? null) === 'm')
                                            <span class="flex-shrink-0 inline-flex px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-700 font-medium">Moodle</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @error('autonomoAccionFormativaId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Tutor --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tutor *</label>
                        <select wire:model="autonomoTutorId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Seleccionar tutor...</option>
                            @foreach($tutores as $tutor)
                                <option value="{{ $tutor->id }}">{{ $tutor->nombre_completo }} ({{ $tutor->tipo }})</option>
                            @endforeach
                        </select>
                        @error('autonomoTutorId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Alumno existente o nuevo --}}
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-3 mb-2">
                            <label class="block text-xs font-medium text-gray-700">Alumno *</label>
                            <button type="button" wire:click="$toggle('autonomoNuevoAlumno')"
                                    class="text-xs text-amber-700 hover:text-amber-900 underline underline-offset-2">
                                {{ $autonomoNuevoAlumno ? 'Seleccionar existente' : 'Crear nuevo alumno' }}
                            </button>
                        </div>

                        @if(!$autonomoNuevoAlumno)
                            <select wire:model="autonomoAlumnoId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-amber-500 focus:border-amber-500">
                                <option value="">Seleccionar alumno de la empresa...</option>
                                @foreach($alumnosParaAutonomo as $al)
                                    <option value="{{ $al->id }}">{{ $al->nombre_completo }} — {{ $al->nif }} — {{ $al->email ?? 'sin email' }}</option>
                                @endforeach
                            </select>
                            @error('autonomoAlumnoId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @else
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 p-3 bg-white border border-amber-200 rounded-lg">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nombre *</label>
                                    <input type="text" wire:model="autonomoNombre" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                    @error('autonomoNombre') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Apellido 1 *</label>
                                    <input type="text" wire:model="autonomoApellido1" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                    @error('autonomoApellido1') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Apellido 2</label>
                                    <input type="text" wire:model="autonomoApellido2" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">NIF *</label>
                                    <input type="text" wire:model="autonomoNif" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                    @error('autonomoNif') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                                    <input type="email" wire:model="autonomoEmail" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                    @error('autonomoEmail') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Teléfono</label>
                                    <input type="text" wire:model="autonomoTelefono" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Fechas --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Inicio</label>
                        <input type="date" wire:model="autonomoFechaInicio" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-amber-500 focus:border-amber-500">
                        @error('autonomoFechaInicio') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha Fin</label>
                        <input type="date" wire:model="autonomoFechaFin" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-amber-500 focus:border-amber-500">
                        @error('autonomoFechaFin') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Días (calcula fin)</label>
                        <input type="number" wire:model.live="autonomoDias" min="1" max="365"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-amber-500 focus:border-amber-500"
                               placeholder="Ej: 30">
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button wire:click="crearMatriculaAutonoma" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-medium">Crear Matrícula</button>
                    <button wire:click="$set('mostrarFormAutonomo', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Cancelar</button>
                </div>
            </div>
        @endif

        {{-- Lista de matrículas autónomas --}}
        @if($matriculasAutonomas->isNotEmpty())
            <div class="space-y-3">
                @foreach($matriculasAutonomas as $ma)
                    <div class="border rounded-lg border-gray-200 p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-gray-900">
                                        {{ $ma->alumno->nombre_completo }}
                                    </span>
                                    <span class="text-xs text-gray-500">{{ $ma->alumno->nif }}</span>
                                    @php
                                        $coloresEstadoAut = [
                                            'pendiente'   => 'bg-gray-100 text-gray-600',
                                            'matriculado' => 'bg-green-100 text-green-800',
                                            'error'       => 'bg-red-100 text-red-800',
                                        ];
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $coloresEstadoAut[$ma->estado] ?? '' }}">
                                        {{ ucfirst($ma->estado) }}
                                    </span>
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-700">
                                        Autónomo
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    #{{ $ma->accionFormativa->numero_accion }} — {{ Str::limit($ma->accionFormativa->denominacion_limpia, 50) }}
                                    &nbsp;|&nbsp; {{ $ma->tutor->nombre_completo }}
                                    @if($ma->fecha_inicio)
                                        &nbsp;|&nbsp; {{ $ma->fecha_inicio->format('d/m/Y') }}{{ $ma->fecha_fin ? ' – ' . $ma->fecha_fin->format('d/m/Y') : '' }}
                                    @endif
                                </div>
                                @if($ma->estado === 'error' && $ma->ultimo_error_moodle)
                                    <div class="text-xs text-red-600 mt-1">Error: {{ Str::limit($ma->ultimo_error_moodle, 120) }}</div>
                                @endif
                                @if($ma->moodle_username)
                                    <div class="text-xs text-gray-400 mt-1">Moodle: {{ $ma->moodle_username }}</div>
                                @endif
                            </div>

                            {{-- Botones --}}
                            <div class="flex gap-1 flex-shrink-0 ml-2">
                                <button wire:click="abrirEditarAlumno({{ $ma->alumno->id }})"
                                        class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-xs hover:bg-amber-200">
                                    Editar alumno
                                </button>
                                @if($ma->estado === 'pendiente' || $ma->estado === 'error')
                                    @php
                                        $cursosVinculadosAut = $ma->accionFormativa->moodleCursos()->with('tutores')->where('tipo', 'activa')->get();
                                        // Igual que en los grupos: solo si el aula no está ya resuelta por el tutor.
                                        $necesitaSelectorAut = $cursosVinculadosAut->count() > 1
                                            && !$ma->moodle_course_id
                                            && $cursosVinculadosAut->filter(fn ($cv) => $cv->tutores->contains('id', $ma->tutor_id))->count() !== 1;
                                    @endphp
                                    @if($necesitaSelectorAut)
                                        <select wire:model="moodleCursosPorAutonomo.{{ $ma->id }}"
                                                class="px-2 py-1 border border-gray-300 rounded text-xs">
                                            <option value="">Selecciona aula</option>
                                            @foreach($cursosVinculadosAut as $cv)
                                                <option value="{{ $cv->moodle_course_id }}">{{ $cv->moodle_fullname }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button wire:click="ejecutarEnMoodleAutonomo({{ $ma->id }})"
                                            wire:confirm="¿Matricular autónomo en Moodle?"
                                            class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                        {{ $ma->estado === 'error' ? 'Reintentar' : 'Matricular en Moodle' }}
                                    </button>
                                @endif
                                @if($ma->estado === 'pendiente')
                                    <button wire:click="eliminarMatriculaAutonoma({{ $ma->id }})"
                                            wire:confirm="¿Eliminar esta matrícula de autónomo?"
                                            class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">
                                        Eliminar
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Formulario edición inline del alumno autónomo --}}
                    @if($editandoAlumnoId === $ma->alumno->id)
                        <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-amber-700 uppercase mb-2">Editar alumno</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nombre *</label>
                                    <input type="text" wire:model="editAlumnoNombre" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                    @error('editAlumnoNombre') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Apellido 1 *</label>
                                    <input type="text" wire:model="editAlumnoApellido1" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                    @error('editAlumnoApellido1') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Apellido 2</label>
                                    <input type="text" wire:model="editAlumnoApellido2" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">NIF *</label>
                                    <input type="text" wire:model="editAlumnoNif" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                    @error('editAlumnoNif') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                                    <input type="email" wire:model="editAlumnoEmail" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                    @error('editAlumnoEmail') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Teléfono</label>
                                    <input type="text" wire:model="editAlumnoTelefono" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                </div>
                            </div>
                            <div class="flex gap-2 mt-2">
                                <button wire:click="actualizarAlumno" class="px-3 py-1 bg-amber-600 text-white rounded text-sm hover:bg-amber-700">Guardar</button>
                                <button wire:click="cerrarEditarAlumno" class="px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">Cancelar</button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <p class="text-gray-500 text-sm text-center py-4">No hay matrículas de autónomos. Crea una para empezar.</p>
        @endif
    </div>
</div>
