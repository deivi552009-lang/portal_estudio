<div class="min-h-screen bg-slate-50">

    <div class="mx-auto max-w-7xl px-6 py-6">

        {{-- ENCABEZADO --}}
        <div class="mb-6">

            <div>
                <p class="text-sm font-medium text-emerald-600">
                    Gestión académica
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-800">
                    Actividades
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Crea y administra talleres, tareas y actividades para tus estudiantes.
                </p>
            </div>

        </div>


        {{-- MENSAJE --}}
        @if (session('mensaje'))

            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('mensaje') }}
            </div>

        @endif


        <div class="grid gap-6 lg:grid-cols-3">

            {{-- ================================================= --}}
            {{-- NUEVA ACTIVIDAD --}}
            {{-- ================================================= --}}

            <div class="lg:col-span-1">

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                    <div class="flex items-start justify-between gap-3">

                        <div>
                            <h2 class="text-lg font-bold text-slate-800">
                                {{ $actividadEditandoId ? 'Editar actividad' : 'Nueva actividad' }}
                            </h2>

                            <p class="mt-1 text-sm text-slate-500">
                                {{ $actividadEditandoId
                                    ? 'Modifica la información de la actividad.'
                                    : 'Publica una actividad para tus estudiantes.' }}
                            </p>
                        </div>

                    </div>


                    <form
                        wire:submit="{{ $actividadEditandoId ? 'actualizarActividad' : 'crearActividad' }}"
                        class="mt-6 space-y-5"
                    >

                        {{-- GRUPO / MATERIA --}}
                        <div>

                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Materia / grupo
                            </label>

                            <select
                                wire:model="grupoSeleccionadoId"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            >

                                <option value="">
                                    Seleccionar materia...
                                </option>

                                @foreach ($grupos as $grupo)

                                    <option value="{{ $grupo->id }}">
                                        {{ $grupo->materia->nombre }}
                                        · Sem. {{ $grupo->semestre }} - {{ $grupo->anio }}
                                    </option>

                                @endforeach

                            </select>

                            @error('grupoSeleccionadoId')
                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- TIPO --}}
                        <div>

                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Tipo de actividad
                            </label>

                            <input
                                type="text"
                                wire:model="tipo"
                                placeholder="Ej. Taller, Quiz, Proyecto..."
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            >

                            @error('tipo')
                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- TÍTULO --}}
                        <div>

                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Título
                            </label>

                            <input
                                type="text"
                                wire:model="titulo"
                                placeholder="Ej. Taller de formularios HTML"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            >

                            @error('titulo')
                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- DESCRIPCIÓN --}}
                        <div>

                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Descripción / indicaciones
                            </label>

                            <textarea
                                wire:model="descripcion"
                                rows="4"
                                placeholder="Escribe las instrucciones para los estudiantes..."
                                class="w-full resize-none rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            ></textarea>

                            @error('descripcion')
                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- FECHA Y HORA --}}
                        <div class="grid grid-cols-2 gap-3">

                            <div>

                                <label class="mb-2 block text-sm font-medium text-slate-700">
                                    Fecha límite
                                </label>

                                <input
                                    type="date"
                                    wire:model="fechaLimite"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                >

                                @error('fechaLimite')
                                    <p class="mt-1 text-xs text-red-500">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>


                            <div>

                                <label class="mb-2 block text-sm font-medium text-slate-700">
                                    Hora límite
                                </label>

                                <input
                                    type="time"
                                    wire:model="horaLimite"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                >

                                @error('horaLimite')
                                    <p class="mt-1 text-xs text-red-500">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                        </div>


                        {{-- ARCHIVO --}}
                        <div>

                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Archivo adjunto
                            </label>

                            <input
                                type="file"
                                wire:model="archivo"

                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600"
                                >
                                <div wire:loading wire:target="archivo" class="mt-2 text-sm text-blue-600">
    Subiendo archivo...
</div>

@if ($archivo)
    <p class="mt-2 text-sm text-emerald-600">
        Archivo seleccionado:
        {{ $archivo->getClientOriginalName() }}
    </p>
@endif

@error('archivo')
    <p class="mt-1 text-xs text-red-500">
        {{ $message }}
    </p>
@enderror

                            <p class="mt-1 text-xs text-slate-400">
                                PDF, Word, Excel, PowerPoint o ZIP · máximo 10 MB.
                            </p>

                            @error('archivo')
                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>


                        {{-- BOTONES --}}
                        <div class="flex gap-2">

                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                            >
                                <span wire:loading.remove>
                                    {{ $actividadEditandoId
                                        ? 'Guardar cambios'
                                        : 'Publicar actividad' }}
                                </span>

                                <span wire:loading>
                                    Guardando...
                                </span>
                            </button>


                            @if ($actividadEditandoId)

                                <button
                                    type="button"
                                    wire:click="limpiarFormulario"
                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                                >
                                    Cancelar
                                </button>

                            @endif

                        </div>

                    </form>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- ACTIVIDADES PUBLICADAS --}}
            {{-- ================================================= --}}

            <div class="lg:col-span-2">

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

                    <div class="border-b border-slate-200 px-6 py-5">

                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
{{-- Filtro de actividades por materia --}}
    <div>
        <h2 class="text-lg font-bold text-slate-800">
            Actividades publicadas
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Consulta y administra las actividades publicadas para tus grupos.
        </p>
    </div>

    <div class="w-full md:w-64">

        <select
            wire:model.live="filtroGrupoId"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
        >

            <option value="">
                Todos los grupos
            </option>

            @foreach ($grupos as $grupo)

                <option value="{{ $grupo->id }}">
                    {{ $grupo->materia->nombre }}
                    · Sem. {{ $grupo->semestre }} - {{ $grupo->anio }}
                </option>

            @endforeach

        </select>

    </div>

</div>

                    </div>

                    {{-- AVISO DE VENCIMIENTO EN ACTIVIDADES --}}
                    @if ($actividadesProximas->isNotEmpty())

    <div class="border-b border-amber-200 bg-amber-50 px-6 py-4">

        <div class="flex items-start gap-3">

            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                ⚠️
            </div>

            <div class="min-w-0">

                <p class="font-semibold text-amber-800">
                    Actividades próximas a vencer
                </p>

                <p class="mt-1 text-sm text-amber-700">
                    Tienes
                    {{ $actividadesProximas->count() }}
                    {{ $actividadesProximas->count() === 1 ? 'actividad' : 'actividades' }}
                    con fecha límite dentro de los próximos 3 días.
                </p>

                <div class="mt-3 space-y-2">

                    @foreach ($actividadesProximas as $actividad)

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">

                            <span class="font-semibold text-amber-900">
                                {{ $actividad->titulo }}
                            </span>

                            <span class="text-amber-700">
                                {{ $actividad->materia_nombre }}
                            </span>

                            <span class="font-medium text-red-600">
                                Vence:
                                {{ $actividad->fecha_limite->format('d/m/Y') }}
                                {{ \Carbon\Carbon::parse($actividad->hora_limite)->format('H:i') }}
                            </span>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>

    </div>

@endif
                    @if ($actividades->isEmpty())

    <div class="px-6 py-12 text-center">

        <p class="font-medium text-slate-600">
            No hay actividades publicadas.
        </p>

        <p class="mt-1 text-sm text-slate-400">
            Las actividades que publiques aparecerán aquí.
        </p>

    </div>

@else

    <div class="divide-y divide-slate-100">

        @foreach ($actividades as $actividad)

            <div class="px-6 py-5">

                <div class="flex items-start justify-between gap-4">

                    <div class="min-w-0 flex-1">

                        <div class="flex flex-wrap items-center gap-2">

                            <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold uppercase text-emerald-600">
                                {{ $actividad->tipo }}
                            </span>

                            <h3 class="font-semibold text-slate-800">
                                {{ $actividad->titulo }}
                            </h3>

                        </div>


                        <p class="mt-1 text-xs font-medium text-slate-400">
                            {{ $actividad->materia_nombre }}
                            · Sem. {{ $actividad->semestre_grupo }}
                            - {{ $actividad->anio_grupo }}
                        </p>


                        @if ($actividad->descripcion)

                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                {{ $actividad->descripcion }}
                            </p>

                        @endif


                        <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-500">

                            <span>
                                📅
                                {{ $actividad->fecha_limite->format('d/m/Y') }}
                            </span>

                            <span>
                                🕐
                                {{ \Carbon\Carbon::parse($actividad->hora_limite)->format('H:i') }}
                            </span>

{{-- MOSTRAR EL ARCHIVO ADJUNTO --}}
@if ($actividad->archivo)

    <div class="mt-4 flex flex-wrap items-center gap-2">

        {{-- VER ARCHIVO --}}
        <a
            href="{{ route('docente.actividad.archivo', $actividad) }}"
            target="_blank"
            title="Ver archivo"
            class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-100"
        >

            <svg
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.8"
                    d="M15 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V7l-5-5z"
                />

                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.8"
                    d="M14 2v6h6"
                />
            </svg>

            Ver archivo

        </a>


        {{-- DESCARGAR ARCHIVO --}}
        <a
            href="{{ asset('storage/' . $actividad->archivo) }}"
            download
            title="Descargar archivo"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-2 text-slate-600 transition hover:bg-slate-100"
        >

            <svg
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.8"
                    d="M12 3v12"
                />

                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.8"
                    d="M7 10l5 5 5-5"
                />

                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.8"
                    d="M5 21h14"
                />

            </svg>

        </a>

    </div>

@endif

                        </div>

                    </div>


                    {{-- ACCIONES --}}
                    <div class="flex shrink-0 items-center gap-1.5">

                        <button
                            type="button"
                            wire:click="editar({{ $actividad->id }})"
                            title="Editar actividad"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M16.862 3.487a2.1 2.1 0 013 2.975L8.5 17.824 4 19l1.176-4.5L16.862 3.487z"
                                />
                            </svg>
                        </button>


                        <button
                            type="button"
                            wire:click="solicitarEliminacion({{ $actividad->id }})"
                            title="Eliminar actividad"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-500 transition hover:bg-red-100"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"
                                />
                            </svg>
                        </button>

                    </div>

                </div>

            </div>

        @endforeach

    </div>

@endif

                </div>

            </div>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- MODAL CONFIRMACIÓN ELIMINAR --}}
    {{-- ========================================================= --}}

    @if ($mostrarConfirmacionEliminar)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">

            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">

                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-500">

                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M12 9v4m0 4h.01M5.5 19h13a1.5 1.5 0 001.3-2.25L13.3 5.5a1.5 1.5 0 00-2.6 0l-6.5 11.25A1.5 1.5 0 005.5 19z"
                        />
                    </svg>

                </div>


                <h3 class="mt-4 text-lg font-bold text-slate-800">
                    ¿Eliminar actividad?
                </h3>

                <p class="mt-2 text-sm leading-6 text-slate-500">
                    La actividad y su archivo adjunto serán eliminados.
                    Esta acción no se puede deshacer.
                </p>


                <div class="mt-6 flex justify-end gap-3">

                    <button
                        type="button"
                        wire:click="cancelarEliminacion"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        wire:click="eliminarActividad"
                        class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        Sí, eliminar
                    </button>

                </div>

            </div>

        </div>

    @endif

</div>
