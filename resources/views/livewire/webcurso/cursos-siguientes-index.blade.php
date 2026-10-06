<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('webcurso.encuestas-calidad', ['pestana' => 'oportunidades']) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Encuestas de calidad · Oportunidades</a>
                <h1 class="text-3xl font-bold text-gray-900 mt-1">➡️ Cursos siguientes</h1>
                <p class="text-sm text-gray-500 mt-1">Define qué curso ofrecer a quien terminó y valoró bien otro (ej. Claude Code → Claude Code Avanzado). Se puede ofrecer cualquier acción formativa FUNDAE ({{ number_format($totalAcciones) }}) o curso del catálogo web ({{ number_format($totalCatalogo) }}).</p>
            </div>
            <button wire:click="proponer" wire:loading.attr="disabled" wire:target="proponer"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50"
                    title="Busca en el catálogo el mismo curso con un nivel superior (Nivel 1→2, básico→intermedio→avanzado, I→II)">
                <span wire:loading.remove wire:target="proponer">✨ Proponer automáticamente</span>
                <span wire:loading wire:target="proponer">Buscando…</span>
            </button>
        </div>

        @if($mensaje)
            <div class="mb-4 px-4 py-2 rounded-lg text-sm bg-indigo-50 text-indigo-800 border border-indigo-200 flex justify-between">
                <span>{{ $mensaje }}</span>
                <button wire:click="$set('mensaje', null)" class="font-bold">×</button>
            </div>
        @endif

        @if($porConfirmar > 0)
            <button wire:click="$set('filtro', 'por_confirmar')" class="mb-4 w-full text-left px-4 py-2 rounded-lg text-sm bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100">
                ⚠️ Hay <strong>{{ $porConfirmar }}</strong> propuestas automáticas pendientes de confirmar. No se usan en «Oportunidades» hasta que las confirmes.
            </button>
        @endif

        <div class="bg-white rounded-xl shadow-sm p-4 mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="🎓 Buscar curso realizado...">
            <select wire:model.live="filtro" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todos los cursos</option>
                <option value="sin_siguiente">Sin curso siguiente</option>
                <option value="por_confirmar">Con propuestas por confirmar</option>
                <option value="con_siguiente">Con curso siguiente activo</option>
            </select>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curso realizado</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Encuestas</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curso siguiente</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($origenes as $o)
                        <tr class="hover:bg-gray-50 align-top" wire:key="orig-{{ md5($o['clave']) }}">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $o['nombre'] }}</td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600 whitespace-nowrap">
                                {{ $o['encuestas'] }}
                                @if($o['media'] !== null)<div class="text-[11px] text-gray-400">media {{ number_format($o['media'], 2) }} · {{ $o['n4'] }} con 4</div>@endif
                            </td>
                            <td class="px-4 py-3">
                                @forelse($o['siguientes'] as $s)
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        @if($s->activo)
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">✓ {{ $s->destino_titulo }}@if($s->destino_tipo === 'accion') <span class="opacity-70">· AF {{ $s->accionFormativa->numero_accion }}</span>@else <span class="opacity-70">· web</span>@endif</span>
                                            <button wire:click="desactivar({{ $s->id }})" class="text-[11px] text-gray-500 hover:underline">pausar</button>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $s->sugerido_auto && !$s->confirmado_en ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $s->sugerido_auto && !$s->confirmado_en ? '✨ propuesta:' : 'pausado:' }} {{ $s->destino_titulo }}@if($s->destino_tipo === 'accion') <span class="opacity-70">· AF {{ $s->accionFormativa->numero_accion }}</span>@else <span class="opacity-70">· web</span>@endif
                                            </span>
                                            <button wire:click="confirmar({{ $s->id }})" class="text-[11px] text-green-700 font-semibold hover:underline">Confirmar</button>
                                        @endif
                                        <button wire:click="quitar({{ $s->id }})" wire:confirm="¿Quitar este curso siguiente?" class="text-[11px] text-red-600 hover:underline">quitar</button>
                                    </div>
                                @empty
                                    <span class="text-xs text-gray-400">— sin definir —</span>
                                @endforelse

                                @if($editandoClave === $o['clave'])
                                    <div class="mt-2 p-3 rounded-lg border border-indigo-100 bg-indigo-50/40">
                                        <input type="text" wire:model.live.debounce.300ms="buscarCatalogo" autofocus
                                               class="w-full px-3 py-1.5 border border-gray-300 rounded text-sm" placeholder="Buscar acción formativa (nombre o nº) o curso de la web (mín. 2 letras)…">
                                        @foreach($resultados as $c)
                                            <div class="flex items-center justify-between py-1 border-b border-indigo-100 last:border-0">
                                                <span class="text-sm text-gray-800">
                                                    <span class="px-1 py-0.5 rounded text-[10px] font-semibold {{ $c['tipo'] === 'accion' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">{{ $c['tipo'] === 'accion' ? 'Acción FUNDAE' : 'Web' }}</span>
                                                    {{ $c['titulo'] }}
                                                    <span class="text-[11px] text-gray-400">{{ $c['detalle'] }}</span>
                                                </span>
                                                <button wire:click="anadirDestino('{{ $c['tipo'] }}', {{ $c['id'] }})" class="text-xs px-2 py-0.5 rounded bg-indigo-600 text-white hover:bg-indigo-700">+ Añadir</button>
                                            </div>
                                        @endforeach

                                        @if($o['siguientes']->isNotEmpty())
                                            <div class="mt-3 text-[11px] text-gray-500 font-semibold uppercase">Enlace a la ficha en webcurso.es</div>
                                            @foreach($o['siguientes'] as $s)
                                                @if($s->cursoDestino)
                                                    <label class="flex items-center gap-2 mt-1 text-xs text-gray-600">
                                                        <span class="w-48 truncate">{{ $s->cursoDestino->titulo }}</span>
                                                        <input type="url" value="{{ $s->cursoDestino->url }}" placeholder="https://www.webcurso.es/courses/…"
                                                               wire:change="guardarUrl({{ $s->cursoDestino->id }}, $event.target.value)"
                                                               class="flex-grow px-2 py-1 border border-gray-300 rounded text-xs">
                                                    </label>
                                                @endif
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="editar(@js($o['clave']))" class="text-sm text-indigo-600 hover:underline whitespace-nowrap">
                                    {{ $editandoClave === $o['clave'] ? 'Cerrar' : '✏️ Editar' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No hay cursos con los filtros actuales.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-gray-100">{{ $origenes->links() }}</div>
        </div>
    </div>
</div>
