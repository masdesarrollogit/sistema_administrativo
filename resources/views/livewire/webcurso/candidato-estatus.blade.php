<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <a href="{{ route('webcurso.candidatos.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Volver a candidatos
            </a>
        </div>

        {{-- Mensaje Flash --}}
        @if (session()->has('message'))
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('message') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Información del Candidato --}}
            <div class="lg:col-span-1">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Información del Candidato</h3>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Nombre</label>
                            <p class="text-gray-900">{{ $candidato->nombre_contacto }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">Email</label>
                            <p class="text-gray-900">{{ $candidato->email }}</p>
                        </div>

                        @if($candidato->telefono)
                        <div>
                            <label class="text-sm font-medium text-gray-500">Teléfono</label>
                            <p class="text-gray-900">{{ $candidato->telefono }}</p>
                        </div>
                        @endif

                        <div>
                            <label class="text-sm font-medium text-gray-500">Tipo</label>
                            <p class="text-gray-900">{{ $candidato->tipoCandidato->nombre }}</p>
                        </div>

                        @if($candidato->empresa)
                        <div>
                            <label class="text-sm font-medium text-gray-500">Empresa</label>
                            <p class="text-gray-900">{{ $candidato->empresa->razon_social }}</p>
                            <p class="text-sm text-gray-500">{{ $candidato->empresa->cif }}</p>
                        </div>
                        @endif

                        @if($candidato->empresaExterna)
                        <div>
                            <label class="text-sm font-medium text-gray-500">Empresa Externa</label>
                            <p class="text-gray-900">{{ $candidato->empresaExterna->razon_social }}</p>
                            <p class="text-sm text-gray-500">{{ $candidato->empresaExterna->cif }}</p>
                        </div>
                        @endif

                        @if($candidato->curso_nombre)
                        <div>
                            <label class="text-sm font-medium text-gray-500">Curso</label>
                            <p class="text-gray-900">{{ $candidato->curso_nombre }}</p>
                        </div>
                        @endif

                        <div>
                            <label class="text-sm font-medium text-gray-500">Estatus</label>
                            @php
                                $badgeColors = [
                                    'pendiente' => 'bg-yellow-100 text-yellow-800',
                                    'completo' => 'bg-green-100 text-green-800',
                                    'desactivado' => 'bg-red-100 text-red-800',
                                    'pausado' => 'bg-gray-100 text-gray-800',
                                ];
                            @endphp
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $badgeColors[$candidato->estatus] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($candidato->estatus) }}
                            </span>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">Recordatorios Enviados</label>
                            <p class="text-gray-900">
                                {{ $candidato->recordatorios_enviados }} 
                                <span class="text-sm text-gray-500">
                                    / {{ config('candidatos.recordatorios.max_recordatorios', 5) }}
                                </span>
                            </p>
                        </div>

                        @if($candidato->ultimo_recordatorio)
                        <div>
                            <label class="text-sm font-medium text-gray-500">Último Recordatorio</label>
                            <p class="text-gray-900">{{ $candidato->ultimo_recordatorio->format('d/m/Y H:i') }}</p>
                            <p class="text-sm text-gray-500">{{ $candidato->ultimo_recordatorio->diffForHumans() }}</p>
                        </div>
                        @endif
                    </div>
                </div>


                {{-- Observación del Administrador --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mt-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Observación</h3>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <textarea wire:model.live.debounce.500ms="observacion" 
                                  rows="4" 
                                  class="w-full bg-transparent border-0 focus:ring-0 p-0 text-gray-700 text-sm resize-none"
                                  placeholder="Escribe aquí observaciones privadas sobre este candidato..."></textarea>
                    </div>
                    <div class="flex justify-end mt-2">
                        <button wire:click="guardarObservacion" 
                                class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-md font-bold uppercase transition shadow-sm flex items-center gap-2">
                            <span wire:loading.remove wire:target="guardarObservacion">Guardar Observación</span>
                            <span wire:loading wire:target="guardarObservacion">Guardando...</span>
                        </button>
                    </div>
                </div>

                {{-- Configuración de Envíos --}}
                <div class="bg-indigo-50 overflow-hidden shadow-sm sm:rounded-lg p-6 mt-6 border border-indigo-100">
                    <h3 class="text-sm font-black text-indigo-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                        ⚙️ Configuración Envíos
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-indigo-700 mb-1 uppercase">Fecha In. Envíos</label>
                            <input type="date" 
                                   wire:model.live="fecha_inicio" 
                                   class="w-full rounded-md border-indigo-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm bg-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-indigo-700 mb-1 uppercase">Frecuencia (Días)</label>
                            <div class="flex items-center gap-2">
                                <input type="number" 
                                       wire:model.live="frecuencia_envio" 
                                       min="1"
                                       class="w-20 rounded-md border-indigo-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-center text-sm bg-white">
                                <span class="text-xs text-indigo-600 italic">días entre correos</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-indigo-700 mb-1 uppercase">Mensaje Personalizado</label>
                            <textarea wire:model.live.debounce.500ms="descripcion_personalizada" 
                                      rows="3"
                                      class="w-full rounded-md border-indigo-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-xs bg-white"
                                      placeholder="Mensaje extra para el correo..."></textarea>
                        </div>

                        <div class="pt-4 border-t border-indigo-200">
                            <label class="block text-xs font-bold text-indigo-700 mb-2 uppercase">Archivos Adjuntos</label>
                            
                            @if(count($archivosGuardados) > 0)
                                <div class="mb-3 space-y-2">
                                    @foreach($archivosGuardados as $archivo)
                                        <div class="flex items-center justify-between p-2 bg-white border border-indigo-100 rounded shadow-sm">
                                            <div class="flex items-center gap-2 truncate">
                                                <span class="text-lg">📄</span>
                                                <span class="text-xs font-bold text-gray-700 truncate" title="{{ $archivo->nombre }}">{{ Str::limit($archivo->nombre, 20) }}</span>
                                            </div>
                                            <div class="flex items-center">
                                                <a href="{{ Storage::url($archivo->ruta) }}" target="_blank" class="text-indigo-500 hover:text-indigo-700 mr-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                </a>
                                                <button type="button" 
                                                        wire:click="eliminarArchivo({{ $archivo->id }})"
                                                        wire:confirm="¿Eliminar archivo?"
                                                        class="text-red-500 hover:text-red-700">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-indigo-300 border-dashed rounded-lg cursor-pointer bg-white hover:bg-indigo-50 transition-all">
                                    <div class="flex flex-col items-center justify-center pt-2 pb-3">
                                        <svg class="w-6 h-6 mb-1 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                        <p class="text-xs text-gray-500">Subir archivos</p>
                                    </div>
                                    <input type="file" wire:model="archivos" multiple class="hidden" />
                                </label>
                            </div>
                            
                            <div wire:loading wire:target="archivos" class="mt-1 text-xs text-indigo-600 animate-pulse">
                                ⏳ Subiendo...
                            </div>

                            @if ($archivos)
                                <div class="mt-2 text-xs text-indigo-600">
                                    {{ count($archivos) }} archivo(s) listo(s). Guardar Config para confirmar.
                                </div>
                            @endif
                        </div>
                        
                        <div class="flex justify-end mt-4">
                            <button wire:click="actualizarConfiguracion" 
                                    class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-md font-bold uppercase transition shadow-sm flex items-center gap-2">
                                <span wire:loading.remove wire:target="actualizarConfiguracion">Guardar Config</span>
                                <span wire:loading wire:target="actualizarConfiguracion">Guardando...</span>
                            </button>
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="mt-6 space-y-2">
                        @if($candidato->estatus === 'pendiente')
                            <button wire:click="pausarCandidato" 
                                    wire:key="btn-pausar-estatus"
                                    wire:confirm="¿Desea PAUSAR este candidato? No recibirá más recordatorios hasta que lo reactive."
                                    class="w-full flex items-center justify-center gap-2 bg-amber-50 text-amber-700 hover:bg-amber-100 px-4 py-3 rounded-lg transition-colors font-medium shadow-sm group">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Pausar Candidato
                            </button>
                        @endif

                        @if($candidato->estatus === 'pausado' || $candidato->estatus === 'desactivado')
                            <button wire:click="reactivarCandidato" 
                                     wire:key="btn-reactivar-estatus"
                                     wire:confirm="¿Desea REACTIVAR este candidato? Se reiniciará el envío de recordatorios."
                                     class="w-full flex items-center justify-center gap-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-4 py-3 rounded-lg transition-colors font-medium shadow-sm group">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Reactivar Candidato
                            </button>
                        @endif

                        @if($candidato->estatus !== 'desactivado')
                            <button wire:click="desactivarCandidato" 
                                    wire:key="btn-desactivar-estatus"
                                    wire:confirm="¿Desea DESACTIVAR este candidato? Podrás reactivarlo más adelante si es necesario."
                                    class="w-full flex items-center justify-center gap-2 bg-red-50 text-red-700 hover:bg-red-100 px-4 py-3 rounded-lg transition-colors font-medium shadow-sm group">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Desactivar Candidato
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Gestión de Requisitos --}}
            <div class="lg:col-span-2">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-6">Requisitos Administrativos</h3>

                    <div class="space-y-4">
                        @foreach($candidato->requisitos->sortBy('tipoRequisito.orden') as $requisito)
                            <div class="border rounded-lg p-4 {{ 
                                $requisito->estado === 'completado' ? 'bg-green-50 border-green-200' : 
                                ($requisito->estado === 'pendiente' ? 'bg-red-50 border-red-200' : 'bg-white border-gray-200') 
                            }}">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3">
                                            {{-- Icono de estado --}}
                                            @if($requisito->estado === 'completado')
                                                <span class="text-2xl">✅</span>
                                            @elseif($requisito->estado === 'en_proceso')
                                                <span class="text-2xl">🔄</span>
                                            @else
                                                <span class="text-2xl">❌</span>
                                            @endif

                                            <div>
                                                <h4 class="font-semibold text-gray-900">
                                                    {{ $requisito->tipoRequisito->nombre }}
                                                </h4>
                                                @if($requisito->tipoRequisito->descripcion)
                                                    <p class="text-sm text-gray-600">
                                                        {{ $requisito->tipoRequisito->descripcion }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Estado y fecha --}}
                                        <div class="mt-2 flex items-center gap-4">
                                            <span class="text-sm font-medium {{ $requisito->estado === 'completado' ? 'text-green-700' : 'text-gray-600' }}">
                                                Estado: {{ ucfirst(str_replace('_', ' ', $requisito->estado)) }}
                                            </span>
                                            @if($requisito->fecha_completado)
                                                <span class="text-sm text-gray-500">
                                                    Completado: {{ $requisito->fecha_completado->format('d/m/Y H:i') }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Notas --}}
                                        <div class="mt-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                                            <textarea wire:model="notas.{{ $requisito->id }}" 
                                                      rows="2"
                                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                      placeholder="Agregar notas sobre este requisito..."></textarea>
                                            <button wire:click="guardarNotas({{ $requisito->id }})" 
                                                    class="mt-1 text-sm text-indigo-600 hover:text-indigo-800">
                                                Guardar notas
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Botones de acción --}}
                                    <div class="ml-4 flex flex-col gap-2">
                                        @if($requisito->estado !== 'completado')
                                            <button wire:click="marcarCompletado({{ $requisito->id }})" 
                                                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition">
                                                ✓ Completar
                                            </button>
                                        @endif

                                        @if($requisito->estado !== 'en_proceso')
                                            <button wire:click="marcarEnProceso({{ $requisito->id }})" 
                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition">
                                                🔄 En Proceso
                                            </button>
                                        @endif

                                        @if($requisito->estado !== 'pendiente')
                                            <button wire:click="marcarPendiente({{ $requisito->id }})" 
                                                    class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm transition">
                                                ⏳ Pendiente
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Resumen --}}
                    @php
                        $total = $candidato->requisitos->count();
                        $completados = $candidato->requisitos->where('estado', 'completado')->count();
                        $porcentaje = $total > 0 ? round(($completados / $total) * 100) : 0;
                    @endphp
                    <div class="mt-6 p-4 bg-indigo-50 rounded-lg">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-medium text-gray-700">Progreso General</span>
                            <span class="text-sm text-gray-600">{{ $completados }}/{{ $total }} completados</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="bg-indigo-600 h-3 rounded-full transition-all duration-300" style="width: {{ $porcentaje }}%"></div>
                        </div>
                        <div class="mt-2 text-center text-2xl font-bold text-indigo-600">
                            {{ $porcentaje }}%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
