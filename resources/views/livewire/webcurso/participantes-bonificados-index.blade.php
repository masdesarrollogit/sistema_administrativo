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
                🏢 <strong>Empresas únicas (CIF):</strong> {{ number_format($stats['cif_unicos']) }} &nbsp;|&nbsp;
                ✅ <strong>Finalizados:</strong> {{ number_format($stats['finalizados']) }} &nbsp;|&nbsp;
                🚫 <strong>Excluidos email:</strong> {{ number_format($stats['excluidos']) }}
            </span>
        </div>

        {{-- Banner: NIFs sin email --}}
        @if($stats['sin_email'] > 0 || $stats['datos_pendientes'] > 0)
            <div class="bg-amber-50 border-l-4 border-amber-400 rounded-xl p-4 mb-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="text-sm text-amber-800">
                        <p class="font-semibold mb-1">⚠ Participantes con datos incompletos</p>
                        <p>
                            <strong>{{ number_format($stats['sin_email']) }}</strong> participantes sin email registrado en alumnos
                            @if($stats['datos_pendientes'] > 0)
                                · <strong>{{ number_format($stats['datos_pendientes']) }}</strong> alumnos marcados como datos pendientes
                            @endif
                        </p>
                    </div>
                    <button wire:click="reejecutarEnriquecimiento"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            wire:target="reejecutarEnriquecimiento"
                            class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 text-sm font-semibold transition-colors">
                        <span wire:loading.remove wire:target="reejecutarEnriquecimiento">🔄 Reejecutar enriquecimiento</span>
                        <span wire:loading wire:target="reejecutarEnriquecimiento" style="display:none">Procesando...</span>
                    </button>
                </div>
                @if($resumenReenriquecimiento)
                    <pre class="mt-3 p-3 bg-white text-xs text-gray-700 rounded border border-amber-200 whitespace-pre-wrap max-h-60 overflow-y-auto">{{ $resumenReenriquecimiento }}</pre>
                @endif
            </div>
        @endif

        {{-- Panel de email mensual de saldo --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-blue-100">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        📧 Email mensual de saldo
                        @if($cronActivo)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-green-100 text-green-700">Activo</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-700">Inactivo</span>
                        @endif
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700">
                            {{ $cronFrecuencia === 'weekly' ? 'Semanal' : ($cronFrecuencia === 'biweekly' ? 'Quincenal' : 'Mensual') }}
                        </span>
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Envía el saldo de la empresa a cada participante bonificado finalizado que tenga email en la tabla de alumnos.
                    </p>
                    <div class="mt-2 text-xs space-y-1">
                        @if($proximoEnvio)
                            <div class="text-gray-700">
                                📅 <strong>Próximo envío programado:</strong>
                                {{ $proximoEnvio->format('d/m/Y H:i') }} (Madrid)
                                <span class="text-gray-400">— en {{ $proximoEnvio->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</span>
                            </div>
                        @endif
                        <div class="text-gray-700">
                            📨 <strong>Copias enviadas a:</strong>
                            <span class="font-mono text-[11px]">empresas.email</span>
                            @if($ccAdmin)
                                + <span class="font-mono text-[11px]">{{ $ccAdmin }}</span>
                            @endif
                        </div>
                        @if($modoPrueba)
                            <div class="text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 inline-block">
                                🧪 <strong>MODO PRUEBA:</strong> los emails se redirigen a Mailpit, no se envían a destinatarios reales ni a las empresas.
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    <button wire:click="enviarEmailSaldoPrueba"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            wire:target="enviarEmailSaldoPrueba"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm transition-colors">
                        <span wire:loading.remove wire:target="enviarEmailSaldoPrueba">🔍 Prueba (dry-run)</span>
                        <span wire:loading wire:target="enviarEmailSaldoPrueba" style="display:none">Ejecutando...</span>
                    </button>
                    <button wire:click="enviarEmailSaldoMasivo"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            wire:target="enviarEmailSaldoMasivo"
                            wire:confirm="¿Enviar emails de saldo a todos los participantes bonificados finalizados?"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm transition-colors">
                        <span wire:loading.remove wire:target="enviarEmailSaldoMasivo">📧 Enviar ahora</span>
                        <span wire:loading wire:target="enviarEmailSaldoMasivo" style="display:none">Enviando...</span>
                    </button>
                </div>
            </div>

            @if($resultadoEnvioMasivo)
                <div class="mt-4 p-3 rounded-lg text-xs font-mono whitespace-pre-wrap max-h-48 overflow-y-auto
                    {{ str_contains($resultadoEnvioMasivo, '❌') ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-gray-50 text-gray-700 border border-gray-200' }}">
                    {{ $resultadoEnvioMasivo }}
                </div>
            @endif
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
            <div class="mt-3 flex items-center gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" wire:model.live="filtroSinEmail" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    Solo participantes <strong class="text-amber-600">sin email</strong>
                </label>
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
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-blue-50">Email</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-blue-50">Último envío</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase bg-blue-50">Envío</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($participantes as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500 text-xs">{{ $p->id }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-900">{{ $p->nif_participante }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-600 text-xs">{{ $p->niss }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-semibold text-gray-900">
                                    {{ $p->nombre }}
                                    @if(in_array($p->nif_participante, $datosPendientesPorNif ?? []))
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700" title="El alumno asociado tiene datos_pendientes=true">
                                            ⚠ datos pendientes
                                        </span>
                                    @endif
                                </td>
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
                                <td class="px-3 py-2 whitespace-nowrap bg-blue-50 text-xs">
                                    @if(isset($emailsPorNif[$p->nif_participante]))
                                        <span class="text-gray-700">{{ $emailsPorNif[$p->nif_participante] }}</span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700" title="No hay alumno con email para este NIF">
                                            📧 sin email
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap bg-blue-50 text-xs">
                                    @php $ultimo = $ultimosEnviosPorNif[$p->nif_participante] ?? null; @endphp
                                    @if($ultimo)
                                        <span class="text-gray-700"
                                              title="{{ $ultimo->format('d/m/Y H:i') }} (Madrid)">
                                            {{ $ultimo->format('d/m/Y') }}
                                        </span>
                                        <div class="text-[10px] text-gray-400">{{ $ultimo->diffForHumans() }}</div>
                                    @else
                                        <span class="text-gray-400 italic">Nunca</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap bg-blue-50 text-center">
                                    @if($p->nif_participante)
                                        @if(in_array($p->nif_participante, $nifsExcluidos))
                                            <button wire:click="reactivarParticipante('{{ $p->nif_participante }}')"
                                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 hover:bg-red-200 transition-colors"
                                                    title="Reactivar envío de email">
                                                🚫 Excluido
                                            </button>
                                        @else
                                            <button wire:click="excluirParticipante('{{ $p->nif_participante }}', '{{ addslashes($p->nombre) }}')"
                                                    wire:confirm="¿Excluir a {{ $p->nombre }} del envío de emails de saldo?"
                                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700 hover:bg-green-200 transition-colors"
                                                    title="Detener envío de email a este participante">
                                                ✅ Activo
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="px-4 py-12 text-center text-gray-500">
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
