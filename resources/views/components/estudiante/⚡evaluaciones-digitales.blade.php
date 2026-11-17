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
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
                            Filtros
                        </p>

                        <h2 class="mt-1 text-lg font-bold text-slate-800">
                            Materias
                        </h2>

                        <label class="mt-4 block text-sm font-medium text-slate-700">
                            Materia / grupo
                            <select
                                wire:model.live="grupoSeleccionadoId"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                            >
                                @foreach ($grupos as $grupo)
                                    <option value="{{ $grupo->id }}">
                                        {{ $grupo->materia->nombre }} · Semestre {{ $grupo->semestre }} · {{ $grupo->anio }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <div class="mt-5">
                            <p class="mb-2 text-sm font-medium text-slate-700">
                                Estado
                            </p>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    wire:click="cambiarFiltro('pendientes')"
                                    class="flex-1 rounded-lg border px-3 py-2 text-sm font-semibold transition {{ $filtroEstado === 'pendientes' ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
                                >
                                    Pendientes
                                </button>

                                <button
                                    type="button"
                                    wire:click="cambiarFiltro('historial')"
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
                                    {{ $filtroEstado === 'pendientes'
                                        ? 'Disponibles o con intento en curso'
                                        : 'Intentos enviados o calificados' }}
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
                                                <span class="rounded-md bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                                    {{ $estadoIntento }}
                                                </span>
                                            @endif
                                        </div>

                                        <p class="mt-2 truncate text-sm font-semibold text-slate-800">
                                            {{ $evaluacion['titulo'] }}
                                        </p>

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

                                @if ($intentoActual && $intentoActual['enviada_at'])
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
                                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                        El período de acceso terminó y no registraste un intento.
                                    </div>
                                @endif
                            @else
                                @if ($intentoActual['editable'])
                                    <p class="mt-4 text-sm text-slate-600">
                                        Tu intento está en curso. Guarda cada respuesta antes de enviar la evaluación.
                                    </p>
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

                                @if ($intentoActual !== null && $intentoActual['editable'])
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
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
