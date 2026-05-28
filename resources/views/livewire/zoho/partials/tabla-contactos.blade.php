@if(!$estadoConexion || !($estadoConexion['conectado'] ?? false))
    <p class="text-center text-gray-500 py-12">Conecta primero con Zoho Books desde la pestaña Resumen.</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($contactos as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $c['contact_name'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-700">{{ $c['company_name'] ?? '' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $c['email'] ?? '' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $c['contact_type'] ?? '' }}</td>
                        <td class="px-4 py-2 text-sm text-right text-gray-900 font-mono">
                            {{ number_format((float)($c['outstanding_receivable_amount'] ?? 0), 2, ',', '.') }}
                            {{ $c['currency_code'] ?? '' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-gray-500">Sin contactos en esta página.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <span class="text-sm text-gray-500">Página {{ $pagina }} · {{ count($contactos) }} resultados</span>
        <div class="flex gap-2">
            <button wire:click="paginaAnterior"
                    @disabled($pagina <= 1)
                    class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700 disabled:opacity-50">
                ← Anterior
            </button>
            <button wire:click="paginaSiguiente"
                    @disabled(!$hayMasContactos)
                    class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700 disabled:opacity-50">
                Siguiente →
            </button>
        </div>
    </div>
@endif
