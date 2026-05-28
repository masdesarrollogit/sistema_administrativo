@if($mostrarModalNotas)
    <div class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6"
         wire:keydown.escape="cerrarNotas">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-black/40" wire:click="cerrarNotas"></div>

        {{-- Panel --}}
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl mt-12 max-h-[85vh] flex flex-col">

            {{-- Cabecera --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-gray-900 truncate">
                            Notas — {{ $razonSocialNotas }}
                        </h3>
                        <p class="text-xs text-gray-500 font-mono">{{ $cifNotas }}</p>
                    </div>
                </div>
                <button type="button"
                        wire:click="cerrarNotas"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Lista de notas (scroll) --}}
            <div class="flex-grow overflow-y-auto px-5 py-4 space-y-3 bg-gray-50/40">
                @if(count($notasLista) === 0)
                    <div class="text-center py-10 text-sm text-gray-500">
                        <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Sin notas todavía. Añade la primera abajo.
                    </div>
                @else
                    <p class="text-xs text-gray-400 uppercase tracking-wide">{{ count($notasLista) }} {{ count($notasLista) === 1 ? 'nota' : 'notas' }} · más recientes primero</p>
                    @foreach($notasLista as $n)
                        <div class="bg-white rounded-md border border-gray-200 p-3 shadow-sm">
                            @if($n['title'])
                                <h4 class="text-sm font-semibold text-gray-900 mb-1">{{ $n['title'] }}</h4>
                            @endif
                            <p class="text-sm text-gray-700 whitespace-pre-line break-words">{{ $n['body'] }}</p>

                            <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                                <div class="flex items-center gap-1.5" title="{{ $n['fecha_full'] }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span>{{ $n['autor'] }}</span>
                                    <span class="text-gray-300">·</span>
                                    <span>{{ $n['fecha'] }}</span>
                                    @if($n['editada'])
                                        <span class="text-gray-300">·</span>
                                        <span class="italic">editada</span>
                                    @endif
                                </div>
                                @if($n['es_propia'])
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                wire:click="editarNota({{ $n['id'] }})"
                                                class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                            Editar
                                        </button>
                                        <button type="button"
                                                wire:click="eliminarNota({{ $n['id'] }})"
                                                wire:confirm="¿Eliminar esta nota? No se puede deshacer."
                                                class="text-red-600 hover:text-red-800 hover:underline">
                                            Eliminar
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Formulario --}}
            <form wire:submit.prevent="guardarNota" class="border-t border-gray-200 px-5 py-4 bg-white rounded-b-lg">
                @if($notaForm['id'])
                    <div class="mb-3 flex items-center justify-between text-xs">
                        <span class="text-indigo-600 font-medium">Editando nota #{{ $notaForm['id'] }}</span>
                        <button type="button"
                                wire:click="cancelarEdicion"
                                class="text-gray-500 hover:text-gray-700 hover:underline">
                            Cancelar edición
                        </button>
                    </div>
                @endif

                <div class="space-y-2">
                    <input type="text"
                           wire:model="notaForm.title"
                           placeholder="Título (opcional)"
                           maxlength="150"
                           class="block w-full text-sm border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">
                    @error('notaForm.title') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <textarea wire:model="notaForm.body"
                              rows="3"
                              placeholder="Descripción de la nota… (ej. 'Llamé y no contesta, vuelvo a probar mañana')"
                              maxlength="5000"
                              class="block w-full text-sm border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 resize-y"></textarea>
                    @error('notaForm.body') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-3 flex items-center justify-end gap-2">
                    <button type="button"
                            wire:click="cerrarNotas"
                            class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cerrar
                    </button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="guardarNota"
                            class="px-4 py-1.5 text-sm font-medium bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-60 shadow-sm">
                        <span wire:loading.remove wire:target="guardarNota">
                            {{ $notaForm['id'] ? 'Guardar cambios' : 'Guardar nota' }}
                        </span>
                        <span wire:loading wire:target="guardarNota">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
