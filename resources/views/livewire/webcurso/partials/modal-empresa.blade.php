@php
    $viewConfig = config("webcurso.vistas.$viewName");
@endphp

@if($mostrarModalEmpresa)
    <div class="fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 overflow-y-auto" wire:click.self="cerrarModalEmpresa">
        <div class="flex items-start justify-center min-h-screen p-4 py-10">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in duration-200">
                {{-- Header --}}
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center sticky top-0 z-10">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        🏢 Detalle de Empresa
                    </h3>
                    <button wire:click="cerrarModalEmpresa" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="p-6 pb-12">
                @if($empresaParaEmail)
                    {{-- Info Empresa Card --}}
                    <div class="bg-blue-50 rounded-xl p-5 mb-6 border border-blue-100 shadow-sm">
                        <p class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-1">Empresa Seleccionada</p>
                        <p class="font-black text-gray-900 text-xl leading-tight mb-4">{{ $empresaParaEmail['razon_social'] }}</p>
                        
                        {{-- Campos dinámicos del Modal según configuración --}}
                        <div class="grid grid-cols-1 gap-y-3 gap-x-4">
                            @foreach($viewConfig['campos_modal'] as $field => $label)
                                <div class="flex justify-between items-center py-1.5 border-b border-blue-100/50 last:border-0">
                                    <span class="text-xs font-bold text-gray-500 uppercase">{{ $label }}</span>
                                    <span class="text-sm font-bold text-gray-900">
                                        @if(str_contains($field, 'credito') || str_contains($field, 'importe') || str_contains($field, 'cofinanciacion'))
                                            {{ number_format($empresaParaEmail[$field] ?? 0, 2, ',', '.') }} €
                                        @elseif($field === 'plantilla_media')
                                            {{ number_format($empresaParaEmail[$field] ?? 0, 0) }}
                                        @elseif($field === 'pyme' || $field === 'nueva_creacion' || $field === 'bloqueada')
                                            @php
                                                $val = strtoupper($empresaParaEmail[$field] ?? '');
                                                $isPositive = str_contains($val, 'SI');
                                                $colorClass = $field === 'bloqueada' 
                                                    ? ($isPositive ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700')
                                                    : ($isPositive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600');
                                            @endphp
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $colorClass }}">
                                                {{ $empresaParaEmail[$field] ?? 'NO' }}
                                            </span>
                                        @elseif($field === 'email')
                                            <a href="mailto:{{ $empresaParaEmail[$field] }}" class="text-blue-600 hover:underline">{{ $empresaParaEmail[$field] ?? '-' }}</a>
                                        @else
                                            {{ $empresaParaEmail[$field] ?? '-' }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                            
                            {{-- Siempre mostrar el saldo disponible por defecto si no está en la lista --}}
                            @if(!isset($viewConfig['campos_modal']['credito_disponible']))
                                <div class="flex justify-between items-center py-2 mt-2 bg-white/50 px-3 rounded-lg border border-blue-100/30">
                                    <span class="text-xs font-black text-blue-600 uppercase">Saldo Disponible</span>
                                    <span class="text-lg font-black text-green-600">{{ $empresaParaEmail['saldo_formateado'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Sección Candidato (Solo si está activo en el config) --}}
                    @if($viewConfig['modulo_candidato'])
                        <div class="mb-8 p-6 border-2 border-dashed border-gray-200 rounded-2xl bg-white">
                            <h4 class="text-sm font-black text-gray-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                                👤 Registrar Nuevo Candidato
                            </h4>
                            <div class="space-y-5">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 mb-2 uppercase tracking-tighter">Nombre del Alumno/Contacto</label>
                                    <input type="text" wire:model="nombreCandidato" 
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                           placeholder="Ej: Juan Pérez">
                                    @error('nombreCandidato') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 mb-2 uppercase tracking-tighter">Email de Notificación</label>
                                    <input type="email" wire:model="emailCandidato" 
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                           placeholder="usuario@empresa.com">
                                    @error('emailCandidato') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                
                                @if($mensajeCandidato)
                                    <div class="p-3 rounded-xl text-xs font-bold {{ str_contains($mensajeCandidato, '✅') ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100' }}">
                                        {{ $mensajeCandidato }}
                                    </div>
                                @endif

                                <div class="pt-2">
                                    <button type="button" 
                                            wire:click="guardarCandidato" 
                                            class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black text-xs uppercase tracking-widest transition-all">
                                        Registrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Sección Saldo (Solo si está activo en el config) --}}
                    @if($viewConfig['modulo_saldo'])
                        <div class="pt-8 mt-4 border-t-2 border-gray-100">
                            <h4 class="text-sm font-black text-gray-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                                📧 Notificación de Saldo
                            </h4>
                            
                            <div class="p-4 bg-blue-50 rounded-xl border border-blue-100 mb-6">
                                <p class="text-[11px] text-blue-700 font-bold italic">
                                    ℹ️ Se enviará el saldo actual a administración (@webcurso.es)
                                </p>
                            </div>

                            @if($mensajeEmail)
                                <div class="mb-6 p-4 rounded-xl text-xs font-bold {{ str_contains($mensajeEmail, '✅') ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100' }}">
                                    {{ $mensajeEmail }}
                                </div>
                            @endif

                            <button type="button"
                                    wire:click="enviarSaldo" 
                                    class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-black text-xs uppercase tracking-widest transition-all">
                                Enviar Saldo
                            </button>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endif
