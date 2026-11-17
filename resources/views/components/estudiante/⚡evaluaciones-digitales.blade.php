<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-slate-900">Evaluaciones digitales</h2>
        <p class="mt-1 text-sm text-slate-500">
            Consulta las evaluaciones publicadas de tus materias.
        </p>
    </div>

    @error('evaluacion')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $message }}
        </div>
    @enderror

    @error('intento')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $message }}
        </div>
    @enderror

    @error('respuesta')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $message }}
        </div>
    @enderror

    @error('envio')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if ($mensajeEnvio)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ $mensajeEnvio }}
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
            <div>
                <h3 class="font-bold text-slate-900">Publicadas</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Evaluaciones disponibles para tus grupos.
                </p>
            </div>

            <span class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-600">
                {{ $evaluaciones->count() }}
                {{ $evaluaciones->count() === 1 ? 'evaluación' : 'evaluaciones' }}
            </span>
        </div>

        @if ($evaluaciones->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                    ▣
                </div>
                <h4 class="mt-4 font-semibold text-slate-700">
                    No hay evaluaciones publicadas
                </h4>
                <p class="mt-1 text-sm text-slate-400">
                    Cuando tu docente publique una evaluación aparecerá aquí.
                </p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($evaluaciones as $evaluacion)
                    <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-semibold text-slate-800">
                                    {{ $evaluacion['titulo'] }}
                                </p>

                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                    Publicada
                                </span>

                                <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $evaluacion['disponibilidad']['clase'] }}">
                                    {{ $evaluacion['disponibilidad']['etiqueta'] }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-slate-500">
                                {{ $evaluacion['materia'] }}
                                · Semestre {{ $evaluacion['semestre'] ?? '—' }}
                                · {{ $evaluacion['anio'] ?? '—' }}
                                · {{ $evaluacion['tipo'] === 'Parcial'
                                    ? 'Parcial ' . ($evaluacion['parcial'] ?? '—')
                                    : 'Quiz' }}
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                {{ $evaluacion['fecha'] }}
                                · {{ $evaluacion['horario'] }}
                            </p>

                            <p class="mt-1 text-xs text-slate-400">
                                Publicada: {{ $evaluacion['publicada_at'] }}
                            </p>
                        </div>

                        @if ($evaluacion['disponibilidad']['disponible'])
                            <button
                                type="button"
                                wire:click="verDetalle({{ $evaluacion['id'] }})"
                                class="shrink-0 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver detalle
                            </button>
                        @else
                            <span class="shrink-0 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-400">
                                No disponible
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($intentoActual)
        <section class="rounded-2xl border border-blue-200 bg-blue-50 p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
                        Intento
                    </p>

                    <h3 class="mt-1 text-lg font-bold text-slate-800">
                        {{ $intentoActual['evaluacion_titulo'] }}
                    </h3>

                    <p class="mt-1 text-sm text-slate-600">
                        Intento #{{ $intentoActual['id'] }}
                        · Estado:
                        {{ match ($intentoActual['estado']) {
                            'en_curso' => 'En curso',
                            'enviada' => 'Enviada',
                            'calificada' => 'Calificada',
                            default => $intentoActual['estado'],
                        } }}
                    </p>

                    @if ($intentoActual['enviada_at'])
                        <p class="mt-1 text-xs text-slate-500">
                            Enviado: {{ $intentoActual['enviada_at'] }}
                        </p>
                    @endif

                    @if ($intentoActual['editable'])
                        <p class="mt-3 text-sm text-slate-600">
                            Responde cada pregunta y usa «Guardar respuesta» para conservar tus avances.
                        </p>
                    @else
                        <span class="mt-3 inline-block rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                            Solo lectura
                        </span>

                        <p class="mt-2 text-sm text-slate-600">
                            @if (in_array($intentoActual['estado'], ['enviada', 'calificada'], true))
                                Este intento ya fue {{ $intentoActual['estado'] === 'calificada' ? 'calificado' : 'enviado' }} y no admite modificaciones. La visualización de resultados se habilitará en un paso posterior.
                            @else
                                El período de acceso terminó; el intento queda en solo lectura.
                            @endif
                        </p>
                    @endif
                </div>

                <button
                    type="button"
                    wire:click="cerrarIntento"
                    class="shrink-0 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
                >
                    Cerrar intento
                </button>
            </div>

            @if ($intentoActual['editable'])
                <div class="mt-6 space-y-4">
                    @if (count($intentoActual['preguntas']) === 0)
                        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-6 text-center text-sm text-slate-400">
                            La evaluación no tiene preguntas registradas.
                        </div>
                    @endif

                    @foreach ($intentoActual['preguntas'] as $pregunta)
                        <div
                            wire:key="pregunta-intento-{{ $pregunta['id'] }}"
                            class="rounded-xl border border-blue-100 bg-white p-4"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Pregunta {{ $loop->iteration }} ·
                                {{ match ($pregunta['tipo']) {
                                    'oracion' => 'Oración',
                                    'multiple' => 'Respuesta múltiple',
                                    'verdadero_falso' => 'Verdadero o falso',
                                    default => $pregunta['tipo'],
                                } }}
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-800">
                                {{ $pregunta['enunciado'] }}
                            </p>

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
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($intentoActual['editable'])
                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        wire:click="solicitarEnvio"
                        class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                    >
                        Enviar evaluación
                    </button>
                </div>
            @endif

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
        </section>
    @endif

    @if ($detalle)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6">
            <div class="max-h-full w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Evaluación · Solo lectura
                        </p>

                        <h3 class="mt-1 text-lg font-bold text-slate-800">
                            {{ $detalle['titulo'] }}
                        </h3>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarDetalle"
                        class="shrink-0 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Cerrar
                    </button>
                </div>

                <div class="mt-5 grid gap-4 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Materia y grupo
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $detalle['materia'] }}
                        </p>
                        <p class="text-slate-500">
                            Semestre {{ $detalle['semestre'] ?? '—' }}
                            · {{ $detalle['anio'] ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Tipo
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $detalle['tipo'] === 'Parcial'
                                ? 'Parcial ' . ($detalle['parcial'] ?? '—')
                                : 'Quiz' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Fecha
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $detalle['fecha'] }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Horario
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $detalle['horario'] }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Publicada el
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $detalle['publicada_at'] }}
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-700">
                            Preguntas
                        </p>

                        <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                            {{ count($detalle['preguntas']) }}
                        </span>
                    </div>

                    @if (count($detalle['preguntas']) === 0)
                        <div class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-center text-sm text-slate-400">
                            La evaluación no tiene preguntas registradas.
                        </div>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach ($detalle['preguntas'] as $indice => $pregunta)
                                @php
                                    $etiquetaTipo = match ($pregunta['tipo']) {
                                        'oracion' => 'Oración',
                                        'multiple' => 'Respuesta múltiple',
                                        'verdadero_falso' => 'Verdadero o falso',
                                        default => $pregunta['tipo'],
                                    };
                                @endphp

                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Pregunta {{ $indice + 1 }} · {{ $etiquetaTipo }}
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-slate-800">
                                        {{ $pregunta['enunciado'] }}
                                    </p>

                                    @if ($pregunta['tipo'] === 'oracion')
                                        <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                            Respuesta abierta · revisión manual
                                        </p>
                                    @else
                                        <div class="mt-3 space-y-1.5">
                                            @foreach ($pregunta['opciones'] as $opcion)
                                                <div class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                                    {{ $opcion['texto'] }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        wire:click="iniciarIntento({{ $detalle['id'] }})"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                    >
                        Iniciar evaluación
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
