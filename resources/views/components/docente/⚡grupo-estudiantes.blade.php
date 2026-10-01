<div
    class="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 lg:px-8"
    x-data="{
        confirmacionGuardar: false
    }"
>

    <div class="mx-auto max-w-7xl">

        {{-- ====================================================== --}}
        {{-- ENCABEZADO                                             --}}
        {{-- ====================================================== --}}

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    Gestión de grupos
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    Crea, edita y administra tus grupos y estudiantes.
                </p>
            </div>

            <button
                type="button"
                onclick="document.getElementById('crear-grupo').scrollIntoView({ behavior: 'smooth' })"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
            >
                <span class="text-lg leading-none">+</span>
                Nuevo grupo
            </button>

        </div>


        {{-- ====================================================== --}}
        {{-- MENSAJE                                                --}}
        {{-- ====================================================== --}}

        @if (session()->has('mensaje'))

            <div
                class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
            >
                {{ session('mensaje') }}
            </div>

        @endif


        {{-- ====================================================== --}}
        {{-- CONTENIDO PRINCIPAL                                    --}}
        {{-- ====================================================== --}}

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">


            {{-- ================================================== --}}
            {{-- COLUMNA IZQUIERDA                                  --}}
            {{-- ================================================== --}}

            <div class="space-y-6 lg:col-span-2">


                {{-- ================================================= --}}
                {{-- CREAR / EDITAR GRUPO                             --}}
                {{-- ================================================= --}}

                <section
                    id="crear-grupo"
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
                >

                    <div class="mb-6 flex items-start justify-between gap-4">

                        <div>

                            <h2 class="text-lg font-bold text-gray-800">

                                @if ($grupoEditandoId)
                                    EDITAR GRUPO
                                @else
                                    CREAR NUEVO GRUPO
                                @endif

                            </h2>

                            <p class="mt-1 text-sm text-gray-500">

                                @if ($grupoEditandoId)
                                    Modifica los datos del grupo seleccionado.
                                @else
                                    Registra la asignatura y el horario del grupo.
                                @endif

                            </p>

                        </div>


                        @if ($grupoEditandoId)

                            <span class="inline-flex shrink-0 items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                Modo edición
                            </span>

                        @endif

                    </div>


                    <form
                        wire:submit.prevent="
                            @if ($grupoEditandoId)
                                $set('mostrarConfirmacionGuardar', true)
                            @else
                                $wire.guardarGrupo()
                            @endif
                        "
                        class="space-y-5"
                    >

                        {{-- Materia --}}
                        <div>

                            <label
                                for="nombreMateria"
                                class="mb-1.5 block text-sm font-medium text-gray-700"
                            >
                                Asignatura
                            </label>

                            <input
                                id="nombreMateria"
                                type="text"
                                wire:model="nombreMateria"
                                placeholder="Ej. Programación Web"
                                autocomplete="off"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            >

                            <p class="mt-1.5 text-xs text-gray-500">
                                El nombre se normalizará automáticamente al guardar.
                            </p>

                            @error('nombreMateria')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Período --}}
                        <div>

                            <label class="mb-1.5 block text-sm font-medium text-gray-700">
                                Período académico
                            </label>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">

                                @if ($periodoActivo)

                                    <span class="font-semibold text-gray-700">
                                        Semestre {{ $periodoActivo->semestre }}
                                    </span>

                                    <span class="mx-1 text-gray-400">
                                        ·
                                    </span>

                                    <span class="text-gray-600">
                                        {{ $periodoActivo->anio }}
                                    </span>

                                @else

                                    <span class="text-amber-600">
                                        No existe un período académico activo.
                                    </span>

                                @endif

                            </div>

                        </div>


                        {{-- Fecha --}}
                        <div>

                            <label
                                for="fechaCreacion"
                                class="mb-1.5 block text-sm font-medium text-gray-700"
                            >
                                Fecha de creación
                            </label>

                            <input
                                id="fechaCreacion"
                                type="date"
                                wire:model="fechaCreacion"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            >

                            @error('fechaCreacion')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- Horario --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                            <div>

                                <label
                                    for="horaInicio"
                                    class="mb-1.5 block text-sm font-medium text-gray-700"
                                >
                                    Hora de inicio
                                </label>

                                <input
                                    id="horaInicio"
                                    type="time"
                                    wire:model="horaInicio"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                >

                                @error('horaInicio')
                                    <p class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>


                            <div>

                                <label
                                    for="horaFin"
                                    class="mb-1.5 block text-sm font-medium text-gray-700"
                                >
                                    Hora de finalización
                                </label>

                                <input
                                    id="horaFin"
                                    type="time"
                                    wire:model="horaFin"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                >

                                @error('horaFin')
                                    <p class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                        </div>


                        {{-- Botones --}}
                        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">

                            @if ($grupoEditandoId)

                                <button
                                    type="button"
                                    wire:click="cancelarEdicion"
                                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                                >
                                    Cancelar
                                </button>

                            @endif


                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >

                                <span wire:loading.remove wire:target="guardarGrupo">
                                    Guardar
                                </span>

                                <span wire:loading wire:target="guardarGrupo">
                                    Guardando...
                                </span>

                            </button>

                        </div>

                    </form>

                </section>


                {{-- ================================================= --}}
                {{-- GRUPO SELECCIONADO                               --}}
                {{-- ================================================= --}}

                @if ($grupo)

                    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

                        <div class="mb-6 border-b border-gray-100 pb-5">

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                                <div>

                                    <h2 class="text-xl font-bold uppercase text-gray-800">
                                        {{ $grupo->materia->nombre }}
                                    </h2>

                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">

                                        <span>
                                            Semestre {{ $grupo->semestre }}
                                            · {{ $grupo->anio }}
                                        </span>

                                        @if ($grupo->hora_inicio && $grupo->hora_fin)

                                            <span>
                                                Horario:
                                                {{ substr($grupo->hora_inicio, 0, 5) }}
                                                -
                                                {{ substr($grupo->hora_fin, 0, 5) }}
                                            </span>

                                        @else

                                            <span class="text-amber-600">
                                                Horario pendiente
                                            </span>

                                        @endif

                                    </div>

                                </div>


                                <span class="inline-flex w-fit items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">

                                    {{ $grupo->estudiantes->count() }}

                                    {{ $grupo->estudiantes->count() === 1 ? 'estudiante' : 'estudiantes' }}

                                </span>

                            </div>

                        </div>


                        {{-- Buscar --}}
                        <div class="mb-6">

                            <label
                                for="busqueda"
                                class="mb-1.5 block text-sm font-medium text-gray-700"
                            >
                                Buscar estudiante
                            </label>

                            <div class="relative">

                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    🔎
                                </span>

                                <input
                                    id="busqueda"
                                    type="text"
                                    wire:model.live.debounce.300ms="busqueda"
                                    placeholder="Nombre o correo electrónico..."
                                    autocomplete="off"
                                    class="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 text-sm text-gray-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                >

                            </div>

                        </div>


                        {{-- Resultados --}}
                        @if (trim($busqueda) !== '')

                            <div class="mb-6">

                                <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500">
                                    Estudiantes encontrados
                                </h3>


                                @if ($estudiantes->count())

                                    <div class="divide-y divide-gray-100 rounded-lg border border-gray-200">

                                        @foreach ($estudiantes as $estudiante)

                                            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">

                                                <div class="min-w-0">

                                                    <p class="truncate text-sm font-semibold text-gray-800">
                                                        {{ $estudiante->user->name }}
                                                    </p>

                                                    <p class="truncate text-xs text-gray-500">
                                                        {{ $estudiante->user->email }}
                                                    </p>

                                                </div>


                                                @if (in_array($estudiante->id, $estudiantesGrupoIds))

                                                    <span class="inline-flex w-fit rounded-md bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700">
                                                        Ya pertenece al grupo
                                                    </span>

                                                @else

                                                    <button
                                                        type="button"
                                                        wire:click="agregar({{ $estudiante->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="agregar({{ $estudiante->id }})"
                                                        class="inline-flex w-fit items-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                                                    >

                                                        <span
                                                            wire:loading.remove
                                                            wire:target="agregar({{ $estudiante->id }})"
                                                        >
                                                            <span class="text-base leading-none">+</span>
                                                            Agregar
                                                        </span>

                                                        <span
                                                            wire:loading
                                                            wire:target="agregar({{ $estudiante->id }})"
                                                        >
                                                            Agregando...
                                                        </span>

                                                    </button>

                                                @endif

                                            </div>

                                        @endforeach

                                    </div>

                                @else

                                    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center">

                                        <p class="text-sm font-medium text-gray-600">
                                            No se encontraron estudiantes.
                                        </p>

                                        <p class="mt-1 text-xs text-gray-500">
                                            Verifica el nombre o correo electrónico.
                                        </p>

                                    </div>

                                @endif

                            </div>

                        @endif


                        {{-- Estudiantes del grupo --}}
                        <div>

                            <div class="mb-3 flex items-center justify-between">

                                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">
                                    Estudiantes del grupo
                                </h3>

                                <span class="text-xs text-gray-400">
                                    {{ $grupo->estudiantes->count() }} registrados
                                </span>

                            </div>


                            @if ($grupo->estudiantes->count())

                                <div class="overflow-hidden rounded-lg border border-gray-200">

                                    <div class="divide-y divide-gray-100">

                                        @foreach ($grupo->estudiantes as $indice => $estudiante)

                                            <div class="flex items-center justify-between gap-4 px-4 py-3">

                                                <div class="flex min-w-0 items-center gap-3">

                                                    <span class="w-6 text-sm font-semibold text-gray-400">
                                                        {{ $indice + 1 }}.
                                                    </span>

                                                    <div class="min-w-0">

                                                        <p class="truncate text-sm font-medium text-gray-800">
                                                            {{ $estudiante->user->name }}
                                                        </p>

                                                        <p class="truncate text-xs text-gray-500">
                                                            {{ $estudiante->user->email }}
                                                        </p>

                                                    </div>

                                                </div>


                                                <button
                                                    type="button"
                                                    wire:click="quitar({{ $estudiante->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="quitar({{ $estudiante->id }})"
                                                    class="shrink-0 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                                                >

                                                    <span
                                                        wire:loading.remove
                                                        wire:target="quitar({{ $estudiante->id }})"
                                                    >
                                                        Quitar
                                                    </span>

                                                    <span
                                                        wire:loading
                                                        wire:target="quitar({{ $estudiante->id }})"
                                                    >
                                                        Quitando...
                                                    </span>

                                                </button>

                                            </div>

                                        @endforeach

                                    </div>

                                </div>

                            @else

                                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">

                                    <p class="text-sm font-medium text-gray-600">
                                        Este grupo todavía no tiene estudiantes.
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        Usa el buscador para agregar estudiantes registrados.
                                    </p>

                                </div>

                            @endif

                        </div>

                    </section>

                @else

                    <section class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-sm">

                        <div class="mx-auto max-w-md">

                            <div class="mb-3 text-4xl">
                                📚
                            </div>

                            <h2 class="text-lg font-bold text-gray-700">
                                Selecciona un grupo
                            </h2>

                            <p class="mt-1 text-sm text-gray-500">
                                Selecciona uno de tus grupos en el panel de la derecha para administrar sus estudiantes.
                            </p>

                        </div>

                    </section>

                @endif

            </div>


{{-- ================================================== --}}
{{-- MIS GRUPOS                                          --}}
{{-- ================================================== --}}

<aside class="lg:col-span-1">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        {{-- Encabezado --}}
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-bold tracking-wide text-slate-800">
                MIS GRUPOS
            </h2>

            <p class="mt-0.5 text-sm text-slate-500">
                {{ $grupos->count() }} {{ $grupos->count() === 1 ? 'grupo' : 'grupos' }}
            </p>
        </div>

        {{-- Lista de grupos --}}
        <div class="divide-y divide-slate-100">

            @forelse ($grupos as $grupoItem)

                @php
                    $seleccionado = $grupoSeleccionadoId === $grupoItem->id;
                @endphp

                <div
                    class="relative px-4 py-4 transition
                        {{ $seleccionado
                            ? 'bg-emerald-50'
                            : 'bg-white hover:bg-slate-50' }}"
                >

                    <div class="flex items-start gap-3">

                        {{-- Indicador del grupo seleccionado --}}
                        <div class="flex w-3 shrink-0 justify-center pt-1.5">

                            @if ($seleccionado)
                                <span
                                    class="block h-3 w-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100"
                                    title="Grupo seleccionado"
                                ></span>
                            @endif

                        </div>

                        {{-- Información del grupo --}}
                        <button
                            type="button"
                            wire:click="seleccionarGrupo({{ $grupoItem->id }})"
                            class="min-w-0 flex-1 text-left"
                        >

                            <div class="truncate font-semibold text-slate-800">
                                {{ $grupoItem->materia->nombre }}
                            </div>

                            <div class="mt-1 text-sm text-slate-500">
                                Sem {{ $grupoItem->semestre }} · {{ $grupoItem->anio }}
                            </div>

                            @if ($grupoItem->hora_inicio && $grupoItem->hora_fin)

                                <div class="mt-1 flex items-center gap-1.5 text-sm text-slate-600">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.8"
                                        stroke="currentColor"
                                        class="h-4 w-4"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M12 6v6l4 2"
                                        />
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                        />
                                    </svg>

                                    {{ substr($grupoItem->hora_inicio, 0, 5) }}
                                    -
                                    {{ substr($grupoItem->hora_fin, 0, 5) }}
                                </div>

                            @else

                                <div class="mt-1 text-sm text-amber-600">
                                    Sin horario
                                </div>

                            @endif

                            <div class="mt-2 text-sm font-medium text-slate-700">
                                {{ $grupoItem->estudiantes->count() }}
                                {{ $grupoItem->estudiantes->count() === 1 ? 'estudiante' : 'estudiantes' }}
                            </div>

                        </button>

                        {{-- Botones --}}
<div class="flex shrink-0 items-center gap-1">

    {{-- EDITAR --}}
    <button
        type="button"
        wire:click="editar({{ $grupoItem->id }})"
        title="Editar grupo"
        aria-label="Editar grupo"
        class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:text-indigo-600"
    >

        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            class="h-5 w-5"
        >
            <path
                d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497L21.174 6.812Z"
            />
        </svg>

    </button>


    {{-- ELIMINAR --}}
    <button
        type="button"
        wire:click="solicitarEliminacion({{ $grupoItem->id }})"
        title="Eliminar grupo"
        aria-label="Eliminar grupo"
        class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:text-red-600"
    >

        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            class="h-5 w-5"
        >
            <path
                fill-rule="evenodd"
                d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z"
                clip-rule="evenodd"
            />
        </svg>

    </button>

</div>

                    </div>
                </div>

            @empty

                <div class="px-5 py-8 text-center">
                    <p class="text-sm text-slate-500">
                        Aún no tienes grupos creados.
                    </p>
                </div>

            @endforelse

        </div>
    </div>
</aside>

        </div>

    </div>


    {{-- ========================================================== --}}
    {{-- MODAL CONFIRMAR GUARDADO                                  --}}
    {{-- ========================================================== --}}

    @if ($mostrarConfirmacionGuardar)

        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
            x-data
        >

            <div
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
            >

                <div class="flex items-start gap-4">

                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.8"
                            stroke="currentColor"
                            class="h-6 w-6"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12.75 9 17l10-10"
                            />
                        </svg>
                    </div>

                    <div>

                        <h3 class="text-lg font-bold text-gray-800">
                            Guardar cambios
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-gray-500">
                            ¿Deseas guardar los cambios realizados en este grupo?
                        </p>

                    </div>

                </div>


                <div class="mt-6 flex justify-end gap-3">

                    <button
                        type="button"
                        wire:click="$set('mostrarConfirmacionGuardar', false)"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                    >
                        Seguir editando
                    </button>

                    <button
                        type="button"
                        wire:click="guardarGrupo"
                        wire:loading.attr="disabled"
                        wire:target="guardarGrupo"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >

                        <span wire:loading.remove wire:target="guardarGrupo">
                            Sí, guardar
                        </span>

                        <span wire:loading wire:target="guardarGrupo">
                            Guardando...
                        </span>

                    </button>

                </div>

            </div>

        </div>

    @endif


    {{-- ========================================================== --}}
    {{-- MODAL CONFIRMAR ELIMINACIÓN                               --}}
    {{-- ========================================================== --}}

    @if ($mostrarConfirmacionEliminar)

        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        >

            <div
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
            >

                <div class="flex items-start gap-4">

                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.8"
                            stroke="currentColor"
                            class="h-6 w-6"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.29 3.86 2.82 17.25A2 2 0 0 0 4.56 20.25h14.88a2 2 0 0 0 1.74-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                            />
                        </svg>

                    </div>


                    <div>

                        <h3 class="text-lg font-bold text-gray-800">
                            Eliminar grupo
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-gray-500">

                            ¿Estás seguro de que deseas eliminar el grupo

                            <span class="font-semibold text-gray-700">
                                "{{ $nombreGrupoEliminar }}"
                            </span>?

                        </p>

                        <p class="mt-2 text-xs leading-5 text-red-500">
                            Esta acción eliminará también las asistencias y calificaciones asociadas al grupo.
                            No se eliminarán las cuentas de los estudiantes.
                        </p>

                    </div>

                </div>


                <div class="mt-6 flex justify-end gap-3">

                    <button
                        type="button"
                        wire:click="cancelarEliminacion"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                    >
                        Cancelar
                    </button>


                    <button
                        type="button"
                        wire:click="confirmarEliminacion"
                        wire:loading.attr="disabled"
                        wire:target="confirmarEliminacion"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >

                        <span wire:loading.remove wire:target="confirmarEliminacion">
                            Sí, eliminar
                        </span>

                        <span wire:loading wire:target="confirmarEliminacion">
                            Eliminando...
                        </span>

                    </button>

                </div>

            </div>

        </div>

    @endif

</div>
