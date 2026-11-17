<div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-7xl px-6 py-6">

        {{-- ENCABEZADO --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-blue-600">
                    Gestión académica
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-800">
                    Revisión de Quiz / Parciales
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Consulta las respuestas, valora las preguntas abiertas y guarda la calificación.
                </p>
            </div>

            <a
                href="{{ route('docente.quizzes') }}"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Volver a Quiz / Parciales
            </a>
        </div>

        @if (session('mensaje'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('mensaje') }}
            </div>
        @endif

        @error('grupo')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('revision')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('valoracion')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('respuesta')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('traslado')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

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
                            @forelse ($grupos as $grupo)
                                <option value="{{ $grupo->id }}">
                                    {{ $grupo->materia->nombre }} · Semestre {{ $grupo->semestre }} · {{ $grupo->anio }}
                                </option>
                            @empty
                                <option value="">Sin grupos disponibles</option>
                            @endforelse
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
                    <h2 class="text-lg font-bold text-slate-800">
                        Estudiantes
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ $filtroEstado === 'pendientes' ? 'Pendientes de calificar' : 'Calificaciones guardadas' }}
                    </p>

                    @if ($intentos->isEmpty())
                        <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">
                            No hay estudiantes en esta lista.
                        </div>
                    @else
                        <div class="mt-4 space-y-2">
                            @foreach ($intentos as $intento)
                                <button
                                    type="button"
                                    wire:key="intento-{{ $intento->id }}"
                                    wire:click="seleccionarIntento({{ $intento->id }})"
                                    class="w-full rounded-xl border p-3 text-left transition {{ $intentoSeleccionadoId === $intento->id ? 'border-blue-500 bg-blue-50' : 'border-slate-200 bg-white hover:bg-slate-50' }}"
                                >
                                    <p class="text-sm font-semibold text-slate-800">
                                        {{ $intento->estudiante?->user?->name ?? 'Estudiante' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-600">
                                        {{ $intento->evaluacionDigital?->titulo ?? 'Evaluación' }}
                                        ·
                                        {{ $intento->evaluacionDigital?->tipo === \App\Models\EvaluacionDigital::TIPO_PARCIAL
                                            ? 'Parcial '.($intento->evaluacionDigital?->parcial ?? '')
                                            : 'Quiz' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        @if ($intento->estado === \App\Models\EvaluacionDigitalIntento::ESTADO_CALIFICADA)
                                            Calificada
                                            @if ($intento->nota !== null)
                                                · Nota {{ number_format((float) $intento->nota, 2) }}
                                            @endif
                                        @else
                                            Pendiente
                                            @if ($intento->pendientes_count > 0)
                                                · {{ $intento->pendientes_count }} abierta(s) por calificar
                                            @endif
                                        @endif
                                    </p>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- PANEL DERECHO --}}
            <div class="lg:col-span-2">
                @if ($examen === null || $sugerida === null)
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
                        <p class="text-sm font-semibold text-slate-700">
                            Selecciona un estudiante
                        </p>
                        <p class="mt-1 text-sm text-slate-400">
                            El examen aparecerá en este panel.
                        </p>
                    </div>
                @else
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                        {{-- DATOS DEL EXAMEN --}}
                        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
                                    {{ $examen['tipo'] }}
                                </p>

                                <h2 class="mt-1 text-xl font-bold text-slate-800">
                                    {{ $examen['estudiante'] }}
                                </h2>

                                <p class="mt-1 text-sm text-slate-600">
                                    {{ $examen['materia'] }} · {{ $examen['grupo'] }}
                                </p>

                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $examen['evaluacion'] }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-center">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">
                                    Nota sugerida
                                </p>

                                @if ($sugerida['nota'] !== null)
                                    <p class="mt-1 text-3xl font-bold text-blue-800">
                                        {{ number_format((float) $sugerida['nota'], 2) }}
                                    </p>
                                    <p class="text-xs text-blue-700">
                                        sobre 5
                                    </p>
                                @else
                                    <p class="mt-1 text-lg font-bold text-amber-700">
                                        Revisión incompleta
                                    </p>
                                    <p class="text-xs text-amber-700">
                                        {{ $sugerida['pendientes'] }} abierta(s) pendiente(s)
                                    </p>
                                @endif

                                <p class="mt-2 text-xs text-slate-600">
                                    {{ number_format((float) $sugerida['puntaje_total'], 2) }}
                                    /
                                    {{ $sugerida['total_preguntas'] }}
                                    puntos
                                </p>
                            </div>
                        </div>

                        {{-- PREGUNTAS --}}
                        <div class="mt-5 space-y-4">
                            @foreach ($examen['preguntas'] as $pregunta)
                                <div class="rounded-xl border border-slate-200 p-4">
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
                                        @if ($pregunta['sin_responder'])
                                            <div class="mt-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                                Sin responder · 0 puntos
                                            </div>
                                        @else
                                            <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                {{ $pregunta['respuesta_texto'] }}
                                            </div>

                                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                                <label class="text-sm font-medium text-slate-700">
                                                    Valoración
                                                    <select
                                                        wire:model.live="valoraciones.{{ $pregunta['respuesta_id'] }}"
                                                        class="ml-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none"
                                                    >
                                                        <option value="">Sin calificar</option>
                                                        @for ($valoracion = 0; $valoracion <= 10; $valoracion++)
                                                            <option value="{{ $valoracion }}">
                                                                {{ $valoracion }} / 10
                                                            </option>
                                                        @endfor
                                                    </select>
                                                </label>

                                                <span class="text-sm text-slate-500">
                                                    @if ($pregunta['valoracion_docente'] !== null)
                                                        {{ number_format($pregunta['valoracion_docente'] / 10, 2) }} puntos
                                                    @else
                                                        Pendiente
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    @else
                                        <div class="mt-3 space-y-1.5">
                                            @foreach ($pregunta['opciones'] as $opcion)
                                                <div class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm {{ $opcion['id'] === $pregunta['opcion_id'] ? ($opcion['es_correcta'] ? 'border-emerald-400 bg-emerald-50 text-emerald-800' : 'border-red-300 bg-red-50 text-red-800') : ($opcion['es_correcta'] ? 'border-emerald-200 bg-emerald-50/50 text-emerald-700' : 'border-slate-200 text-slate-600') }}">
                                                    <span>
                                                        {{ $opcion['texto'] }}
                                                        @if ($opcion['id'] === $pregunta['opcion_id'])
                                                            · Seleccionada
                                                        @endif
                                                        @if ($opcion['es_correcta'])
                                                            · Correcta
                                                        @endif
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>

                                        <p class="mt-3 text-sm font-semibold {{ $pregunta['sin_responder'] ? 'text-slate-500' : ($pregunta['es_correcta'] ? 'text-emerald-700' : 'text-red-700') }}">
                                            @if ($pregunta['sin_responder'])
                                                Sin responder · 0 puntos
                                            @elseif ($pregunta['es_correcta'])
                                                Correcta · 1 punto
                                            @else
                                                Incorrecta · 0 puntos
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">
                            @if (! $sugerida['completa'])
                                <p class="text-sm text-amber-700">
                                    Completa todas las preguntas abiertas para guardar la calificación definitiva.
                                </p>
                            @endif

                            <button
                                type="button"
                                wire:click="guardarCalificacion"
                                @disabled(! $sugerida['completa'])
                                class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition {{ $sugerida['completa'] ? 'bg-blue-600 hover:bg-blue-700' : 'cursor-not-allowed bg-slate-400' }}"
                            >
                                Guardar calificación
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($trasladoPendiente)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-slate-900">
                        Reemplazar nota oficial
                    </h3>

                    <p class="mt-3 text-sm text-slate-600">
                        El parcial {{ $trasladoPendiente['parcial'] }} ya tiene una nota oficial registrada para este estudiante.
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-4 rounded-xl bg-slate-50 p-4 text-sm">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Nota actual
                            </p>
                            <p class="mt-1 text-lg font-bold text-slate-800">
                                {{ number_format((float) $trasladoPendiente['nota_anterior'], 2) }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Nota digital
                            </p>
                            <p class="mt-1 text-lg font-bold text-blue-800">
                                {{ number_format((float) $trasladoPendiente['nota_nueva'], 2) }}
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm text-slate-600">
                        ¿Deseas reemplazar la nota oficial con la nota de la revisión digital?
                    </p>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            wire:click="cancelarTrasladoNota"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Conservar nota actual
                        </button>

                        <button
                            type="button"
                            wire:click="confirmarTrasladoNota"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700"
                        >
                            Sí, reemplazar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
