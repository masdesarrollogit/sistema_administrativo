<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Encabezado --}}
        <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Reportes Moodle</h1>
                <p class="text-sm text-gray-500 mt-1">Dashboard de alumnos en curso (2026) + historial post-cierre. Reportes activos: <strong>No conectados</strong>, <strong>Inactivos</strong>, <strong>Riesgo crítico</strong>, <strong>Pre-cierre</strong>, <strong>Apto sin examen</strong>, <strong>Aprobados</strong>, <strong>No aptos</strong></p>
            </div>
            <div class="flex items-center gap-4">
                {{-- Toggle de notificaciones automáticas --}}
                <div class="flex flex-col items-end">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-700">Notificaciones automáticas</span>
                        <button type="button"
                                wire:click="toggleNotificaciones"
                                wire:confirm="{{ $notificacionesActivas
                                    ? '¿Desactivar las notificaciones automáticas? Los 3 crones de email (no conectados, inactivos, tutores) dejarán de enviar. El snapshot diario seguirá corriendo.'
                                    : '¿Reactivar las notificaciones automáticas? Los próximos crones enviarán emails a alumnos y tutores.' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 {{ $notificacionesActivas ? 'bg-green-500' : 'bg-gray-300' }}"
                                aria-label="Activar o desactivar notificaciones automáticas">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $notificacionesActivas ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                        <span class="text-xs font-semibold {{ $notificacionesActivas ? 'text-green-700' : 'text-gray-500' }}">
                            {{ $notificacionesActivas ? 'ON' : 'OFF' }}
                        </span>
                    </div>
                    @if($notificacionesActualizadasInfo)
                        <span class="text-[10px] text-gray-400 mt-1">{{ $notificacionesActualizadasInfo }}</span>
                    @endif
                </div>

                <button wire:click="refrescarTodos" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm disabled:opacity-50">
                    <span wire:loading.remove wire:target="refrescarTodos">↻ Refrescar todos ahora</span>
                    <span wire:loading wire:target="refrescarTodos">Refrescando...</span>
                </button>
            </div>
        </div>

        @unless($notificacionesActivas)
            <div class="mb-4 p-3 bg-yellow-50 border border-yellow-300 rounded-lg text-yellow-900 text-sm flex items-center gap-2">
                <span class="text-lg">⏸</span>
                <div>
                    <strong>Notificaciones automáticas DESACTIVADAS</strong> — los 3 crones de email (no conectados, inactivos, tutores) no enviarán nada hasta que vuelvas a activar el switch. El snapshot diario sigue corriendo.
                </div>
            </div>
        @endunless

        @if($mensajeFlash)
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-sm">
                {{ $mensajeFlash }}
            </div>
        @endif

        {{-- KPIs clicables (cada una abre su Reporte) --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
            <button type="button"
                    wire:click="abrirReporte('todos')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500 hover:shadow-md transition-shadow {{ $reporte === 'todos' ? 'ring-2 ring-blue-300' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">Total en curso</div>
                <div class="text-3xl font-bold text-gray-900 mt-1">{{ $kpiTotal }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Ver todos</div>
            </button>

            <button type="button"
                    wire:click="abrirReporte('no_conectados')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-400 hover:shadow-md transition-shadow {{ $reporte === 'no_conectados' ? 'ring-2 ring-orange-300' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">No conectados</div>
                <div class="text-3xl font-bold text-orange-600 mt-1">{{ $kpiNoConectados }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Reporte 1 · click para abrir</div>
            </button>

            <button type="button"
                    wire:click="abrirReporte('inactivos')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-indigo-500 hover:shadow-md transition-shadow {{ $reporte === 'inactivos' ? 'ring-2 ring-indigo-300' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">Inactivos &gt;3 días</div>
                <div class="text-3xl font-bold text-indigo-600 mt-1">{{ $kpiInactivos }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Reporte 2 · click para abrir</div>
            </button>

            <button type="button"
                    wire:click="abrirReporte('riesgo_critico')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500 hover:shadow-md transition-shadow {{ $reporte === 'riesgo_critico' ? 'ring-2 ring-red-300' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">Riesgo crítico</div>
                <div class="text-3xl font-bold text-red-600 mt-1">{{ $kpiRiesgoCritico }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Reporte 3 · click para abrir</div>
            </button>

            <button type="button"
                    wire:click="abrirReporte('pre_cierre')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-600 hover:shadow-md transition-shadow {{ $reporte === 'pre_cierre' ? 'ring-2 ring-amber-400' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">Pre-cierre &lt;72h</div>
                <div class="text-3xl font-bold text-amber-700 mt-1">{{ $kpiPreCierre }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Reporte 4 · click para abrir</div>
            </button>

            <button type="button"
                    wire:click="abrirReporte('apto_sin_examen')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500 hover:shadow-md transition-shadow {{ $reporte === 'apto_sin_examen' ? 'ring-2 ring-yellow-300' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">Apto sin examen</div>
                <div class="text-3xl font-bold text-yellow-600 mt-1">{{ $kpiAptoSinExamen }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Reporte 5 · ≥50 sin cuestionario</div>
            </button>

            <button type="button"
                    wire:click="abrirReporte('aprobados')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-600 hover:shadow-md transition-shadow {{ $reporte === 'aprobados' ? 'ring-2 ring-green-300' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">Aprobados</div>
                <div class="text-3xl font-bold text-green-700 mt-1">{{ $kpiAprobados }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Reporte 6 · curso superado</div>
            </button>

            <button type="button"
                    wire:click="abrirReporte('no_aptos')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-800 hover:shadow-md transition-shadow {{ $reporte === 'no_aptos' ? 'ring-2 ring-red-400' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">No aptos</div>
                <div class="text-3xl font-bold text-red-800 mt-1">{{ $kpiNoAptos }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Reporte 7 · curso suspendido</div>
            </button>

            <button type="button"
                    wire:click="abrirReporte('conectados')"
                    class="text-left bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500 hover:shadow-md transition-shadow {{ $reporte === 'conectados' ? 'ring-2 ring-emerald-300' : '' }}">
                <div class="text-xs uppercase text-gray-500 font-medium">Conectados</div>
                <div class="text-3xl font-bold text-emerald-600 mt-1">{{ $kpiConectados }}</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">Activos al día</div>
            </button>

            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-rose-500">
                <div class="text-xs uppercase text-gray-500 font-medium">% en riesgo</div>
                <div class="text-3xl font-bold text-rose-600 mt-1">{{ $kpiPctRiesgo }}%</div>
                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">No conectados + inactivos + crítico</div>
            </div>
        </div>

        {{-- Leyenda de badges (sub-estados visuales) --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs uppercase text-gray-500 font-semibold tracking-wide">Leyenda de badges</div>
                <div class="text-[11px] text-gray-400">Reportes 1, 2, 3, 4, 5, 6 y 7 activos · Módulo completo</div>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-50 text-yellow-700 border border-yellow-300">Nunca entró al sitio</span>
                    <span class="text-xs text-gray-500">Reporte 1 · sub-estado</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-300">No entró al curso</span>
                    <span class="text-xs text-gray-500">Reporte 1 · sub-estado</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-300">Inactivo</span>
                    <span class="text-xs text-gray-500">Reporte 2 · &gt;3d sin entrar, no aprobado</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-400">Riesgo crítico</span>
                    <span class="text-xs text-gray-500">Reporte 3 · &lt;50 pts en &gt;50% del tiempo</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-500">Pre-cierre</span>
                    <span class="text-xs text-gray-500">Reporte 4 · &lt;=72h y sin cuestionario final</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-400">Apto sin examen</span>
                    <span class="text-xs text-gray-500">Reporte 5 · ≥50 pts pero sin cuestionario final</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-500">Aprobado</span>
                    <span class="text-xs text-gray-500">Reporte 6 · curso superado (≥50 + cuestionario)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-200 text-red-900 border border-red-800">No apto</span>
                    <span class="text-xs text-gray-500">Reporte 7 · curso suspendido (post fecha_fin, &lt;50 sin cuestionario)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-300">Conectado</span>
                    <span class="text-xs text-gray-500">Accedió al curso recientemente</span>
                </div>
                <div class="flex items-center gap-2 opacity-50">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-400">Apto</span>
                    <span class="text-xs text-gray-500">Curso superado (Fase 6)</span>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 border border-gray-200">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                    <input type="text" wire:model.live.debounce.300ms="search"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Nombre, apellido, NIF, email...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reporte</label>
                    <select wire:model.live="reporte"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        @foreach($reportesDisponibles as $clave => $etiqueta)
                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tutor</label>
                    <select wire:model.live="tutorId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        @foreach($tutoresOpciones as $t)
                            <option value="{{ $t->id }}">{{ trim($t->nombre.' '.$t->apellido1) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Empresa</label>
                    <select wire:model.live="empresaId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todas</option>
                        @foreach($empresasOpciones as $e)
                            <option value="{{ $e->id }}">{{ $e->razon_social }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Acción</label>
                    <select wire:model.live="accionId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todas</option>
                        @foreach($accionesOpciones as $a)
                            <option value="{{ $a->id }}">{{ $a->numero_accion }} — {{ \Illuminate\Support\Str::limit($a->denominacion, 50) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex justify-end mt-3">
                <button wire:click="limpiarFiltros" class="text-sm text-blue-600 hover:text-blue-800">Limpiar filtros</button>
            </div>
        </div>

        {{-- Tabla dedicada de No aptos (Reporte 7) --}}
        @if($reporte === 'no_aptos')
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-red-200">
                <div class="px-4 py-3 bg-red-50 border-b border-red-200">
                    <h2 class="text-sm font-semibold text-red-900">No aptos — historial de cursos suspendidos ({{ $noAptosListado->count() }})</h2>
                    <p class="text-xs text-red-700 mt-1">Alumnos cuyo curso finalizó sin aprobación. Click "Marcar reiniciado" cuando hayas creado el nuevo grupo.</p>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-600 uppercase">Alumno</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-600 uppercase">Curso</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-600 uppercase">Tutor</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-600 uppercase">Grupo</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-600 uppercase">Fin curso</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-600 uppercase">Nota final</th>
                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-600 uppercase">Ofrec.</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-600 uppercase">Estado</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-600 uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($noAptosListado as $na)
                            @php
                                $alumno = $na->alumno;
                                // El No apto puede venir de un grupo formativo o de una matrícula individual
                                $origenNoApto = $na->origen();
                                $grupo = $na->grupoFormativo;
                                $accion = $na->accion_curso;
                                $tutor = $origenNoApto?->tutorMatricula();
                                $numOfrec = $na->ofrecimientos->where('exitoso', true)->count();
                                $estadoColor = match($na->reinicio_estado) {
                                    'reiniciado'  => 'bg-green-100 text-green-800 border-green-400',
                                    'aceptado'    => 'bg-blue-100 text-blue-800 border-blue-400',
                                    'ofrecido'    => 'bg-amber-100 text-amber-800 border-amber-400',
                                    'caducado'    => 'bg-gray-100 text-gray-700 border-gray-400',
                                    'rechazado'   => 'bg-red-100 text-red-700 border-red-400',
                                    default       => 'bg-yellow-50 text-yellow-700 border-yellow-300',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900">{{ $alumno?->nombre_completo }}</div>
                                    <div class="text-xs text-gray-500">{{ $alumno?->email ?? '—' }}</div>
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ \Illuminate\Support\Str::limit($accion?->denominacion ?? '—', 35) }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $tutor ? trim($tutor->nombre.' '.$tutor->apellido1) : '—' }}</td>
                                <td class="px-3 py-2 text-gray-700">
                                    {{-- Bonificado: código FUNDAE (o el externo). Individual: modalidad --}}
                                    {{ $origenNoApto?->codigoGrupoMatricula()
                                        ?? match($origenNoApto?->tipoMatricula()) {
                                            'particular' => 'Particular',
                                            'autonomo'   => 'Autónomo 2x1',
                                            default      => '—',
                                        } }}
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ $na->fecha_fin_curso?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-right text-gray-700">
                                    @if($na->nota_total !== null && $na->nota_max !== null)
                                        <span class="text-red-700 font-semibold">{{ rtrim(rtrim(number_format((float)$na->nota_total, 2, ',', ''), '0'), ',') }}</span>
                                        <span class="text-xs text-gray-500">/{{ rtrim(rtrim(number_format((float)$na->nota_max, 2, ',', ''), '0'), ',') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $numOfrec }}/{{ config('reportes_moodle.reporte_no_aptos.max_ofrecimientos') }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $estadoColor }}">
                                        {{ ucfirst($na->reinicio_estado) }}
                                    </span>
                                    @if($na->reinicio_cerrado_at)
                                        <div class="text-[10px] text-gray-400 mt-0.5">
                                            por {{ $na->cerradoPor?->name ?? '?' }} · {{ $na->reinicio_cerrado_at?->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @if(in_array($na->reinicio_estado, ['pendiente', 'ofrecido', 'aceptado']))
                                        <button wire:click="marcarReiniciado({{ $na->id }})"
                                                wire:confirm="¿Confirmar que ya creaste el nuevo grupo para {{ addslashes($alumno?->nombre_completo ?? '') }}? Esto detendrá los emails al alumno."
                                                class="text-xs text-green-700 hover:text-green-900 font-medium">
                                            ✓ Marcar reiniciado
                                        </button>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <button wire:click="marcarRechazado({{ $na->id }})"
                                                wire:confirm="¿Rechazar el reinicio para este alumno?"
                                                class="text-xs text-gray-500 hover:text-gray-700">
                                            ✗ Rechazar
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-sm text-gray-500">
                                    No hay alumnos No aptos detectados todavía. El cron diario los descubre automáticamente cuando los cursos terminan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
        {{-- Tabla principal de snapshots --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Alumno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Usuario</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Curso</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Tutor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Grupo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Fechas</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-600 uppercase">Días restantes</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Total curso</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-600 uppercase">Avisos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($snapshots as $snap)
                        @php
                            $alumno = $snap->alumno;
                            // El origen puede ser un grupo formativo o una matrícula individual
                            $origen = $snap->origen();
                            $grupo = $snap->grupo;
                            $accion = $snap->accion_curso;
                            $tutor = $snap->tutor_curso;
                            $fechaInicioCurso = $snap->fecha_inicio_curso;
                            $fechaFinCurso = $snap->fecha_fin_curso;
                            $key = "{$snap->alumno_id}:".($origen?->claveOrigen() ?? '0');
                            $avisos = $notifCounts[$key] ?? 0;
                            $diasRestantes = $fechaFinCurso
                                ? max(0, (int) now('Europe/Madrid')->startOfDay()->diffInDays(\Carbon\Carbon::parse($fechaFinCurso)->startOfDay(), false))
                                : null;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    // Sub-etiqueta SIEMPRE referida al curso (consistente con la métrica de FUNDAE).
                                    $ultimoTexto = 'NUNCA';
                                    $notaSitio = null;
                                    if ($snap->lastaccess_curso && $snap->lastaccess_curso > 0) {
                                        $diff = now()->getTimestamp() - $snap->lastaccess_curso;
                                        if ($diff < 3600) {
                                            $ultimoTexto = 'HACE ' . max(1, (int) floor($diff / 60)) . ' MIN';
                                        } elseif ($diff < 86400) {
                                            $h = (int) floor($diff / 3600);
                                            $ultimoTexto = 'HACE ' . $h . ' HORA' . ($h === 1 ? '' : 'S');
                                        } else {
                                            $d = (int) floor($diff / 86400);
                                            $ultimoTexto = 'HACE ' . $d . ' DÍA' . ($d === 1 ? '' : 'S');
                                        }
                                    } elseif ($snap->lastaccess_global && $snap->lastaccess_global > 0) {
                                        // No entró al curso, pero sí a Moodle. Lo mostramos como nota auxiliar.
                                        $diff = now()->getTimestamp() - $snap->lastaccess_global;
                                        $d = max(1, (int) floor($diff / 86400));
                                        $notaSitio = 'sí entró a Moodle hace ' . $d . ' día' . ($d === 1 ? '' : 's');
                                    }
                                @endphp
                                <div class="flex flex-col gap-1">
                                    {{-- Badge primario: identifica el estado MÁS FUNDAMENTAL del alumno --}}
                                    @if($snap->nunca_entro_sitio)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-50 text-yellow-700 border border-yellow-300 w-fit">
                                            Nunca entró al sitio
                                        </span>
                                    @elseif($snap->nunca_entro_curso)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-300 w-fit">
                                            No entró al curso
                                        </span>
                                    @elseif($snap->pre_cierre)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-500 w-fit">
                                            Pre-cierre &lt;72h
                                        </span>
                                    @elseif($snap->apto_sin_examen)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-400 w-fit">
                                            Apto sin examen
                                        </span>
                                    @elseif($snap->riesgo_critico)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-400 w-fit">
                                            Riesgo crítico
                                        </span>
                                    @elseif($snap->inactivo)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-300 w-fit">
                                            Inactivo {{ $snap->dias_inactivo }}d
                                        </span>
                                    @elseif($snap->aprobado)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-500 w-fit">
                                            ✓ Aprobado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-300 w-fit">
                                            Conectado
                                        </span>
                                    @endif

                                    {{-- Sub-badges secundarios: estados adicionales en los que también está el alumno --}}
                                    @php
                                        $subBadges = [];
                                        // Pre-cierre solo se muestra como sub-badge si el badge primario NO es pre-cierre
                                        if ($snap->pre_cierre && ($snap->nunca_entro_sitio || $snap->nunca_entro_curso)) {
                                            $subBadges[] = ['label' => 'Pre-cierre &lt;72h', 'bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'border' => 'border-amber-400'];
                                        }
                                        // Apto sin examen — secundario solo si el primario es R1 (no si pre_cierre es primario)
                                        if ($snap->apto_sin_examen && !$snap->pre_cierre && ($snap->nunca_entro_sitio || $snap->nunca_entro_curso)) {
                                            $subBadges[] = ['label' => 'Apto sin examen', 'bg' => 'bg-yellow-50', 'text' => 'text-yellow-800', 'border' => 'border-yellow-300'];
                                        }
                                        // Riesgo crítico solo se muestra si el primario no es R4 ni R3
                                        if ($snap->riesgo_critico && !$snap->pre_cierre && ($snap->nunca_entro_sitio || $snap->nunca_entro_curso)) {
                                            $subBadges[] = ['label' => 'Riesgo crítico', 'bg' => 'bg-red-50', 'text' => 'text-red-700', 'border' => 'border-red-300'];
                                        }
                                        // Inactivo solo se muestra si el primario no es R4/R3/R2
                                        if ($snap->inactivo && !$snap->pre_cierre && !$snap->riesgo_critico && ($snap->nunca_entro_sitio || $snap->nunca_entro_curso)) {
                                            $subBadges[] = ['label' => 'Inactivo '.$snap->dias_inactivo.'d', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200'];
                                        }
                                    @endphp
                                    @foreach($subBadges as $sb)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $sb['bg'] }} {{ $sb['text'] }} border {{ $sb['border'] }} w-fit">
                                            + {!! $sb['label'] !!}
                                        </span>
                                    @endforeach

                                    <span class="text-[10px] uppercase text-gray-400 tracking-wide">ÚLTIMO AL CURSO: {{ $ultimoTexto }}</span>
                                    @if($notaSitio)
                                        <span class="text-[10px] text-gray-400 italic normal-case">({{ $notaSitio }})</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $alumno?->nombre_completo }}</div>
                                <div class="text-xs text-gray-500">{{ $alumno?->email ?? '—' }}</div>
                                @if($alumno?->telefono)
                                    <div class="text-xs text-gray-400">
                                        <a href="tel:{{ $alumno->telefono_e164 ?? $alumno->telefono }}" class="hover:text-blue-600">📞 {{ $alumno->telefono }}</a>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $snap->pivot?->moodle_username ?? $snap->matriculaAutonoma?->moodle_username ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ \Illuminate\Support\Str::limit($accion?->denominacion ?? '—', 40) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $tutor ? trim($tutor->nombre.' '.$tutor->apellido1) : '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{-- Bonificado: código FUNDAE (o el externo). Individual: modalidad --}}
                                @if($grupo)
                                    {{ $snap->codigo_grupo ?? '—' }}
                                @else
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold
                                        {{ $snap->tipo_matricula === 'particular' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $snap->codigo_grupo ?? '—' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                {{ $fechaInicioCurso?->format('d/m/Y') ?? '—' }} →
                                {{ $fechaFinCurso?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-gray-700">{{ $diasRestantes ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 min-w-[160px]">
                                @php
                                    $pct = $snap->nota_total_porcentaje;
                                    $barColor = $pct === null ? 'bg-gray-300' : ($pct >= 50 ? 'bg-emerald-500' : ($pct >= 25 ? 'bg-amber-500' : 'bg-red-500'));
                                @endphp
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2 overflow-hidden">
                                        <div class="h-2 {{ $barColor }}" style="width: {{ $pct !== null ? min(100, max(0, $pct)) : 0 }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-700 w-10 text-right">
                                        {{ $pct !== null ? number_format($pct, 0) . '%' : '—' }}
                                    </span>
                                </div>
                                <button wire:click="abrirNotas({{ $snap->id }}, '{{ addslashes($alumno?->nombre_completo ?? '') }}')"
                                        class="text-[11px] text-blue-600 hover:text-blue-800 mt-1">
                                    Ver notas
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="abrirHistorial({{ $alumno?->id }}, '{{ addslashes($alumno?->nombre_completo ?? '') }}')"
                                        class="text-blue-600 hover:text-blue-800 underline">
                                    {{ $avisos }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-sm text-gray-500">
                                No hay snapshot del día. Pulsa <strong>Refrescar todos ahora</strong> para generarlo, o espera al cron de las 02:00.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $snapshots->links() }}
            </div>
        </div>
        @endif

        {{-- Modal historial --}}
        @if($mostrarModalHistorial)
            <div class="fixed inset-0 z-50 bg-gray-900/50 flex items-center justify-center p-4" wire:click.self="cerrarHistorial">
                <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[80vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900">Historial de notificaciones — {{ $alumnoHistorialNombre }}</h3>
                        <button wire:click="cerrarHistorial" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    <div class="px-6 py-4">
                        @if($historialNotificaciones->isEmpty())
                            <p class="text-sm text-gray-500">Sin notificaciones registradas.</p>
                        @else
                            <table class="min-w-full text-sm">
                                <thead class="text-xs text-gray-500 uppercase border-b">
                                    <tr>
                                        <th class="text-left py-2">Fecha</th>
                                        <th class="text-left py-2">Tipo</th>
                                        <th class="text-left py-2">Destinatario</th>
                                        <th class="text-left py-2">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($historialNotificaciones as $log)
                                        <tr>
                                            <td class="py-2 text-gray-700">{{ $log->enviado_at?->format('d/m/Y H:i') }}</td>
                                            <td class="py-2 text-gray-700">{{ $log->tipo }}</td>
                                            <td class="py-2 text-gray-700">{{ $log->destinatario_email }}</td>
                                            <td class="py-2">
                                                @if($log->exitoso)
                                                    <span class="text-emerald-700">Enviado</span>
                                                @else
                                                    <span class="text-rose-700">Error: {{ \Illuminate\Support\Str::limit($log->error_message, 60) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal calificaciones --}}
        @if($mostrarModalNotas && $progresoNotas)
            <div class="fixed inset-0 z-50 bg-gray-900/50 flex items-center justify-center p-4" wire:click.self="cerrarNotas">
                <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[85vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Calificaciones — {{ $alumnoNotasNombre }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Snapshot del {{ $progresoNotas->fecha_snapshot?->format('d/m/Y') }}</p>
                        </div>
                        <button wire:click="cerrarNotas" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    <div class="px-6 py-4">
                        @if($progresoNotas->calificaciones->isEmpty())
                            <p class="text-sm text-gray-500">Sin calificaciones disponibles en el snapshot del día.</p>
                        @else
                            <table class="min-w-full text-sm">
                                <thead class="text-xs text-gray-500 uppercase border-b">
                                    <tr>
                                        <th class="text-left py-2">Actividad</th>
                                        <th class="text-left py-2">Tipo</th>
                                        <th class="text-right py-2">Nota</th>
                                        <th class="text-right py-2">Máx.</th>
                                        <th class="text-right py-2">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($progresoNotas->calificaciones as $cal)
                                        <tr class="{{ $cal->is_course_total ? 'bg-blue-50 font-semibold' : ($cal->is_final_quiz ? 'bg-emerald-50' : '') }}">
                                            <td class="py-2 text-gray-800">
                                                {{ $cal->itemname }}
                                                @if($cal->is_course_total)
                                                    <span class="ml-1 text-[10px] text-blue-700 uppercase">total</span>
                                                @endif
                                                @if($cal->is_final_quiz)
                                                    <span class="ml-1 text-[10px] text-emerald-700 uppercase">cuestionario final</span>
                                                @endif
                                            </td>
                                            <td class="py-2 text-gray-500 text-xs uppercase">
                                                {{ $cal->itemmodule ?? $cal->itemtype ?? '—' }}
                                            </td>
                                            <td class="py-2 text-right text-gray-800">
                                                {{ $cal->grade !== null ? rtrim(rtrim(number_format((float)$cal->grade, 2, ',', ''), '0'), ',') : '—' }}
                                            </td>
                                            <td class="py-2 text-right text-gray-500">
                                                {{ $cal->grademax !== null ? rtrim(rtrim(number_format((float)$cal->grademax, 2, ',', ''), '0'), ',') : '—' }}
                                            </td>
                                            <td class="py-2 text-right text-gray-700">
                                                @if($cal->porcentaje !== null)
                                                    {{ number_format($cal->porcentaje, 0) }}%
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
