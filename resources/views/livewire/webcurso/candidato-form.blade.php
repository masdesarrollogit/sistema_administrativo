<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('webcurso.candidatos.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Volver a candidatos
            </a>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    {{ $isEdit ? 'Editar Candidato' : 'Nuevo Candidato' }}
                </h2>

                @if (session()->has('message'))
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        {{ session('message') }}
                    </div>
                @endif

                @if (session()->has('warning'))
                    <div class="mb-6 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative">
                        {{ session('warning') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        {{ session('error') }}
                    </div>
                @endif

                <form wire:submit="save">
                    {{-- Tipo de Candidato --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo de Candidato <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="tipo_candidato_id" 
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                            <option value="">Seleccionar tipo...</option>
                            @foreach($tiposCandidato as $tipo)
                                <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                            @endforeach
                        </select>
                        @error('tipo_candidato_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    {{-- Datos de Empresa (si aplica) --}}
                    {{-- Datos de Empresa (si aplica) --}}
                    <div>
                        @if($requiereEmpresa)
                            <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <h3 class="font-semibold text-gray-800 mb-4">Datos de la Empresa</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Razón Social <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" 
                                               wire:model.live.debounce.300ms="razon_social_empresa" 
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                               placeholder="Nombre de la empresa"
                                               autocomplete="off"
                                               required>
                                        
                                        {{-- Dropdown Autocomplete Empresa --}}
                                        @if($showEmpresaDropdown && count($empresaResults) > 0)
                                            <div class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                                @foreach($empresaResults as $empresa)
                                                    <button type="button" 
                                                            wire:key="empresa-{{ $empresa->id }}"
                                                            wire:click="seleccionarEmpresa({{ $empresa->id }})"
                                                            class="w-full text-left px-4 py-2 hover:bg-indigo-50 transition-colors border-b border-gray-50 last:border-0">
                                                        <div class="font-medium text-gray-900 text-sm">{{ $empresa->razon_social }}</div>
                                                        <div class="text-xs text-gray-500">{{ $empresa->cif }}</div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                        @error('razon_social_empresa') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            CIF <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex gap-2">
                                            <input type="text" 
                                                   wire:model="cif_empresa" 
                                                   class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                   placeholder="A12345678"
                                                   required>
                                            <button type="button" 
                                                    wire:click="buscarEmpresaPorCif"
                                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md transition">
                                                🔍 Buscar
                                            </button>
                                        </div>
                                        @error('cif_empresa') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Datos del Contacto --}}
                    <div class="mb-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Datos del Contacto</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre del Contacto <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       wire:model="nombre_contacto" 
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       required>
                                @error('nombre_contacto') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" 
                                       wire:model="email" 
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       required>
                                @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Teléfono
                                </label>
                                <input type="text" 
                                       wire:model="telefono" 
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('telefono') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Datos del Curso --}}
                    <div class="mb-6">
                        <h3 class="font-black text-gray-800 uppercase tracking-widest text-sm mb-4 border-b pb-2">📚 Datos del Curso</h3>
                        
                        <div class="relative">
                            <label class="block text-xs font-black text-gray-500 uppercase mb-2 tracking-tighter">
                                Nombre del Curso
                            </label>
                            <input type="text" 
                                   wire:model.live.debounce.300ms="curso_nombre" 
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="Empieza a escribir el curso..."
                                   autocomplete="off">
                            
                            {{-- Dropdown Autocomplete --}}
                            @if($showDropdown && count($results) > 0)
                                <div class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto overflow-x-hidden">
                                    @foreach($results as $curso)
                                        <button type="button" 
                                                wire:key="curso-{{ $curso->id }}"
                                                wire:click="seleccionarCurso({{ $curso->id }})"
                                                class="w-full text-left px-4 py-3 hover:bg-indigo-50 transition-colors border-b border-gray-50 last:border-0 group">
                                            <div class="font-bold text-gray-900 text-sm group-hover:text-indigo-700">{{ $curso->titulo }}</div>
                                            <div class="flex justify-between items-center mt-1">
                                                <span class="text-[10px] bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-black uppercase">{{ $curso->categoria->nombre }}</span>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            @error('curso_nombre') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Notas --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Notas internas (no se envían)
                        </label>
                        <textarea wire:model="notas" 
                                  rows="2"
                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Información adicional interna..."></textarea>
                        @error('notas') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>



                    {{-- Botones --}}
                    <div class="flex justify-end gap-4 pt-6 mt-6 border-t border-gray-100">
                        <a href="{{ route('webcurso.candidatos.index') }}" 
                           class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium shadow-sm">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Cancelar
                        </a>
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                class="flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm">
                            <svg class="w-5 h-5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            {{ $isEdit ? 'Actualizar' : 'Crear' }} Candidato
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
