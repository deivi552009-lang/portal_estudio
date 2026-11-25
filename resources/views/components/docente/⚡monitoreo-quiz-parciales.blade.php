<div class="min-h-screen bg-slate-50" @if (! $mostrarModalAnular) wire:poll.visible.5s @endif>
    <div class="mx-auto max-w-7xl px-6 py-6">

        {{-- ENCABEZADO --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-blue-600">
                    Quiz / Parciales
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-800">
                    Monitoreo de Evaluaciones
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Supervisa en tiempo real y consulta el historial de actividad de cada estudiante.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="actualizarMonitoreo"
                    wire:loading.attr="disabled"
                    wire:target="actualizarMonitoreo"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="actualizarMonitoreo">
                        Actualizar
                    </span>
                    <span wire:loading wire:target="actualizarMonitoreo">
                        Actualizando...
                    </span>
                </button>

                <a
                    href="{{ route('docente.quizzes') }}"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Volver a Quiz / Parciales
                </a>
            </div>
        </div>

        @if (session()->has('mensaje'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('mensaje') }}
            </div>
        @endif

        @error('grupo')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('monitoreo')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @if ($grupos->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
                <p class="text-sm font-semibold text-slate-700">
                    No tienes materias asignadas
                </p>
                <p class="mt-1 text-sm text-slate-400">
                    Crea un grupo para monitorear las evaluaciones digitales de tus estudiantes.
                </p>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-3">

                {{-- COLUMNA IZQUIERDA: PANEL DE CONTROL --}}
                <div class="lg:col-span-1">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
                            Supervisión
                        </p>

                        <h2 class="mt-1 text-lg font-bold text-slate-800">
                            Panel de Control
                        </h2>

                        <label class="mt-4 block text-sm font-medium text-slate-700">
                            Selecciona la materia
                            <select
                                wire:model.live="grupoSeleccionadoId"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            >
                                @foreach ($grupos as $grupo)
                                    <option value="{{ $grupo->id }}">
                                        {{ $grupo->materia->nombre }} · Semestre {{ $grupo->semestre }} · {{ $grupo->anio }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        @if ($evaluacionesGrupo->isNotEmpty())
                            <label class="mt-4 block text-sm font-medium text-slate-700">
                                Evaluación
                                <select
                                    wire:model.live="evaluacionSeleccionadaId"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                >
                                    @foreach ($evaluacionesGrupo as $eval)
                                        @php
                                            $etiquetaTipo = $eval->tipo === \App\Models\EvaluacionDigital::TIPO_PARCIAL
                                                ? 'Parcial '.($eval->parcial ?? '')
                                                : 'Quiz';
                                        @endphp
                                        <option value="{{ $eval->id }}">
                                            {{ $eval->titulo }} ({{ $etiquetaTipo }})@if ($eval->en_curso) (En curso)@endif
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @else
                            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-2.5 text-xs text-slate-500">
                                Esta materia no tiene evaluaciones digitales publicadas.
                            </div>
                        @endif

                        <div class="mt-6 border-t border-slate-100 pt-5">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-700">
                                    Estudiantes
                                </p>

                                <span class="rounded-lg bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                    {{ $estudiantes->count() }}
                                </span>
                            </div>

                            @if ($estudiantes->isEmpty())
                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">
                                    No hay estudiantes inscritos en esta materia.
                                </div>
                            @else
                                <div class="space-y-2">
                                    @foreach ($estudiantes as $est)
                                        @php
                                            $estaSeleccionado = $estudianteSeleccionadoId === $est['id'];
                                        @endphp
                                        <button
                                            type="button"
                                            wire:key="estudiante-monitoreo-{{ $est['id'] }}"
                                            wire:click="seleccionarEstudiante({{ $est['id'] }})"
                                            class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition {{ $estaSeleccionado ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white hover:bg-slate-50' }}"
                                        >
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full font-bold text-sm {{ $estaSeleccionado ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700' }}">
                                                {{ $est['inicial'] }}
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold text-slate-800">
                                                    {{ $est['nombre'] }}
                                                </p>

                                                <div class="mt-0.5 flex items-center gap-1.5">
                                                    @if ($est['anulado'])
                                                        <span class="inline-block h-2 w-2 rounded-full bg-red-600"></span>
                                                        <span class="text-xs font-semibold text-red-700">
                                                            Anulado
                                                        </span>
                                                    @elseif ($est['finalizado'])
                                                        <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                                        <span class="text-xs font-semibold text-blue-600">
                                                            Finalizado
                                                        </span>
                                                    @elseif ($est['activo'])
                                                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                                                        <span class="text-xs font-semibold text-emerald-600">
                                                            Activo
                                                        </span>
                                                    @else
                                                        <span class="inline-block h-2 w-2 rounded-full bg-red-500"></span>
                                                        <span class="text-xs font-semibold text-red-600">
                                                            Inactivo
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- COLUMNA DERECHA: DETALLE DEL ESTUDIANTE --}}
                <div class="space-y-6 lg:col-span-2">
                    @if ($detalleEstudiante === null)
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
                            <p class="text-sm font-semibold text-slate-700">
                                Selecciona un estudiante
                            </p>
                            <p class="mt-1 text-sm text-slate-400">
                                El detalle de conexión, progreso, alertas y registro de acciones aparecerá aquí.
                            </p>
                        </div>
                    @else
                        @php
                            $resumen = $detalleEstudiante['resumen'];
                            $porcentaje = max(0, min(100, (int) $resumen['porcentaje_progreso']));
                            $circunferencia = 226.19;
                            $offsetAnillo = round($circunferencia * (1 - ($porcentaje / 100)), 2);
                        @endphp

                        {{-- ENCABEZADO DEL DETALLE --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-800">
                                        Detalle del Estudiante: {{ $detalleEstudiante['nombre'] }}
                                    </h2>

                                    @if ($detalleEstudiante['evaluacion_titulo'])
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $detalleEstudiante['evaluacion_tipo'] }} · {{ $detalleEstudiante['evaluacion_titulo'] }}
                                        </p>
                                    @else
                                        <p class="mt-1 text-sm text-slate-400">
                                            Sin evaluación publicada seleccionada
                                        </p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($evaluacionSeleccionada && $evaluacionSeleccionada->en_curso)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                            En curso
                                        </span>
                                    @endif

                                    @if ($evaluacionSeleccionada !== null)
                                        @if ($resumen['anulado'])
                                            <button
                                                type="button"
                                                wire:click="cancelarAnulacionExamen"
                                                wire:loading.attr="disabled"
                                                wire:target="cancelarAnulacionExamen"
                                                class="rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                            >
                                                Cancelar
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="abrirModalAnular"
                                                class="rounded-lg bg-red-600 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700"
                                            >
                                                Anular
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            {{-- CUADRÍCULA 2x2 DE TARJETAS --}}
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">

                                {{-- TARJETA 1: INFORMACIÓN GENERAL --}}
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5">
                                    <h3 class="text-sm font-bold text-slate-800">
                                        Información General
                                    </h3>

                                    <div class="mt-4 flex items-center gap-4">
                                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-blue-100 text-lg font-bold text-blue-700">
                                            {{ $detalleEstudiante['inicial'] }}
                                        </div>

                                        <div class="min-w-0">
                                            <p class="truncate text-base font-bold text-slate-800">
                                                {{ $detalleEstudiante['nombre'] }}
                                            </p>

                                            <p class="mt-0.5 text-xs text-slate-600">
                                                ID Número: {{ $detalleEstudiante['id'] }}
                                            </p>

                                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                                {{ $detalleEstudiante['carrera'] ?: $detalleEstudiante['email'] }}
                                            </p>

                                            <p class="mt-1 text-xs font-medium text-blue-700">
                                                {{ $detalleEstudiante['materia'] }} · {{ $detalleEstudiante['curso'] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- TARJETA 2: ESTADO DE CONEXIÓN --}}
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5">
                                    <h3 class="text-sm font-bold text-slate-800">
                                        Estado de Conexión
                                    </h3>

                                    <div class="mt-4 space-y-2.5 text-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($resumen['anulado'])
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-100 text-red-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>

                                                <span class="text-slate-700">
                                                    Estado:
                                                    <span class="font-semibold text-red-700">Anulado</span>
                                                </span>
                                            @elseif ($resumen['finalizado'])
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>

                                                <span class="text-slate-700">
                                                    Estado:
                                                    <span class="font-semibold text-blue-600">Finalizado</span>
                                                </span>

                                                <span class="rounded-md bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                                    Entregado
                                                </span>
                                            @elseif ($resumen['en_linea'])
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>

                                                <span class="text-slate-700">
                                                    Estado:
                                                    <span class="font-semibold text-emerald-600">En Línea</span>
                                                </span>
                                            @else
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-100 text-red-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>

                                                <span class="text-slate-700">
                                                    Estado:
                                                    <span class="font-semibold text-red-600">Inactivo</span>
                                                </span>
                                            @endif
                                        </div>

                                        <p class="text-xs text-slate-600">
                                            <span class="font-medium text-slate-500">Hora de conexión:</span>
                                            {{ $resumen['hora_conexion'] ?? '—' }}
                                        </p>

                                        <p class="text-xs text-slate-600">
                                            <span class="font-medium text-slate-500">IP Address:</span>
                                            {{ $resumen['ip_address'] ?? '—' }}
                                        </p>
                                    </div>
                                </div>

                                {{-- TARJETA 3: PROGRESO DEL EXAMEN --}}
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5">
                                    <h3 class="text-sm font-bold text-slate-800">
                                        Progreso del Examen
                                    </h3>

                                    <div class="mt-4 flex items-center gap-4">
                                        <div class="relative flex h-20 w-20 shrink-0 items-center justify-center">
                                            <svg class="h-20 w-20" viewBox="0 0 88 88" aria-hidden="true">
                                                <circle
                                                    cx="44"
                                                    cy="44"
                                                    r="36"
                                                    stroke="#e2e8f0"
                                                    stroke-width="8"
                                                    fill="none"
                                                />
                                                <circle
                                                    cx="44"
                                                    cy="44"
                                                    r="36"
                                                    stroke="#2563eb"
                                                    stroke-width="8"
                                                    stroke-linecap="round"
                                                    fill="none"
                                                    stroke-dasharray="{{ $circunferencia }}"
                                                    stroke-dashoffset="{{ $offsetAnillo }}"
                                                    transform="rotate(-90 44 44)"
                                                />
                                            </svg>

                                            <span class="absolute text-sm font-bold text-slate-800">
                                                {{ $porcentaje }}%
                                            </span>
                                        </div>

                                        <div>
                                            <p class="text-xs font-medium text-slate-500">
                                                Avance guardado
                                            </p>
                                            <p class="mt-1 text-sm font-semibold text-slate-800">
                                                Preguntas Respondidas: {{ $resumen['preguntas_respondidas'] }} de {{ $resumen['total_preguntas'] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- TARJETA 4: ALERTAS ACTIVAS --}}
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5">
                                    <h3 class="text-sm font-bold text-slate-800">
                                        Alertas Activas
                                    </h3>

                                    @if (count($resumen['alertas']['items']) > 0)
                                        <ul class="mt-4 space-y-2">
                                            @foreach ($resumen['alertas']['items'] as $alertaItem)
                                                <li class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">
                                                    <span aria-hidden="true">⚠️</span>
                                                    <span>{{ $alertaItem }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-4 text-xs text-slate-500">
                                            Sin alertas activas registradas.
                                        </p>
                                    @endif
                                </div>

                            </div>
                        </div>

                        {{-- PANEL INFERIOR: ACTIVIDAD RECIENTE (LOG DE ACCIONES) --}}
                        @php
                            $totalLogs = count($resumen['logs']);
                            $limiteLogs = $limiteActividades ?? 5;
                            $logsVisibles = $verTodasActividades
                                ? $resumen['logs']
                                : array_slice($resumen['logs'], 0, $limiteLogs);
                        @endphp

                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <h3 class="text-base font-bold text-slate-800">
                                    Actividad Reciente (Log de Acciones)
                                </h3>

                                <span class="rounded-lg bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                                    {{ $totalLogs }}
                                </span>
                            </div>

                            @if ($totalLogs === 0)
                                <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-400">
                                    Aún no se registran acciones para este estudiante.
                                </div>
                            @else
                                <div class="mt-4 divide-y divide-slate-100">
                                    @foreach ($logsVisibles as $log)
                                        <div
                                            wire:key="log-monitoreo-{{ $log['id'] }}"
                                            class="flex items-start gap-4 py-3 text-sm"
                                        >
                                            <span class="w-20 shrink-0 font-mono text-xs font-medium text-slate-500">
                                                {{ $log['hora'] ?? '—' }}
                                            </span>

                                            <div class="min-w-0 flex-1">
                                                <p class="{{ $log['es_alerta'] ? 'font-semibold text-amber-800' : 'text-slate-700' }}">
                                                    @if ($log['es_alerta'])
                                                        <span class="mr-1" aria-hidden="true">⚠️</span>
                                                    @endif
                                                    {{ $log['descripcion'] }}
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($totalLogs > $limiteLogs)
                                    <div class="mt-4 border-t border-slate-100 pt-4 text-center">
                                        <button
                                            type="button"
                                            wire:click="toggleVerTodasActividades"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                        >
                                            @if ($verTodasActividades)
                                                Ver menos
                                            @else
                                                Ver más
                                            @endif
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>

            </div>
        @endif

        @if ($mostrarModalAnular)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-slate-900">
                        Anular examen
                    </h3>

                    <p class="mt-2 text-sm text-slate-600">
                        Escribe el mensaje que verá el estudiante. Se asignará automáticamente una nota de 1.0 en sus calificaciones.
                    </p>

                    <div class="mt-4">
                        <label for="mensaje-anulacion" class="block text-sm font-medium text-slate-700">
                            Mensaje para el estudiante
                        </label>
                        <textarea
                            id="mensaje-anulacion"
                            wire:model="mensajeAnulacion"
                            rows="4"
                            maxlength="1000"
                            placeholder="Escribe el motivo de la anulación para el estudiante..."
                            class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
                        ></textarea>

                        @error('mensajeAnulacion')
                            <p class="mt-1.5 text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            wire:click="cerrarModalAnular"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </button>

                        <button
                            type="button"
                            wire:click="confirmarAnularExamen"
                            wire:loading.attr="disabled"
                            wire:target="confirmarAnularExamen"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Anular
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
