<div
    class="space-y-8"
    wire:poll.30s
>

    {{-- ENCABEZADO --}}
    <div>
        <h2 class="text-2xl font-bold text-slate-800">
            Bienvenido, {{ $usuario->name }}
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Aquí tienes un resumen de tu actividad académica.
        </p>
    </div>


{{-- ========================================================= --}}
{{-- TALLERES Y ACTIVIDADES POR VENCER --}}
{{-- ========================================================= --}}

<section class="mb-8">

    <div class="mb-4">
        <h2 class="text-xl font-bold text-slate-800">
            Talleres y actividades por vencer
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Actividades pendientes de tus materias inscritas.
        </p>
    </div>

    @if ($actividades->isEmpty())

        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                <svg
                    class="h-6 w-6 text-slate-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6l5 5v11a2 2 0 01-2 2z"
                    />
                </svg>
            </div>

            <p class="font-medium text-slate-700">
                No tienes actividades pendientes.
            </p>

            <p class="mt-1 text-sm text-slate-400">
                Cuando un docente publique una actividad aparecerá aquí.
            </p>
        </div>

    @else

        <div class="space-y-3">

            @foreach ($actividades as $actividad)

                @php
                    $fechaLimite = \Carbon\Carbon::parse(
                        $actividad->fecha_limite->format('Y-m-d') . ' ' . $actividad->hora_limite
                    );

                    $ahora = now();

                    $vencida = $fechaLimite->isPast();

                    $porVencer = !$vencida &&
                        $ahora->diffInHours($fechaLimite, false) <= 24;

                    $tipo = mb_strtolower($actividad->tipo, 'UTF-8');
                @endphp

                <div
                    class="rounded-2xl border bg-white p-5 shadow-sm transition hover:shadow-md
                    {{ $porVencer
                        ? 'border-red-200'
                        : 'border-slate-200' }}"
                >

                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

                        {{-- Información principal --}}
                        <div class="flex min-w-0 items-start gap-4">

                            {{-- Icono --}}
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                                {{ $porVencer
                                    ? 'bg-red-50 text-red-500'
                                    : 'bg-emerald-50 text-emerald-600' }}"
                            >
                                @if ($tipo === 'taller')

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
                                            d="M12 6v12m6-6H6m13 9H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2z"
                                        />
                                    </svg>

                                @else

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
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6l5 5v11a2 2 0 01-2 2z"
                                        />
                                    </svg>

                                @endif
                            </div>

                            {{-- Título y descripción --}}
                            <div class="min-w-0">

                                <h3 class="truncate font-semibold text-slate-800">
                                    {{ $actividad->titulo }}
                                </h3>

                                @if ($actividad->descripcion)

                                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">
                                        {{ $actividad->descripcion }}
                                    </p>

                                @endif

                                <p class="mt-2 text-sm font-medium text-slate-600">
                                    {{ $actividad->materia_nombre }}
                                </p>

                                <p class="mt-0.5 text-xs text-slate-400">
                                    Docente: {{ $actividad->docente_nombre }}
                                </p>

                            </div>

                        </div>


                        {{-- Fecha y estado --}}
                        <div class="flex shrink-0 flex-col gap-2 md:items-end">

                            <div class="text-sm text-slate-500">
                                <span class="font-medium text-slate-600">
                                    Vence:
                                </span>

                                {{ $fechaLimite->format('d/m/Y') }}
                                a las
                                {{ $fechaLimite->format('H:i') }}
                            </div>

                            @if ($vencida)

                                <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">
                                    VENCIDA
                                </span>

                            @elseif ($porVencer)

                                <span class="inline-flex w-fit rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-600">
                                    POR VENCER
                                </span>

                            @else

                                <span class="inline-flex w-fit rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600">
                                    PENDIENTE
                                </span>

                            @endif

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

    @endif

</section>


    {{-- MIS MATERIAS --}}
    <section>

        <div class="mb-4">
            <h3 class="text-lg font-bold text-slate-800">
                Mis materias inscritas
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                Consulta el estado y horario de las materias en las que estás inscrito.
            </p>
        </div>


        @if ($grupos->isEmpty())

            <div class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">

                <p class="text-sm text-slate-500">
                    Actualmente no estás inscrito en ninguna materia.
                </p>

            </div>

        @else

            <div class="space-y-4">

                @foreach ($grupos as $grupo)

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">

                            {{-- INFORMACIÓN DE LA MATERIA --}}
                            <div class="min-w-0 flex-1">

                                <div class="flex flex-wrap items-center gap-3">

                                    <h4 class="text-lg font-bold text-slate-800">
                                        {{ $grupo->materia->nombre }}
                                    </h4>


                                    @if ($grupo->estado_clase === 'en_curso')

                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">

                                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>

                                            EN CURSO

                                        </span>

                                    @elseif ($grupo->estado_clase === 'proxima')

                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">

                                            <span class="h-2 w-2 rounded-full bg-blue-500"></span>

                                            PRÓXIMA

                                        </span>

                                    @elseif ($grupo->estado_clase === 'finalizada')

                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">

                                            <span class="h-2 w-2 rounded-full bg-slate-400"></span>

                                            FINALIZADA

                                        </span>

                                    @else

                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">
                                            SIN HORARIO
                                        </span>

                                    @endif

                                </div>


                                <div class="mt-3 space-y-1 text-sm text-slate-600">

                                    <p>
                                        <span class="font-medium text-slate-700">
                                            Docente:
                                        </span>

                                        {{ $grupo->docente->user->name }}
                                    </p>


                                    @if ($grupo->hora_inicio && $grupo->hora_fin)

                                        <p>
                                            <span class="font-medium text-slate-700">
                                                Horario:
                                            </span>

                                            {{ \Carbon\Carbon::parse($grupo->hora_inicio)->format('g:i A') }}
                                            -
                                            {{ \Carbon\Carbon::parse($grupo->hora_fin)->format('g:i A') }}
                                        </p>

                                    @endif


                                    <p>
                                        <span class="font-medium text-slate-700">
                                            Semestre:
                                        </span>

                                        {{ $grupo->semestre }} - {{ $grupo->anio }}
                                    </p>

                                </div>


                                {{-- PROGRESO SOLO SI ESTÁ EN CURSO --}}
                                @if ($grupo->estado_clase === 'en_curso')

                                    <div class="mt-5 max-w-2xl">

                                        <div class="mb-2 flex items-center justify-between">

                                            <span class="text-sm font-medium text-slate-700">
                                                Progreso de la clase
                                            </span>

                                            <span class="text-sm font-bold text-emerald-600">
                                                {{ $grupo->progreso_clase }}%
                                            </span>

                                        </div>


                                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">

                                            <div
                                                class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                                style="width: {{ $grupo->progreso_clase }}%"
                                            ></div>

                                        </div>

                                    </div>

                                @endif

                            </div>


                            {{-- BOTÓN --}}
                            <div class="shrink-0">

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                >
                                    Ver más

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>

                                </button>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        @endif

    </section>

</div>
