@if(!$estadoConexion || !($estadoConexion['conectado'] ?? false))
    <p class="text-center text-gray-500 py-12">Conecta primero con Zoho Books desde la pestaña Resumen.</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nº</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">CIF</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Teléfono</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Pendiente</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($facturas as $f)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $f['invoice_number'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $f['date'] ?? '' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-700">{{ $f['customer_name'] ?? '' }}</td>
                        <td class="px-4 py-2 text-sm font-mono text-gray-700">{{ $f['cif'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $f['email'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $f['telefono'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm">
                            @php $st = $f['status'] ?? ''; @endphp
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                {{ in_array($st, ['paid']) ? 'bg-emerald-100 text-emerald-700' :
                                   (in_array($st, ['sent', 'partially_paid']) ? 'bg-indigo-100 text-indigo-700' :
                                   (in_array($st, ['overdue']) ? 'bg-red-100 text-red-700' :
                                   'bg-gray-100 text-gray-700')) }}">
                                {{ $st ?: '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm text-right text-gray-900 font-mono">
                            {{ number_format((float)($f['total'] ?? 0), 2, ',', '.') }} {{ $f['currency_code'] ?? '' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-right text-gray-900 font-mono">
                            {{ number_format((float)($f['balance'] ?? 0), 2, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-12 text-center text-gray-500">Sin facturas en esta página.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <span class="text-sm text-gray-500">Página {{ $pagina }} · {{ count($facturas) }} resultados</span>
        <div class="flex gap-2">
            <button wire:click="paginaAnterior"
                    @disabled($pagina <= 1)
                    class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700 disabled:opacity-50">
                ← Anterior
            </button>
            <button wire:click="paginaSiguiente"
                    @disabled(!$hayMasFacturas)
                    class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700 disabled:opacity-50">
                Siguiente →
            </button>
        </div>
    </div>
@endif
