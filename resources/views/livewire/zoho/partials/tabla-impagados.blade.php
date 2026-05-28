@if(!$estadoConexion || !($estadoConexion['conectado'] ?? false))
    <p class="text-center text-gray-500 py-12">Conecta primero con Zoho Books desde la pestaña Resumen.</p>
@else
    <div class="mb-4 rounded-md bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
        <p class="font-medium">Participantes bonificados con factura impagada</p>
        <p class="mt-1 text-blue-700">
            El cruce se hace por <code class="font-mono">cf_cif</code> de la factura Zoho Books ↔ <code class="font-mono">cif</code> de
            <code class="font-mono">participantes_bonificados</code>. Las facturas se piden en vivo a Zoho con filtro
            <code class="font-mono">Status.Unpaid</code> (incluye sent, partially_paid, overdue).
        </p>
    </div>

    @if(empty($impagados) && empty($impagadosSinParticipantes))
        <p class="text-center text-gray-500 py-12">No hay facturas impagadas con CIF identificable.</p>
    @endif

    {{-- Tabla principal: participantes con factura impagada --}}
    @if(!empty($impagados))
        <div class="mb-4 flex items-center justify-end">
            <button wire:click="descargarImpagadosXlsx"
                    wire:loading.attr="disabled"
                    wire:target="descargarImpagadosXlsx"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                <svg wire:loading.remove wire:target="descargarImpagadosXlsx" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <svg wire:loading wire:target="descargarImpagadosXlsx" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <span wire:loading.remove wire:target="descargarImpagadosXlsx">Descargar XLSX</span>
                <span wire:loading wire:target="descargarImpagadosXlsx">Generando…</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Participante</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grupo</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">CIF</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Notas</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contacto empresa</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total impagado</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Facturas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($impagados as $i)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <div class="text-sm font-medium text-gray-900">{{ $i['nombre'] ?? '—' }}</div>
                                @if(!empty($i['nif_participante']))
                                    <div class="text-xs font-mono text-gray-500 mt-0.5">{{ $i['nif_participante'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                {{ $i['id_codigo_grupo'] ?? '' }}
                                @if($i['fecha_inicio'] || $i['fecha_fin'])
                                    <div class="text-xs text-gray-400">
                                        {{ $i['fecha_inicio'] ?? '?' }} → {{ $i['fecha_fin'] ?? '?' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700">{{ $i['razon_social'] }}</td>
                            <td class="px-4 py-2 text-sm font-mono text-gray-700">{{ $i['cif'] }}</td>
                            <td class="px-4 py-2 text-center">
                                @if(!empty($i['empresa_id']))
                                    <button type="button"
                                            wire:click="abrirNotas({{ (int) $i['empresa_id'] }}, @js($i['cif']), @js($i['razon_social']))"
                                            title="Notas de seguimiento de la empresa"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium transition-colors
                                                   {{ ($i['notas_count'] ?? 0) > 0
                                                      ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                                                      : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        <span>{{ $i['notas_count'] ?? 0 }}</span>
                                    </button>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500">
                                @foreach($i['emails'] ?? [] as $em)
                                    <div class="flex items-center gap-1.5">
                                        <a href="mailto:{{ $em['email'] }}"
                                           class="text-gray-400 hover:text-indigo-600 transition-colors"
                                           onclick="event.stopPropagation()">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </a>
                                        <span>{{ $em['email'] }}</span>
                                        <span class="text-gray-400">
                                            · {{ $em['fuente'] }}@if(!empty($em['nombre'])) — <span class="text-gray-600">{{ $em['nombre'] }}</span>@endif
                                        </span>
                                    </div>
                                @endforeach
                                @foreach($i['telefonos'] ?? [] as $tel)
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <a href="tel:{{ $tel['e164'] }}"
                                           class="text-gray-400 hover:text-indigo-600 transition-colors"
                                           onclick="event.stopPropagation()">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 7V5z"/>
                                            </svg>
                                        </a>
                                        <span>{{ $tel['raw'] }}</span>
                                        <span class="text-gray-400">
                                            · {{ $tel['fuente'] }}@if(!empty($tel['nombre'])) — <span class="text-gray-600">{{ $tel['nombre'] }}</span>@endif
                                        </span>
                                    </div>
                                @endforeach
                                @if(empty($i['emails']) && empty($i['telefonos']))
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-right font-mono font-semibold text-red-700">
                                {{ number_format((float)$i['total_impagado'], 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-2 text-sm text-right">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs font-medium">
                                    {{ $i['num_facturas'] }}
                                    @if(!empty($i['facturas']))
                                        <span class="text-red-600">·</span>
                                        <span class="font-mono">{{ collect($i['facturas'])->pluck('invoice_number')->take(2)->implode(', ') }}{{ count($i['facturas']) > 2 ? '…' : '' }}</span>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3 text-sm text-gray-500">
            {{ count($impagados) }} participantes · {{ collect($impagados)->unique('cif')->count() }} empresas · total impagado
            <span class="font-mono font-semibold text-red-700">
                {{ number_format(collect($impagados)->unique('cif')->sum('total_impagado'), 2, ',', '.') }}
            </span>
        </div>
    @endif

    {{-- Bloque diagnóstico: empresas con factura impagada pero sin participantes bonificados --}}
    @if(!empty($impagadosSinParticipantes))
        <details class="mt-6">
            <summary class="cursor-pointer text-sm font-medium text-gray-600 hover:text-gray-900">
                {{ count($impagadosSinParticipantes) }} empresa(s) con factura impagada pero <span class="underline">sin</span> participantes bonificados ligados
            </summary>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">CIF</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Nº facturas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($impagadosSinParticipantes as $r)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ $r['customer_name'] }}</td>
                                <td class="px-4 py-2 text-sm font-mono text-gray-700">{{ $r['cif'] }}</td>
                                <td class="px-4 py-2 text-sm text-right font-mono text-gray-700">{{ number_format((float)$r['total_impagado'], 2, ',', '.') }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-600">{{ $r['num_facturas'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
@endif
