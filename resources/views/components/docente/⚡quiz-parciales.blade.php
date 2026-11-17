<div class="min-h-screen bg-slate-50">

    @if ($publicacionId === null)

    <div class="mx-auto max-w-7xl px-6 py-6">

        {{-- ENCABEZADO --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-blue-600">
                    Gestión académica
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-800">
                    Quiz / Parciales
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Crea evaluaciones digitales y publícalas para tus grupos.
                </p>
            </div>

            <a
                href="{{ route('docente.quizzes.revision') }}"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Revisión de calificaciones
            </a>
        </div>

        @if (session('mensaje'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('mensaje') }}
            </div>
        @endif

        @error('borrador')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('preguntas')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('grupoSeleccionadoId')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('evaluacion')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        @error('archivo')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $message }}
            </div>
        @enderror

        {{-- ESTRUCTURA DE DOS COLUMNAS --}}
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- COLUMNA IZQUIERDA: CONFIGURACIÓN Y CONSTRUCTOR --}}
            <div class="lg:col-span-1">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                    @if (!$pasoDos)

                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">
                            Paso 1
                        </p>

                        <h2 class="mt-1 text-lg font-bold text-slate-800">
                            {{ $borradorId ? 'Editar evaluación' : 'Nueva evaluación' }}
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Configura la materia, el tipo de evaluación y el horario antes de
                            construir las preguntas.
                        </p>

                        <form wire:submit="generarConfiguracion" class="mt-6 space-y-5">

                            {{-- GRUPO / MATERIA --}}
                            <div>
                                <p class="mb-2 block text-sm font-medium text-slate-700">
                                    Materia / grupo
                                </p>

                                @if ($borradorId)
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                        <p class="text-sm font-semibold text-slate-700">
                                            {{ $grupoSeleccionado?->materia?->nombre ?? '—' }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            Semestre {{ $grupoSeleccionado?->semestre ?? '—' }}
                                            · {{ $grupoSeleccionado?->anio ?? '—' }}
                                        </p>
                                    </div>

                                    <p class="mt-1 text-xs text-slate-500">
                                        El grupo no se puede cambiar una vez creado el borrador.
                                    </p>
                                @else
                                    <select
                                        id="grupo-quiz"
                                        wire:model.live="grupoSeleccionadoId"
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option value="">Seleccionar materia...</option>

                                        @foreach ($grupos as $grupo)
                                            <option value="{{ $grupo->id }}">
                                                {{ $grupo->materia->nombre }}
                                                — Semestre {{ $grupo->semestre }}
                                                — {{ $grupo->anio }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif

                                @error('grupoSeleccionadoId')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- TIPO --}}
                            <div>
                                <label for="tipo-quiz" class="mb-2 block text-sm font-medium text-slate-700">
                                    Tipo de evaluación
                                </label>

                                <select
                                    id="tipo-quiz"
                                    wire:model.live="tipo"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                >
                                    <option value="quiz">Quiz</option>
                                    <option value="parcial">Parcial</option>
                                </select>

                                @error('tipo')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- PARCIAL --}}
                            @if ($tipo === \App\Models\EvaluacionDigital::TIPO_PARCIAL)
                                <div>
                                    <label for="parcial-quiz" class="mb-2 block text-sm font-medium text-slate-700">
                                        Número de parcial
                                    </label>

                                    <select
                                        id="parcial-quiz"
                                        wire:model.live="parcial"
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option value="">Seleccionar parcial...</option>

                                        @foreach ($parcialesEstado as $periodo => $estado)
                                            <option
                                                value="{{ $periodo }}"
                                                @selected($this->parcial === $periodo)
                                                @disabled(!$estado['seleccionable'])
                                            >
                                                {{ $periodo }} — {{ $estado['etiqueta'] }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error('parcial')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror

                                    @if ($parcialesEstado)
                                        <div class="mt-3 space-y-1.5">
                                            @foreach ($parcialesEstado as $periodo => $estado)
                                                <div class="flex items-center justify-between gap-2 text-xs">
                                                    <span class="font-medium text-slate-600">{{ $periodo }}</span>
                                                    <span class="rounded-md px-2 py-0.5 font-semibold {{ $estado['clase'] }}">
                                                        {{ $estado['etiqueta'] }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- TÍTULO --}}
                            <div>
                                <label for="titulo-quiz" class="mb-2 block text-sm font-medium text-slate-700">
                                    Título
                                </label>

                                <input
                                    id="titulo-quiz"
                                    type="text"
                                    wire:model.live="titulo"
                                    placeholder="Ej. Parcial de bases de datos"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                >

                                @error('titulo')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- FECHA Y HORAS --}}
                            <div>
                                <label for="fecha-quiz" class="mb-2 block text-sm font-medium text-slate-700">
                                    Fecha
                                </label>

                                <input
                                    id="fecha-quiz"
                                    type="date"
                                    wire:model.live="fecha"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                >

                                @error('fecha')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="hora-inicio-quiz" class="mb-2 block text-sm font-medium text-slate-700">
                                        Hora de inicio
                                    </label>

                                    <input
                                        id="hora-inicio-quiz"
                                        type="time"
                                        wire:model.live="horaInicio"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                    >

                                    @error('horaInicio')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="hora-fin-quiz" class="mb-2 block text-sm font-medium text-slate-700">
                                        Hora de finalización
                                    </label>

                                    <input
                                        id="hora-fin-quiz"
                                        type="time"
                                        wire:model.live="horaFin"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                    >

                                    @error('horaFin')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- MODALIDAD --}}
                            <div>
                                <label for="modalidad-quiz" class="mb-2 block text-sm font-medium text-slate-700">
                                    Modalidad
                                </label>

                                <select
                                    id="modalidad-quiz"
                                    wire:model.live="modalidad"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                >
                                    <option value="preguntas">Preguntas</option>
                                    <option value="archivo">Archivo</option>
                                    <option value="mixta">Mixta</option>
                                </select>

                                @error('modalidad')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="generarConfiguracion"
                                class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="generarConfiguracion">
                                    Generar
                                </span>
                                <span wire:loading wire:target="generarConfiguracion">
                                    Validando...
                                </span>
                            </button>

                        </form>

                    @else

                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Paso 2
                        </p>

                        <h2 class="mt-1 text-lg font-bold text-slate-800">
                            Configuración completada
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            La información general está lista. Agrega las preguntas de la
                            evaluación.
                        </p>

                        @if ($borradorId)
                            <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">
                                    Borrador activo #{{ $borradorId }}
                                </p>
                                <p class="mt-1 text-sm font-semibold text-slate-800">
                                    {{ $titulo }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-600">
                                    {{ $grupoSeleccionado?->materia?->nombre ?? '—' }}
                                    · Semestre {{ $grupoSeleccionado?->semestre ?? '—' }}
                                    · {{ $grupoSeleccionado?->anio ?? '—' }}
                                    · Último guardado: {{ $ultimoGuardado ?? '—' }}
                                </p>
                            </div>
                        @endif

                        <div class="mt-3 text-xs">
                            <span
                                wire:loading
                                wire:target="guardarBorrador,guardarYContinuarCambio"
                                class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 font-semibold text-blue-700"
                            >
                                Guardando...
                            </span>

                            <span
                                wire:loading.remove
                                wire:target="guardarBorrador,guardarYContinuarCambio"
                            >
                                @if ($cambiosSinGuardar)
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 font-semibold text-amber-700">
                                        Tienes cambios sin guardar
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">
                                        Todos los cambios guardados
                                    </span>
                                @endif
                            </span>
                        </div>

                        <div class="mt-5 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-500">Materia</span>
                                <span class="text-right font-semibold text-slate-700">
                                    {{ $grupoSeleccionado?->materia?->nombre ?? '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-500">Grupo</span>
                                <span class="text-right font-semibold text-slate-700">
                                    Semestre {{ $grupoSeleccionado?->semestre ?? '—' }}
                                    · {{ $grupoSeleccionado?->anio ?? '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-500">Tipo</span>
                                <span class="text-right font-semibold text-slate-700">
                                    {{ $tipo === 'parcial' ? 'Parcial ' . $parcial : 'Quiz' }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-500">Fecha</span>
                                <span class="text-right font-semibold text-slate-700">
                                    {{ $fecha }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-500">Horario</span>
                                <span class="text-right font-semibold text-slate-700">
                                    {{ $horaInicio }} – {{ $horaFin }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-500">Modalidad</span>
                                <span class="text-right font-semibold text-slate-700">
                                    {{ ucfirst($modalidad) }}
                                </span>
                            </div>
                        </div>

                        @if (! $borradorPublicado)
                            <div class="mt-6 border-t border-slate-100 pt-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">
                                        Agregar pregunta
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Construye la pregunta y marca la respuesta correcta.
                                    </p>
                                </div>

                                <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    {{ count($preguntas) }} {{ count($preguntas) === 1 ? 'pregunta' : 'preguntas' }}
                                </span>
                            </div>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label for="pregunta-enunciado" class="mb-2 block text-sm font-medium text-slate-700">
                                        Enunciado
                                    </label>

                                    <textarea
                                        id="pregunta-enunciado"
                                        rows="3"
                                        wire:model.live.debounce.500ms="preguntaActual.enunciado"
                                        placeholder="1. Escribe tu pregunta"
                                        class="w-full resize-none rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                    ></textarea>
                                </div>

                                <div>
                                    <label for="pregunta-tipo" class="mb-2 block text-sm font-medium text-slate-700">
                                        Tipo de respuesta
                                    </label>

                                    <select
                                        id="pregunta-tipo"
                                        wire:change="cambiarTipoPregunta($event.target.value)"
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option value="oracion" @selected($preguntaActual['tipo'] === 'oracion')>
                                            Oración
                                        </option>
                                        <option value="multiple" @selected($preguntaActual['tipo'] === 'multiple')>
                                            Respuesta múltiple
                                        </option>
                                        <option value="verdadero_falso" @selected($preguntaActual['tipo'] === 'verdadero_falso')>
                                            Verdadero o falso
                                        </option>
                                    </select>
                                </div>

                                @if ($preguntaActual['tipo'] === 'multiple')
                                    <div>
                                        <label for="cantidad-opciones" class="mb-2 block text-sm font-medium text-slate-700">
                                            Cantidad de opciones
                                        </label>

                                        <select
                                            id="cantidad-opciones"
                                            wire:model.live="cantidadOpciones"
                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                        >
                                            @for ($cantidad = 2; $cantidad <= 6; $cantidad++)
                                                <option value="{{ $cantidad }}">{{ $cantidad }} opciones</option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        @if (is_array($preguntaActual['opciones']))
                                            @foreach ($preguntaActual['opciones'] as $indice => $opcion)
                                                <div class="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        name="opcion-correcta"
                                                        value="{{ $indice }}"
                                                        wire:model.live="indiceCorrecto"
                                                        class="h-4 w-4 shrink-0 border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                        aria-label="Marcar opción {{ $indice + 1 }} como correcta"
                                                    >

                                                    <input
                                                        type="text"
                                                        wire:model.live.debounce.500ms="preguntaActual.opciones.{{ $indice }}.texto"
                                                        placeholder="Opción {{ $indice + 1 }}"
                                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                                    >
                                                </div>
                                            @endforeach
                                        @else
                                            <p class="text-xs text-red-500">
                                                La estructura de opciones no es válida.
                                            </p>
                                        @endif
                                    </div>
                                @elseif ($preguntaActual['tipo'] === 'verdadero_falso')
                                    <div class="space-y-2">
                                        @if (is_array($preguntaActual['opciones']))
                                            @foreach ($preguntaActual['opciones'] as $indice => $opcion)
                                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                                    <input
                                                        type="radio"
                                                        name="opcion-correcta"
                                                        value="{{ $indice }}"
                                                        wire:model.live="indiceCorrecto"
                                                        class="h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                    >
                                                    {{ is_array($opcion) ? ($opcion['texto'] ?? '') : '' }}
                                                </label>
                                            @endforeach
                                        @else
                                            <p class="text-xs text-red-500">
                                                La estructura de opciones no es válida.
                                            </p>
                                        @endif
                                    </div>
                                @else
                                    <p class="rounded-lg bg-slate-50 px-3 py-2.5 text-sm text-slate-500">
                                        El estudiante responderá con texto libre. La revisión será manual.
                                    </p>
                                @endif

                                @error('preguntaActual')
                                    <p class="text-xs text-red-500">{{ $message }}</p>
                                @enderror

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        wire:click="solicitarAgregarPregunta"
                                        class="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                                    >
                                        Agregar
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="solicitarCancelarEvaluacion"
                                        class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if ($borradorPublicado)
                            <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <p class="text-sm font-semibold text-emerald-800">
                                    Evaluación publicada correctamente.
                                </p>
                                <p class="mt-1 text-xs text-emerald-700">
                                    Esta evaluación ya no se puede editar como borrador.
                                    Puedes consultarla en la sección «Publicadas».
                                </p>
                            </div>
                        @else
                            <div class="mt-5 space-y-2">
                                <button
                                    type="button"
                                    wire:click="guardarBorrador"
                                    wire:loading.attr="disabled"
                                    wire:target="guardarBorrador"
                                    class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="guardarBorrador">
                                        Guardar borrador
                                    </span>
                                    <span wire:loading wire:target="guardarBorrador">
                                        Guardando...
                                    </span>
                                </button>

                                @if ($ultimoGuardado)
                                    <p class="text-center text-xs text-slate-400">
                                        Último guardado: {{ $ultimoGuardado }}
                                    </p>
                                @endif
                            </div>

                            @if ($solicitudPublicacionPreparada)
                                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    <p>
                                        Solicitud de publicación preparada para la versión guardada.
                                    </p>

                                    <button
                                        type="button"
                                        wire:click="publicarSolicitudPreparada"
                                        wire:loading.attr="disabled"
                                        wire:target="publicarSolicitudPreparada"
                                        class="mt-2 w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        <span wire:loading.remove wire:target="publicarSolicitudPreparada">
                                            Publicar ahora
                                        </span>
                                        <span wire:loading wire:target="publicarSolicitudPreparada">
                                            Publicando...
                                        </span>
                                    </button>
                                </div>
                            @elseif ($solicitudPublicacionInvalidada)
                                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                    La solicitud preparada ya no es válida porque cambió el formulario o la versión guardada. Vuelve a confirmar la publicación.
                                </div>
                            @endif

                            @if ($borradorId)
                                <button
                                    type="button"
                                    wire:click="solicitarPublicacion"
                                    wire:loading.attr="disabled"
                                    wire:target="solicitarPublicacion"
                                    class="mt-2 w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="solicitarPublicacion">
                                        Publicar
                                    </span>
                                    <span wire:loading wire:target="solicitarPublicacion">
                                        Preparando...
                                    </span>
                                </button>
                            @else
                                <p class="mt-2 text-center text-xs text-slate-400">
                                    Guarda el borrador para poder publicar.
                                </p>
                            @endif

                            <button
                                type="button"
                                wire:click="volverAConfiguracion"
                                class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                            >
                                Volver a configuración
                            </button>
                        @endif

                    @endif

                </div>
            </div>

            {{-- COLUMNA DERECHA: VISTA PREVIA --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:sticky lg:top-6">

                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">
                                Vista previa
                            </h2>

                            <p class="mt-1 text-sm text-slate-500">
                                Resumen de la configuración de la evaluación.
                            </p>
                        </div>

                        @if ($pasoDos)
                            <span class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                                Configuración completa
                            </span>
                        @endif
                    </div>

                    @if (!$grupoSeleccionado)

                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                                ◫
                            </div>

                            <h3 class="mt-4 font-semibold text-slate-700">
                                Aún no hay una evaluación en construcción
                            </h3>

                            <p class="mx-auto mt-1 max-w-md text-sm text-slate-400">
                                Selecciona una materia y completa la configuración para ver el
                                resumen de la evaluación.
                            </p>
                        </div>

                    @else

                        <div class="space-y-5 px-6 py-6">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Título
                                </p>
                                <h3 class="mt-1 text-xl font-bold text-slate-800">
                                    {{ $titulo !== '' ? $titulo : 'Sin título' }}
                                </h3>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Materia y grupo
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-slate-700">
                                        {{ $grupoSeleccionado->materia->nombre }}
                                    </p>
                                    <p class="text-sm text-slate-500">
                                        Semestre {{ $grupoSeleccionado->semestre }}
                                        · {{ $grupoSeleccionado->anio }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Tipo
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-slate-700">
                                        {{ $tipo === 'parcial' ? 'Parcial ' . ($parcial ?: '—') : 'Quiz' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Fecha
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-slate-700">
                                        {{ $fecha ?: 'Sin fecha' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Horario
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-slate-700">
                                        {{ $horaInicio }} – {{ $horaFin }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Modalidad
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-slate-700">
                                        {{ ucfirst($modalidad) }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Docente
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-slate-700">
                                        {{ auth()->user()->name ?? 'Docente' }}
                                    </p>
                                </div>
                            </div>

                            @if (count($preguntas) === 0)

                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                                    <p class="text-sm font-semibold text-slate-700">
                                        Preguntas de la evaluación
                                    </p>
                                    <p class="mt-1 text-sm text-slate-400">
                                        Todavía no se han agregado preguntas.
                                    </p>
                                </div>

                            @else

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-700">
                                            Preguntas de la evaluación
                                        </p>
                                        <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                            {{ count($preguntas) }}
                                        </span>
                                    </div>

                                    @foreach ($preguntas as $indice => $pregunta)
                                        @php
                                            $etiquetaTipo = match ($pregunta['tipo']) {
                                                'oracion' => 'Oración',
                                                'multiple' => 'Respuesta múltiple',
                                                'verdadero_falso' => 'Verdadero o falso',
                                                default => $pregunta['tipo'],
                                            };
                                        @endphp

                                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                        Pregunta {{ $indice + 1 }} · {{ $etiquetaTipo }}
                                                    </p>

                                                    <p class="mt-1 text-sm font-semibold text-slate-800">
                                                        {{ $pregunta['enunciado'] }}
                                                    </p>
                                                </div>

                                                <button
                                                    type="button"
                                                    wire:click="eliminarPregunta({{ $indice }})"
                                                    wire:confirm="¿Eliminar esta pregunta?"
                                                    class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50"
                                                >
                                                    Eliminar
                                                </button>
                                            </div>

                                            @if ($pregunta['tipo'] === 'oracion')
                                                <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                                    Respuesta abierta · revisión manual
                                                </p>
                                            @else
                                                <div class="mt-3 space-y-1.5">
                                                    @if (is_array($pregunta['opciones']))
                                                        @foreach ($pregunta['opciones'] as $indiceOpcion => $opcion)
                                                            @php
                                                                $esCorrecta = ($pregunta['indice_correcto'] ?? null) === $indiceOpcion;
                                                            @endphp

                                                            <div class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm
                                                                {{ $esCorrecta
                                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                                    : 'border-slate-200 text-slate-600' }}">
                                                                <span>{{ is_array($opcion) ? ($opcion['texto'] ?? '') : '' }}</span>

                                                                @if ($esCorrecta)
                                                                    <span class="text-xs font-semibold text-emerald-700">
                                                                        Correcta
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <p class="text-xs text-red-500">
                                                            La estructura de opciones no es válida.
                                                        </p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                            @endif
                        </div>

                    @endif

                </div>
            </div>

        </div>

        {{-- MIS BORRADORES --}}
        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Mis borradores
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Continúa una evaluación guardada o inicia una nueva.
                    </p>
                </div>

                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <button
                        type="button"
                        wire:click="solicitarNuevoBorrador"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
                    >
                        Nueva evaluación
                    </button>

                    <div class="text-xs">
                        <span
                            wire:loading
                            wire:target="guardarBorrador,guardarYContinuarCambio"
                            class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 font-semibold text-blue-700"
                        >
                            Guardando...
                        </span>

                        <span
                            wire:loading.remove
                            wire:target="guardarBorrador,guardarYContinuarCambio"
                        >
                            @if ($cambiosSinGuardar)
                                <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 font-semibold text-amber-700">
                                    Tienes cambios sin guardar
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">
                                    Todos los cambios guardados
                                </span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            @if ($borradores->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-400">
                    Todavía no tienes borradores guardados.
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($borradores as $borrador)
                        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-800">
                                        {{ $borrador->titulo }}
                                    </p>

                                    @if ($borradorId === $borrador->id)
                                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                            Editando
                                        </span>
                                    @endif
                                </div>

                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $borrador->grupo?->materia?->nombre ?? 'Sin materia' }}
                                    · {{ $borrador->grupo?->semestre ?? '—' }}
                                    · {{ $borrador->grupo?->anio ?? '—' }}
                                    · {{ $borrador->tipo === 'parcial' ? 'Parcial ' . $borrador->parcial : 'Quiz' }}
                                </p>

                                <p class="mt-1 text-xs text-slate-400">
                                    Actualizado: {{ $borrador->updated_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                wire:click="solicitarEditarBorrador({{ $borrador->id }})"
                                class="shrink-0 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Continuar
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- PUBLICADAS --}}
        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">
                    Publicadas
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Consulta las evaluaciones publicadas. Su contenido es de solo lectura.
                </p>
            </div>

            @if ($publicaciones->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-400">
                    Todavía no tienes evaluaciones publicadas.
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($publicaciones as $publicacion)
                        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-800">
                                        {{ $publicacion->titulo }}
                                    </p>

                                    <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                        Publicada
                                    </span>
                                </div>

                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $publicacion->grupo?->materia?->nombre ?? 'Sin materia' }}
                                    · Semestre {{ $publicacion->grupo?->semestre ?? '—' }}
                                    · {{ $publicacion->grupo?->anio ?? '—' }}
                                    · {{ $publicacion->tipo === \App\Models\EvaluacionDigital::TIPO_PARCIAL
                                        ? 'Parcial ' . ($publicacion->parcial ?? '—')
                                        : 'Quiz' }}
                                </p>

                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $publicacion->fecha?->format('d/m/Y') ?? 'Sin fecha' }}
                                    · {{ substr((string) $publicacion->hora_inicio, 0, 5) }}
                                    – {{ substr((string) $publicacion->hora_fin, 0, 5) }}
                                </p>

                                <p class="mt-1 text-xs text-slate-400">
                                    Publicada: {{ $publicacion->publicada_at?->format('d/m/Y H:i') ?? '—' }}
                                </p>
                            </div>

                            <button
                                type="button"
                                wire:click="abrirPublicacion({{ $publicacion->id }})"
                                class="shrink-0 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Ver
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

    </div>

    {{-- CONFIRMAR AGREGAR PREGUNTA --}}
    @if ($mostrarConfirmacionPregunta)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-800">
                    ¿Estás seguro de agregar esta pregunta?
                </h3>

                <p class="mt-2 text-sm leading-6 text-slate-500">
                    La pregunta se añadirá a la vista previa de la evaluación.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        wire:click="seguirEditandoPregunta"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Seguir editando
                    </button>

                    <button
                        type="button"
                        wire:click="confirmarAgregarPregunta"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                    >
                        Sí, agregar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- CONFIRMAR DESCARTAR EVALUACIÓN --}}
    @if ($mostrarConfirmacionCancelarEvaluacion)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-800">
                    ¿Descartar esta evaluación?
                </h3>

                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Si descartas esta evaluación, perderás los datos registrados.
                    ¿Deseas continuar?
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        wire:click="seguirEditandoEvaluacion"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Seguir editando
                    </button>

                    <button
                        type="button"
                        wire:click="descartarEvaluacion"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        Descartar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- CONFIRMAR CAMBIO CON CAMBIOS SIN GUARDAR --}}
    @if ($mostrarConfirmacionCambio)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-800">
                    Tienes cambios sin guardar
                </h3>

                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Si continúas sin guardar, perderás los cambios de la evaluación
                    actual. ¿Qué deseas hacer?
                </p>

                <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        wire:click="seguirEditandoCambio"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Seguir editando
                    </button>

                    <button
                        type="button"
                        wire:click="descartarCambiosYContinuar"
                        class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                    >
                        Descartar cambios
                    </button>

                    <button
                        type="button"
                        wire:click="guardarYContinuarCambio"
                        wire:loading.attr="disabled"
                        wire:target="guardarYContinuarCambio"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="guardarYContinuarCambio">
                            Guardar y continuar
                        </span>
                        <span wire:loading wire:target="guardarYContinuarCambio">
                            Guardando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- CONFIRMAR PUBLICACIÓN --}}
    @if ($mostrarConfirmacionPublicacion && $resumenPublicacion)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-800">
                    Publicar evaluación
                </h3>

                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Revisa los datos guardados antes de continuar.
                </p>

                <div class="mt-5 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Título</span>
                        <span class="text-right font-semibold text-slate-700">
                            {{ $resumenPublicacion['titulo'] }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Materia / grupo</span>
                        <span class="text-right font-semibold text-slate-700">
                            {{ $resumenPublicacion['materia'] }}
                            · Semestre {{ $resumenPublicacion['semestre'] ?? '—' }}
                            · {{ $resumenPublicacion['anio'] ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Tipo</span>
                        <span class="text-right font-semibold text-slate-700">
                            {{ $resumenPublicacion['tipo'] === 'Parcial'
                                ? 'Parcial ' . ($resumenPublicacion['parcial'] ?? '—')
                                : 'Quiz' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Fecha</span>
                        <span class="text-right font-semibold text-slate-700">
                            {{ $resumenPublicacion['fecha'] }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Horario</span>
                        <span class="text-right font-semibold text-slate-700">
                            {{ $resumenPublicacion['horario'] }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Preguntas</span>
                        <span class="text-right font-semibold text-slate-700">
                            {{ $resumenPublicacion['cantidad_preguntas'] }}
                        </span>
                    </div>
                </div>

                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    Una vez publicada, la evaluación no podrá editarse.
                </p>

                @if ($publicacionConCambios)
                    <p class="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                        Tienes cambios sin guardar. Para preparar la publicación de la versión actual,
                        se guardarán primero.
                    </p>
                @endif

                <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        wire:click="cancelarPublicacion"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        wire:click="confirmarPublicacion"
                        wire:loading.attr="disabled"
                        wire:target="confirmarPublicacion"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="confirmarPublicacion">
                            {{ $publicacionConCambios ? 'Guardar y publicar' : 'Publicar' }}
                        </span>
                        <span wire:loading wire:target="confirmarPublicacion">
                            Preparando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @endif

    {{-- DETALLE DE PUBLICACIÓN (SOLO LECTURA) --}}
    @if ($publicacionSeleccionada)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6">
            <div class="max-h-full w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Publicación · Solo lectura
                        </p>

                        <h3 class="mt-1 text-lg font-bold text-slate-800">
                            {{ $publicacionSeleccionada->titulo }}
                        </h3>
                    </div>

                    <button
                        type="button"
                        wire:click="cerrarPublicacion"
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
                            {{ $publicacionSeleccionada->grupo?->materia?->nombre ?? 'Sin materia' }}
                        </p>
                        <p class="text-slate-500">
                            Semestre {{ $publicacionSeleccionada->grupo?->semestre ?? '—' }}
                            · {{ $publicacionSeleccionada->grupo?->anio ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Tipo
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $publicacionSeleccionada->tipo === \App\Models\EvaluacionDigital::TIPO_PARCIAL
                                ? 'Parcial ' . ($publicacionSeleccionada->parcial ?? '—')
                                : 'Quiz' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Fecha
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $publicacionSeleccionada->fecha?->format('d/m/Y') ?? 'Sin fecha' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Horario
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ substr((string) $publicacionSeleccionada->hora_inicio, 0, 5) }}
                            – {{ substr((string) $publicacionSeleccionada->hora_fin, 0, 5) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Publicada el
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ $publicacionSeleccionada->publicada_at?->format('d/m/Y H:i') ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Modalidad
                        </p>
                        <p class="mt-1 font-medium text-slate-700">
                            {{ ucfirst($publicacionSeleccionada->modalidad) }}
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-700">
                            Preguntas
                        </p>

                        <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                            {{ $publicacionSeleccionada->preguntas->count() }}
                        </span>
                    </div>

                    @if ($publicacionSeleccionada->preguntas->isEmpty())
                        <div class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-center text-sm text-slate-400">
                            La publicación no tiene preguntas registradas.
                        </div>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach ($publicacionSeleccionada->preguntas as $indice => $pregunta)
                                @php
                                    $etiquetaTipo = match ($pregunta->tipo) {
                                        'oracion' => 'Oración',
                                        'multiple' => 'Respuesta múltiple',
                                        'verdadero_falso' => 'Verdadero o falso',
                                        default => $pregunta->tipo,
                                    };
                                @endphp

                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Pregunta {{ $indice + 1 }} · {{ $etiquetaTipo }}
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-slate-800">
                                        {{ $pregunta->enunciado }}
                                    </p>

                                    @if ($pregunta->tipo === 'oracion')
                                        <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                            Respuesta abierta · revisión manual
                                        </p>
                                    @else
                                        <div class="mt-3 space-y-1.5">
                                            @foreach ($pregunta->opciones as $opcion)
                                                <div class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm
                                                    {{ $opcion->es_correcta
                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                        : 'border-slate-200 text-slate-600' }}">
                                                    <span>{{ $opcion->texto }}</span>

                                                    @if ($opcion->es_correcta)
                                                        <span class="text-xs font-semibold text-emerald-700">
                                                            Correcta
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>
