<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl px-6 py-6">

        {{-- ENCABEZADO --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-blue-600">
                    Evaluaciones digitales
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-800">
                    Quiz / Parciales
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Consulta tus evaluaciones publicadas, responde y envía tu intento.
                </p>
            </div>
        </div>

        @error('evaluacion')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('intento')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('respuesta')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('envio')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @if ($mensajeEnvio)
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ $mensajeEnvio }}
            </div>
        @endif

        @if ($grupos->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
                <p class="text-sm font-semibold text-slate-700">
                    No tienes materias inscritas
                </p>
                <p class="mt-1 text-sm text-slate-400">
                    Cuando estés inscrito en un grupo aparecerán tus evaluaciones.
                </p>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-3">

                {{-- PANEL IZQUIERDO --}}
                <div class="space-y-4 lg:col-span-1">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
                                    Filtros
                                </p>

                                <h2 class="mt-1 text-lg font-bold text-slate-800">
                                    Materias
                                </h2>
                            </div>

                            @if ($filtroEstado === 'historial')
                                <button
                                    type="button"
                                    wire:click="cambiarFiltro('disponibles')"
                                    title="Volver a evaluaciones disponibles"
                                    aria-label="Volver a evaluaciones disponibles"
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-800"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                                    </svg>
                                </button>
                            @endif
                        </div>

                        <label class="mt-4 block text-sm font-medium text-slate-700">
                            Materia / grupo
                            <select
                                wire:model.live="grupoSeleccionadoId"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">Todos</option>

                                @foreach ($grupos as $grupo)
                                    <option value="{{ $grupo->id }}">
                                        {{ $grupo->materia->nombre }} · Semestre {{ $grupo->semestre }} · {{ $grupo->anio }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <div class="mt-5">
                            <p class="mb-2 text-sm font-medium text-slate-700">
                                Acciones
                            </p>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    wire:click="actualizarEvaluaciones"
                                    wire:loading.attr="disabled"
                                    wire:target="actualizarEvaluaciones"
                                    class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <span wire:loading.remove wire:target="actualizarEvaluaciones">
                                        Actualizar
                                    </span>
                                    <span wire:loading wire:target="actualizarEvaluaciones">
                                        Actualizando...
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    wire:click="toggleHistorial"
                                    class="flex-1 rounded-lg border px-3 py-2 text-sm font-semibold transition {{ $filtroEstado === 'historial' ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
                                >
                                    Historial
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-bold text-slate-800">
                                    Evaluaciones
                                </h2>

                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $filtroEstado === 'historial'
                                        ? 'Intentos enviados o calificados'
                                        : 'Evaluaciones disponibles o en curso' }}
                                </p>
                            </div>

                            <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                {{ $evaluaciones->count() }}
                            </span>
                        </div>

                        @if ($evaluaciones->isEmpty())
                            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">
                                No hay evaluaciones en esta lista.
                            </div>
                        @else
                            <div class="mt-4 space-y-2">
                                @foreach ($evaluaciones as $evaluacion)
                                    @php
                                        $estadoIntento = match ($evaluacion['intento_estado']) {
                                            'en_curso' => 'En curso',
                                            'enviada' => 'Enviada',
                                            'calificada' => 'Calificada',
                                            'anulada' => 'Anulada',
                                            default => null,
                                        };
                                    @endphp

                                    <button
                                        type="button"
                                        wire:key="evaluacion-{{ $evaluacion['id'] }}"
                                        wire:click="seleccionarEvaluacion({{ $evaluacion['id'] }})"
                                        class="w-full rounded-xl border p-3 text-left transition {{ $evaluacionSeleccionadaId === $evaluacion['id'] ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white hover:bg-slate-50' }}"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                {{ $evaluacion['tipo'] }}
                                            </span>

                                            <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $evaluacion['disponibilidad']['clase'] }}">
                                                {{ $evaluacion['disponibilidad']['etiqueta'] }}
                                            </span>

                                            @if ($estadoIntento)
                                                <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $evaluacion['intento_estado'] === 'anulada' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                                    {{ $estadoIntento }}
                                                </span>
                                            @endif

                                            @if ($filtroEstado === 'historial' && $evaluacion['nota'] !== null)
                                                <span class="rounded-md border px-2 py-0.5 text-xs font-semibold {{ $evaluacion['nota_clase'] }}">
                                                    Nota: {{ $evaluacion['nota'] }}
                                                </span>
                                            @endif
                                        </div>

                                        <p class="mt-2 truncate text-sm font-semibold text-slate-800">
                                            {{ $evaluacion['titulo'] }}
                                        </p>

                                        @if ($grupoSeleccionadoId === null)
                                            <p class="mt-1 truncate text-xs font-medium text-blue-700">
                                                {{ $evaluacion['materia'] }}
                                            </p>
                                        @endif

                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $evaluacion['fecha'] }} · {{ $evaluacion['horario'] }}
                                        </p>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- PANEL DERECHO --}}
                <div class="lg:col-span-2">
                    @if ($detalle === null)
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
                            <p class="text-sm font-semibold text-slate-700">
                                Selecciona una evaluación
                            </p>
                            <p class="mt-1 text-sm text-slate-400">
                                La evaluación aparecerá en este panel.
                            </p>
                        </div>
                    @else
                        @php
                            $tipoDetalle = $detalle['tipo'] === 'Parcial'
                                ? 'Parcial '.($detalle['parcial'] ?? '')
                                : 'Quiz';

                            $tipoPregunta = fn (string $tipo) => match ($tipo) {
                                'oracion' => 'Oración',
                                'multiple' => 'Respuesta múltiple',
                                'verdadero_falso' => 'Verdadero o falso',
                                default => $tipo,
                            };

                            $estadoIntentoDetalle = $intentoActual === null
                                ? null
                                : match ($intentoActual['estado']) {
                                    'en_curso' => 'En curso',
                                    'enviada' => 'Enviada',
                                    'calificada' => 'Calificada',
                                    'anulada' => 'Anulada',
                                    default => $intentoActual['estado'],
                                };

                            $preguntas = $intentoActual === null
                                ? $detalle['preguntas']
                                : $intentoActual['preguntas'];
                        @endphp

                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                            {{-- DATOS DE LA EVALUACIÓN --}}
                            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                            {{ $tipoDetalle }}
                                        </span>

                                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $detalle['disponibilidad']['clase'] }}">
                                            {{ $detalle['disponibilidad']['etiqueta'] }}
                                        </span>

                                        @if ($estadoIntentoDetalle)
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                Intento: {{ $estadoIntentoDetalle }}
                                            </span>
                                        @endif
                                    </div>

                                    <h2 class="mt-2 text-xl font-bold text-slate-800">
                                        {{ $detalle['titulo'] }}
                                    </h2>

                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ $detalle['materia'] }}
                                        · Semestre {{ $detalle['semestre'] ?? '—' }}
                                        · {{ $detalle['anio'] ?? '—' }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ $detalle['fecha'] }} · {{ $detalle['horario'] }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-400">
                                        Publicada: {{ $detalle['publicada_at'] }}
                                    </p>
                                </div>

                                @if ($intentoActual && ($intentoActual['enviada_at'] || $intentoActual['nota'] !== null))
                                    <div class="flex flex-wrap items-stretch gap-3">
                                        @if ($intentoActual['nota'] !== null)
                                            <div class="rounded-xl border px-4 py-3 text-sm {{ $intentoActual['nota_clase'] }}">
                                                <p class="text-xs font-semibold uppercase tracking-wide opacity-80">
                                                    Calificación
                                                </p>
                                                <p class="mt-1 text-base font-bold">
                                                    {{ $intentoActual['nota'] }}
                                                </p>
                                            </div>
                                        @endif

                                        @if ($intentoActual['enviada_at'])
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                    Enviado
                                                </p>
                                                <p class="mt-1 font-medium text-slate-700">
                                                    {{ $intentoActual['enviada_at'] }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- INDICACIONES --}}
                            @if ($intentoActual === null ? $detalle['disponibilidad']['disponible'] : $intentoActual['editable'])
                                <div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                    <p class="font-semibold">Instrucciones</p>
                                    <ul class="mt-1 list-disc space-y-1 pl-5">
                                        <li>Responde cada pregunta y guarda tu avance antes de continuar.</li>
                                        <li>Al finalizar, pulsa «Enviar evaluación». El envío es definitivo.</li>
                                        <li>Las preguntas sin responder se entregarán vacías.</li>
                                    </ul>
                                </div>
                            @endif

                            {{-- ESTADO DEL INTENTO --}}
                            @if ($intentoActual === null)
                                @if ($detalle['disponibilidad']['disponible'])
                                    <p class="mt-4 text-sm text-slate-600">
                                        Aún no has iniciado esta evaluación. Pulsa «Iniciar evaluación» para comenzar.
                                    </p>
                                @elseif ($detalle['disponibilidad']['etiqueta'] === 'Pendiente')
                                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                        La evaluación aún no está disponible. Podrás iniciarla el
                                        {{ $detalle['fecha'] }} entre {{ $detalle['horario'] }}.
                                    </div>
                                @else
                                    <div id="estado-tiempo-agotado" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                        El período de acceso terminó y no registraste un intento.
                                    </div>
                                @endif
                            @else
                                @if ($intentoActual['anulada'] ?? false)
                                    <div class="mt-4 rounded-xl border border-red-300 bg-red-50 px-5 py-4 text-sm text-red-800">
                                        <p class="text-base font-bold text-red-700">
                                            Examen anulado
                                        </p>
                                        @if (! empty($intentoActual['motivo_anulacion']))
                                            <p class="mt-1.5 text-sm text-red-700">
                                                {{ $intentoActual['motivo_anulacion'] }}
                                            </p>
                                        @endif
                                    </div>
                                @else
                                    @if ($intentoActual['anulacion_cancelada'] ?? false)
                                        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                                            {{ $intentoActual['mensaje_anulacion_cancelada'] }}
                                        </div>
                                    @endif

                                    @if ($intentoActual['editable'])
                                        <p class="mt-4 text-sm text-slate-600">
                                            Tu intento está en curso. Guarda cada respuesta antes de enviar la evaluación.
                                        </p>
                                    @elseif ($intentoActual['vencido'])
                                        <div id="estado-tiempo-agotado" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                                            <p class="font-semibold">Se acabó el tiempo</p>
                                            <p class="mt-1">
                                                Ya no puedes responder más preguntas. Pulsa «Enviar evaluación» para entregar las respuestas que alcanzaste a guardar.
                                            </p>
                                        </div>
                                    @else
                                        <div class="mt-4 flex flex-wrap items-center gap-3">
                                            <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                                Solo lectura
                                            </span>

                                            <p class="text-sm text-slate-600">
                                                @if (in_array($intentoActual['estado'], ['enviada', 'calificada'], true))
                                                    Este intento ya fue {{ $intentoActual['estado'] === 'calificada' ? 'calificado' : 'enviado' }} y no admite modificaciones.
                                                @else
                                                    El período de acceso terminó; el intento queda en solo lectura.
                                                @endif
                                            </p>
                                        </div>
                                    @endif
                                @endif
                            @endif

                            {{-- PREGUNTAS --}}
                            @if (
                                $intentoActual !== null
                                || $detalle['disponibilidad']['disponible']
                            )
                                <div class="mt-5 space-y-4">
                                    @if (count($preguntas) === 0)
                                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-center text-sm text-slate-400">
                                            La evaluación no tiene preguntas registradas.
                                        </div>
                                    @endif

                                    @foreach ($preguntas as $pregunta)
                                        <div
                                            wire:key="pregunta-{{ $intentoActual['id'] ?? 'detalle' }}-{{ $pregunta['id'] }}"
                                            class="rounded-xl border border-slate-200 p-4"
                                        >
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                Pregunta {{ $loop->iteration }} ·
                                                {{ $tipoPregunta($pregunta['tipo']) }}
                                            </p>

                                            <p class="mt-1 text-sm font-semibold text-slate-800">
                                                {{ $pregunta['enunciado'] }}
                                            </p>

                                            @if ($intentoActual !== null && $intentoActual['editable'])
                                                {{-- EDITABLE --}}
                                                @if ($pregunta['tipo'] === 'oracion')
                                                    <textarea
                                                        wire:model="respuestasTexto.{{ $pregunta['id'] }}"
                                                        rows="3"
                                                        maxlength="5000"
                                                        class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                                                    ></textarea>
                                                @else
                                                    <div class="mt-3 space-y-2">
                                                        @foreach ($pregunta['opciones'] as $opcion)
                                                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                                                <input
                                                                    type="radio"
                                                                    wire:model="respuestasOpciones.{{ $pregunta['id'] }}"
                                                                    value="{{ $opcion['id'] }}"
                                                                >

                                                                <span>{{ $opcion['texto'] }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                                    <button
                                                        type="button"
                                                        wire:click="guardarRespuesta({{ $pregunta['id'] }})"
                                                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                                    >
                                                        Guardar respuesta
                                                    </button>

                                                    @if ($respuestaGuardadaId === $pregunta['id'])
                                                        <span class="text-sm font-semibold text-emerald-700">
                                                            Respuesta guardada.
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                {{-- SOLO LECTURA --}}
                                                @if ($pregunta['tipo'] === 'oracion')
                                                    @php
                                                        $textoGuardado = $respuestasTexto[$pregunta['id']] ?? null;
                                                    @endphp

                                                    @if ($textoGuardado !== null && $textoGuardado !== '')
                                                        <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                            {{ $textoGuardado }}
                                                        </div>
                                                    @else
                                                        <div class="mt-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                                            Sin responder
                                                        </div>
                                                    @endif
                                                @else
                                                    @php
                                                        $opcionSeleccionada = (int) ($respuestasOpciones[$pregunta['id']] ?? 0);
                                                    @endphp

                                                    <div class="mt-3 space-y-1.5">
                                                        @foreach ($pregunta['opciones'] as $opcion)
                                                            <div class="rounded-lg border px-3 py-2 text-sm {{ $opcionSeleccionada === (int) $opcion['id'] ? 'border-blue-400 bg-blue-50 text-blue-800' : 'border-slate-200 text-slate-600' }}">
                                                                {{ $opcion['texto'] }}
                                                                @if ($opcionSeleccionada === (int) $opcion['id'])
                                                                    · Seleccionada
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    @if ($opcionSeleccionada === 0)
                                                        <p class="mt-2 text-sm text-slate-500">
                                                            Sin responder
                                                        </p>
                                                    @endif
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- ACCIONES --}}
                            <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                                @if ($intentoActual === null && $detalle['disponibilidad']['disponible'])
                                    <button
                                        type="button"
                                        wire:click="iniciarIntento({{ $detalle['id'] }})"
                                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
                                    >
                                        Iniciar evaluación
                                    </button>
                                @endif

                                @if ($intentoActual !== null && ($intentoActual['editable'] || $intentoActual['vencido']))
                                    <button
                                        type="button"
                                        wire:click="solicitarEnvio"
                                        class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                                    >
                                        Enviar evaluación
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- CONFIRMACIÓN DE ENVÍO --}}
                        @if ($mostrarConfirmacionEnvio)
                            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6">
                                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                                    <h3 class="text-lg font-bold text-slate-900">
                                        Enviar evaluación
                                    </h3>

                                    <p class="mt-3 text-sm text-slate-600">
                                        El envío es definitivo. Revisa tus respuestas antes de continuar:
                                    </p>

                                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600">
                                        <li>Después del envío no podrás modificar tus respuestas.</li>
                                        <li>Las preguntas sin responder se entregarán sin respuesta.</li>
                                        <li>Solo se enviarán las respuestas que hayas guardado con «Guardar respuesta».</li>
                                    </ul>

                                    <div class="mt-6 flex justify-end gap-3">
                                        <button
                                            type="button"
                                            wire:click="cancelarEnvio"
                                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                        >
                                            Cancelar
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="enviarIntento"
                                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                                        >
                                            Sí, enviar evaluación
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- NOTIFICACIÓN EMERGENTE DE TIEMPO RESTANTE --}}
                        @if (
                            ! empty($detalle['cierre_iso'])
                            && (
                                ($intentoActual === null && ($detalle['disponibilidad']['disponible'] || $tiempoAgotadoNotificado))
                                || ($intentoActual !== null && ($intentoActual['editable'] || $intentoActual['vencido']))
                            )
                        )
                            <div
                                wire:ignore
                                wire:key="alerta-tiempo-{{ $detalle['id'] }}-{{ $detalle['cierre_iso'] }}"
                                data-cierre-iso="{{ $detalle['cierre_iso'] }}"
                                data-servidor-ahora-iso="{{ $detalle['servidor_ahora_iso'] }}"
                                x-data="{
                                    cierreMs: 0,
                                    servidorAhoraMs: 0,
                                    clockOffsetMs: 0,
                                    visible: false,
                                    mensaje: '',
                                    tono: 'azul',
                                    mostradas: {},
                                    intentosCierre: 0,
                                    intervaloId: null,
                                    timeoutId: null,
                                    init() {
                                        this.cierreMs = Date.parse(this.$el.dataset.cierreIso || '');
                                        this.servidorAhoraMs = Date.parse(this.$el.dataset.servidorAhoraIso || '');

                                        if (Number.isNaN(this.cierreMs) || Number.isNaN(this.servidorAhoraMs)) {
                                            return;
                                        }

                                        this.clockOffsetMs = this.servidorAhoraMs - Date.now();
                                        this.$nextTick(() => this.verificar());
                                        this.intervaloId = setInterval(() => this.verificar(), 1000);
                                    },
                                    destroy() {
                                        if (this.intervaloId !== null) {
                                            clearInterval(this.intervaloId);
                                            this.intervaloId = null;
                                        }
                                        if (this.timeoutId !== null) {
                                            clearTimeout(this.timeoutId);
                                            this.timeoutId = null;
                                        }
                                    },
                                    verificar() {
                                        const restanteMs = this.cierreMs - (Date.now() + this.clockOffsetMs);

                                        if (restanteMs <= 0) {
                                            const yaBloqueado = document.getElementById('estado-tiempo-agotado') !== null;

                                            if (! this.mostradas[0]) {
                                                this.mostradas[0] = true;

                                                if (! yaBloqueado) {
                                                    this.mostrar('Se acabó el tiempo. Ya no puedes responder; solo puedes enviar la evaluación.', 'rojo');
                                                }
                                            }

                                            if (yaBloqueado || this.intentosCierre >= 5) {
                                                if (this.intervaloId !== null) {
                                                    clearInterval(this.intervaloId);
                                                    this.intervaloId = null;
                                                }

                                                return;
                                            }

                                            this.intentosCierre++;
                                            this.$wire.tiempoAgotado();

                                            return;
                                        }

                                        const min30 = 30 * 60 * 1000;
                                        const min15 = 15 * 60 * 1000;
                                        const min5 = 5 * 60 * 1000;

                                        let umbral = null;
                                        let texto = '';
                                        let tono = 'azul';

                                        if (restanteMs <= min5) {
                                            umbral = 5;
                                            texto = 'Te quedan 5 minutos para terminar el examen.';
                                            tono = 'rojo';
                                        } else if (restanteMs <= min15) {
                                            umbral = 15;
                                            texto = 'Te quedan 15 minutos para terminar el examen.';
                                            tono = 'ambar';
                                        } else if (restanteMs <= min30) {
                                            umbral = 30;
                                            texto = 'Te queda media hora para terminar el examen.';
                                            tono = 'azul';
                                        }

                                        if (umbral === null || this.mostradas[umbral]) {
                                            return;
                                        }

                                        this.mostradas[umbral] = true;
                                        this.mostrar(texto, tono);
                                    },
                                    mostrar(texto, tono) {
                                        this.mensaje = texto;
                                        this.tono = tono;
                                        this.visible = true;

                                        if (this.timeoutId !== null) {
                                            clearTimeout(this.timeoutId);
                                        }

                                        this.timeoutId = setTimeout(() => {
                                            this.cerrar();
                                        }, 10000);
                                    },
                                    cerrar() {
                                        this.visible = false;
                                        if (this.timeoutId !== null) {
                                            clearTimeout(this.timeoutId);
                                            this.timeoutId = null;
                                        }
                                    }
                                }"
                            >
                                <div
                                    x-show="visible"
                                    x-transition
                                    x-cloak
                                    style="position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 60; max-width: 20rem;"
                                >
                                    <div
                                        class="flex items-start gap-2.5 rounded-xl border px-3.5 py-2.5 text-xs shadow-lg"
                                        :class="{
                                            'border-blue-200 bg-white text-slate-800 ring-1 ring-blue-100': tono === 'azul',
                                            'border-amber-300 bg-amber-50 text-amber-900 ring-1 ring-amber-200': tono === 'ambar',
                                            'border-red-300 bg-red-50 text-red-900 ring-1 ring-red-200': tono === 'rojo'
                                        }"
                                    >
                                        <span
                                            class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg"
                                            :class="{
                                                'bg-blue-100 text-blue-700': tono === 'azul',
                                                'bg-amber-100 text-amber-700': tono === 'ambar',
                                                'bg-red-100 text-red-700': tono === 'rojo'
                                            }"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
                                            </svg>
                                        </span>

                                        <p x-text="mensaje" class="flex-1 font-medium leading-snug"></p>

                                        <button
                                            type="button"
                                            @click="cerrar()"
                                            aria-label="Cerrar aviso"
                                            class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-sm font-semibold opacity-60 transition hover:opacity-100"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- MONITOREO SILENCIOSO DEL NAVEGADOR (SOLO INTENTO EN CURSO Y EDITABLE) --}}
                        @if ($intentoActual !== null && $intentoActual['editable'])
                            <div
                                id="monitoreo-navegador-activo"
                                wire:ignore
                                wire:key="monitoreo-señales-intento-{{ $intentoActual['id'] }}"
                                class="hidden"
                                x-data="{
                                    ultimoFocoMs: 0,
                                    ultimoCopiarMs: 0,
                                    ultimoPegarMs: 0,
                                    intervaloHeartbeatId: null,
                                    onVisibilityChange: null,
                                    onWindowBlur: null,
                                    onCopy: null,
                                    onPaste: null,
                                    onKeyDown: null,
                                    init() {
                                        this.onVisibilityChange = () => {
                                            if (document.hidden === true) {
                                                this.ultimoFocoMs = Date.now();
                                                this.$wire.registrarSenalMonitoreo('cambio_pestana', { origen: 'visibilitychange' });
                                            }
                                        };

                                        this.onWindowBlur = () => {
                                            const ahora = Date.now();
                                            if (ahora - this.ultimoFocoMs > 800) {
                                                this.ultimoFocoMs = ahora;
                                                this.$wire.registrarSenalMonitoreo('perdida_foco', { origen: 'blur' });
                                            }
                                        };

                                        this.onCopy = () => {
                                            this.registrarCopiar('copy');
                                        };

                                        this.onPaste = () => {
                                            this.registrarPegar('paste');
                                        };

                                        this.onKeyDown = (e) => {
                                            if (! (e.ctrlKey || e.metaKey) || ! e.key) {
                                                return;
                                            }

                                            const tecla = e.key.toLowerCase();
                                            if (tecla === 'c') {
                                                this.registrarCopiar('keydown');
                                            } else if (tecla === 'v') {
                                                this.registrarPegar('keydown');
                                            }
                                        };

                                        document.addEventListener('visibilitychange', this.onVisibilityChange);
                                        window.addEventListener('blur', this.onWindowBlur);
                                        document.addEventListener('copy', this.onCopy);
                                        document.addEventListener('paste', this.onPaste);
                                        document.addEventListener('keydown', this.onKeyDown);

                                        this.intervaloHeartbeatId = setInterval(() => {
                                            if (! document.hidden) {
                                                this.$wire.registrarSenalMonitoreo('heartbeat');
                                            }
                                        }, 30000);
                                    },
                                    registrarCopiar(origen) {
                                        const ahora = Date.now();
                                        if (ahora - this.ultimoCopiarMs <= 500) {
                                            return;
                                        }
                                        this.ultimoCopiarMs = ahora;
                                        this.$wire.registrarSenalMonitoreo('copiar', { origen });
                                    },
                                    registrarPegar(origen) {
                                        const ahora = Date.now();
                                        if (ahora - this.ultimoPegarMs <= 500) {
                                            return;
                                        }
                                        this.ultimoPegarMs = ahora;
                                        this.$wire.registrarSenalMonitoreo('pegar', { origen });
                                    },
                                    destroy() {
                                        if (this.onVisibilityChange) {
                                            document.removeEventListener('visibilitychange', this.onVisibilityChange);
                                        }
                                        if (this.onWindowBlur) {
                                            window.removeEventListener('blur', this.onWindowBlur);
                                        }
                                        if (this.onCopy) {
                                            document.removeEventListener('copy', this.onCopy);
                                        }
                                        if (this.onPaste) {
                                            document.removeEventListener('paste', this.onPaste);
                                        }
                                        if (this.onKeyDown) {
                                            document.removeEventListener('keydown', this.onKeyDown);
                                        }
                                        if (this.intervaloHeartbeatId !== null) {
                                            clearInterval(this.intervaloHeartbeatId);
                                            this.intervaloHeartbeatId = null;
                                        }
                                    }
                                }"
                            ></div>
                        @endif

                        {{-- SINCRONIZACIÓN DE ESTADO DEL INTENTO --}}
                        @if ($intentoActual !== null && in_array($intentoActual['estado'], ['en_curso', 'anulada'], true))
                            <div
                                wire:ignore
                                wire:key="sync-estado-intento-{{ $intentoActual['id'] }}"
                                class="hidden"
                                x-data="{
                                    intervaloEstadoId: null,
                                    init() {
                                        this.intervaloEstadoId = setInterval(() => {
                                            this.$wire.verificarEstadoIntento();
                                        }, 4000);
                                    },
                                    destroy() {
                                        if (this.intervaloEstadoId !== null) {
                                            clearInterval(this.intervaloEstadoId);
                                            this.intervaloEstadoId = null;
                                        }
                                    }
                                }"
                            ></div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
