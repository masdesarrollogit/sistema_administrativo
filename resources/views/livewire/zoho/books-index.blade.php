<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Encabezado --}}
        <div class="mb-8 flex items-start justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    📘 Zoho Books
                </h1>
                <p class="mt-2 text-gray-600">Conexión y consulta del catálogo de facturas y contactos en Zoho Books.</p>
            </div>

            <div class="flex items-center gap-2">
                @if($estadoConexion && ($estadoConexion['conectado'] ?? false))
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-sm font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Conectado
                    </span>
                    <a href="{{ route('webcurso.zoho.disconnect') }}"
                       onclick="return confirm('¿Revocar la conexión local con Zoho? (Tendrás que volver a autorizar)')"
                       class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">
                        Desconectar
                    </a>
                @else
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-sm font-medium">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        No conectado
                    </span>
                    <a href="{{ route('webcurso.zoho.connect') }}"
                       class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow-sm">
                        Conectar con Zoho
                    </a>
                @endif
            </div>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 p-4 text-emerald-700">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-4 text-red-700">
                {{ session('error') }}
            </div>
        @endif
        @if($error)
            <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 p-4 text-amber-800">
                <p class="font-medium">Error al consultar Zoho Books:</p>
                <p class="text-sm mt-1">{{ $error }}</p>
            </div>
        @endif

        {{-- Tabs --}}
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-6 px-6" aria-label="Tabs">
                    @foreach(['resumen' => 'Resumen', 'facturas' => 'Facturas', 'contactos' => 'Contactos', 'impagados' => 'Impagados'] as $key => $label)
                        <button wire:click="cambiarTab('{{ $key }}')"
                                type="button"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm
                                       {{ $tab === $key
                                          ? 'border-indigo-500 text-indigo-600'
                                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                {{-- TAB: Resumen --}}
                @if($tab === 'resumen')
                    @if(!$estadoConexion || !($estadoConexion['conectado'] ?? false))
                        <div class="text-center py-12">
                            <p class="text-gray-600 mb-4">Aún no hay conexión con Zoho Books.</p>
                            <a href="{{ route('webcurso.zoho.connect') }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Conectar con Zoho
                            </a>
                            <p class="text-xs text-gray-500 mt-4">
                                Se te redirigirá al consentimiento OAuth de Zoho. Tras aceptar, el refresh token se guardará en la BD.
                            </p>
                        </div>
                    @else
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="bg-gray-50 rounded-md p-4">
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Organization ID</dt>
                                <dd class="mt-1 font-mono text-sm text-gray-900">{{ $estadoConexion['organization_id'] ?? '—' }}</dd>
                            </div>
                            <div class="bg-gray-50 rounded-md p-4">
                                <dt class="text-xs uppercase tracking-wide text-gray-500">API domain</dt>
                                <dd class="mt-1 font-mono text-sm text-gray-900">{{ $estadoConexion['api_domain'] ?? '—' }}</dd>
                            </div>
                        </dl>

                        <h3 class="mt-6 mb-3 text-sm font-semibold text-gray-700 uppercase tracking-wide">Organizaciones accesibles</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">País</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Moneda</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @forelse($estadoConexion['organizations'] ?? [] as $org)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $org['name'] }}</td>
                                            <td class="px-4 py-2 text-sm font-mono text-gray-600">{{ $org['organization_id'] }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-600">{{ $org['country'] ?? '—' }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-600">{{ $org['currency'] ?? '—' }}</td>
                                            <td class="px-4 py-2 text-sm">
                                                @if($org['is_default_org'])
                                                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded text-xs font-medium">SÍ</span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Sin organizaciones.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            <button wire:click="recargar"
                                    class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">
                                ↻ Recargar
                            </button>
                        </div>
                    @endif
                @endif

                {{-- TAB: Facturas --}}
                @if($tab === 'facturas')
                    @include('livewire.zoho.partials.tabla-facturas')
                @endif

                {{-- TAB: Contactos --}}
                @if($tab === 'contactos')
                    @include('livewire.zoho.partials.tabla-contactos')
                @endif

                {{-- TAB: Impagados --}}
                @if($tab === 'impagados')
                    @include('livewire.zoho.partials.tabla-impagados')
                @endif
            </div>
        </div>
    </div>

    {{-- Modal de notas por empresa (vive fuera de tabs para sobrevivir cambios de pestaña) --}}
    @include('livewire.zoho.partials.modal-notas')
</div>
