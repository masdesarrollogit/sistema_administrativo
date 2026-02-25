<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Encabezado --}}
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                💰 Participantes Bonificados
            </h1>
            <p class="mt-1 text-gray-500 text-sm">Reporte de participantes con formación bonificada FUNDAE</p>
        </div>

        {{-- Navegación --}}
        <div class="mb-6 flex flex-wrap gap-3">
            <a href="{{ route('webcurso.dashboard') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                🏠 Panel Principal
            </a>
            <a href="{{ route('webcurso.importar') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm">
                📤 Importar Archivos
            </a>
        </div>

        {{-- Estadísticas --}}
        <div class="bg-green-50 rounded-xl p-4 mb-6 text-center border border-green-100">
            <span class="text-lg">
                👤 <strong>Total participantes:</strong> {{ number_format($stats['total']) }} &nbsp;|&nbsp;
                🏢 <strong>Empresas únicas (CIF):</strong> {{ number_format($stats['cif_unicos']) }}
            </span>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">🔍 Filtrar Participantes</h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroNombre"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500"
                           placeholder="Buscar nombre...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">NIF Participante</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroNif"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500"
                           placeholder="NIF...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">CIF Empresa</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroCif"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500"
                           placeholder="CIF...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
                    <select wire:model.live="filtroEstado"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500">
                        <option value="">Todos</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="En curso">En curso</option>
                        <option value="Anulado">Anulado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Código Grupo</label>
                    <input type="text" wire:model.live.debounce.300ms="filtroGrupo"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500"
                           placeholder="Grupo...">
                </div>
                <div class="flex items-end">
                    <button wire:click="limpiarFiltros"
                            class="w-full px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm">
                        🗑️ Limpiar
                    </button>
                </div>
            </div>
        </div>

        {{-- Paginación superior --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <label class="text-sm text-gray-600">Mostrar:</label>
                <select wire:model.live="perPage"
                        class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-sm text-gray-600">
                    Mostrando {{ $participantes->firstItem() ?? 0 }} - {{ $participantes->lastItem() ?? 0 }} de {{ $participantes->total() }}
                </span>
            </div>
            <div>
                {{ $participantes->links() }}
            </div>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIF Participante</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">NISS</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-green-50">CIF Empresa</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Código Grupo</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código PIF</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Inicio</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Fin</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado Grupo</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($participantes as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500 text-xs">{{ $p->id }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-900">{{ $p->nif_participante }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-600 text-xs">{{ $p->niss }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-semibold text-gray-900">{{ $p->nombre }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold
                                        {{ $p->estado === 'Finalizado' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $p->estado === 'En curso' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $p->estado === 'Anulado' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ !in_array($p->estado, ['Finalizado','En curso','Anulado']) ? 'bg-gray-100 text-gray-700' : '' }}
                                    ">
                                        {{ $p->estado }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap bg-green-50">
                                    @if($p->cif)
                                        <button
                                            wire:click="abrirModal('{{ $p->cif }}')"
                                            class="font-mono font-bold text-green-700 hover:text-green-900 hover:underline cursor-pointer transition-colors"
                                            title="Ver saldo de la empresa">
                                            {{ $p->cif }}
                                        </button>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $p->id_codigo_grupo }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $p->codigo_pif }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ $p->fecha_inicio ? $p->fecha_inicio->format('d/m/Y') : '—' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ $p->fecha_fin ? $p->fecha_fin->format('d/m/Y') : '—' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="font-semibold
                                        {{ $p->estado_grupo === 'Finalizado' ? 'text-green-600' : '' }}
                                        {{ $p->estado_grupo === 'Válido' ? 'text-blue-600' : '' }}
                                        {{ $p->estado_grupo === 'Modificado' ? 'text-orange-500' : '' }}
                                    ">{{ $p->estado_grupo }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-gray-500">
                                    <div class="text-4xl mb-3">💰</div>
                                    <p class="font-semibold">No hay participantes bonificados cargados</p>
                                    <p class="text-sm mt-1">
                                        <a href="{{ route('webcurso.importar') }}" class="text-green-600 hover:underline">Importar archivo XLS de FUNDAE</a>
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Paginación inferior --}}
        <div class="mt-4">
            {{ $participantes->links() }}
        </div>

    </div>

    {{-- ========== MODAL EMPRESA ========== --}}
    @if($mostrarModal)
        <div class="fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 overflow-y-auto"
             wire:click.self="cerrarModal">
            <div class="flex items-start justify-center min-h-screen p-4 py-10">
                <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">

                    {{-- Header --}}
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center sticky top-0 z-10">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            🏢 Detalle de Empresa
                        </h3>
                        <button wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 pb-10">
                        @if($empresaModal)
                            {{-- Card empresa --}}
                            <div class="bg-blue-50 rounded-xl p-5 mb-6 border border-blue-100 shadow-sm">
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-1">Empresa</p>
                                <p class="font-black text-gray-900 text-xl leading-tight mb-4">
                                    {{ $empresaModal['razon_social'] }}
                                </p>

                                <div class="grid grid-cols-1 gap-y-3">
                                    <div class="flex justify-between items-center py-1.5 border-b border-blue-100/50">
                                        <span class="text-xs font-bold text-gray-500 uppercase">CIF / NIF</span>
                                        <span class="text-sm font-bold font-mono text-gray-900">{{ $empresaModal['cif'] }}</span>
                                    </div>

                                    <div class="flex justify-between items-center py-2 mt-1 bg-white/60 px-3 rounded-lg border border-blue-100/30">
                                        <span class="text-xs font-black text-blue-600 uppercase">Saldo Disponible</span>
                                        @if($empresaModal['credito_disponible'] !== null)
                                            <span class="text-xl font-black text-green-600">
                                                {{ $empresaModal['saldo_formateado'] }}
                                            </span>
                                        @else
                                            <span class="text-sm font-bold text-gray-400 italic">
                                                Empresa no registrada
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Sección envío de email --}}
                            @if($empresaModal['credito_disponible'] !== null)
                                <div class="pt-4 border-t-2 border-gray-100">
                                    <h4 class="text-sm font-black text-gray-800 uppercase tracking-widest mb-4 flex items-center gap-2">
                                        📧 Notificación de Saldo
                                    </h4>

                                    <div class="p-4 bg-blue-50 rounded-xl border border-blue-100 mb-4">
                                        <p class="text-[11px] text-blue-700 font-bold">
                                            ✉️ Se enviará a <strong>webcurso@webcurso.es</strong><br>
                                            con copia a <strong>administracion@webcurso.es</strong> y <strong>prospectos@webcurso.es</strong>
                                        </p>
                                    </div>

                                    @if($mensajeEmail)
                                        <div class="mb-4 p-4 rounded-xl text-xs font-bold
                                            {{ str_contains($mensajeEmail, '✅') ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100' }}">
                                            {{ $mensajeEmail }}
                                        </div>
                                    @endif

                                    <button type="button"
                                            wire:click="enviarSaldo"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-black text-xs uppercase tracking-widest transition-all">
                                        <span wire:loading.remove wire:target="enviarSaldo">📧 Enviar Saldo</span>
                                        <span wire:loading wire:target="enviarSaldo" style="display:none">Enviando...</span>
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
