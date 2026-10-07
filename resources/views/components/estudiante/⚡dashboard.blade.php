<div wire:poll.30s class="mx-auto max-w-7xl space-y-6">

    @php
        $nombre = trim($usuario->name ?? 'Estudiante');
        $primerNombre = explode(' ', $nombre)[0];

        $proximaActividad = $actividades->first();
        $talleresPendientes = $actividades->count();
    @endphp

    {{-- =========================================================
         BIENVENIDA
    ========================================================== --}}
    <section>
        <h2 class="text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">
            ¡Hola, {{ $primerNombre }}!
        </h2>

        <p class="mt-1 text-sm text-slate-500 sm:text-base">
            Bienvenido a tu aula virtual
        </p>
    </section>

    {{-- =========================================================
         TARJETAS RESUMEN
    ========================================================== --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Promedio --}}
        <div class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-full border-4 border-blue-200 bg-white text-blue-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M4 5h16v14H4zM8 9h8M8 13h5" />
                    </svg>
                </div>
            </div>

            <p class="mt-4 text-sm font-medium text-slate-600">Promedio General</p>

            @if ($promedioGeneral !== null)
                <p class="mt-1 text-3xl font-bold text-slate-800">
                    {{ number_format($promedioGeneral, 1) }}
                </p>

                <div class="mt-2 flex gap-1 text-lg" aria-label="{{ number_format($promedioGeneral, 1) }} de 5 estrellas">
                    @for ($i = 1; $i <= 5; $i++)
                        @php
                            $progresoEstrella = max(0, min(1, (float) $promedioGeneral - ($i - 1)));
                        @endphp
                        <span class="relative inline-block h-5 w-5" aria-hidden="true">
                            <span class="absolute inset-0 text-slate-300">★</span>
                            <span class="absolute inset-y-0 left-0 overflow-hidden text-amber-400" style="width: {{ round($progresoEstrella * 100, 2) }}%">
                                <span class="inline-block w-5">★</span>
                            </span>
                        </span>
                    @endfor
                </div>

                <p class="mt-1 text-xs text-slate-400">
                    Promedio de tus asignaturas con calificación.
                </p>
            @else
                <p class="mt-1 text-3xl font-bold text-slate-800">—</p>

                <div class="mt-2 flex gap-1 text-lg text-slate-300" aria-hidden="true">
                    <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                </div>

                <p class="mt-1 text-xs text-slate-400">
                    Aún no tienes calificaciones registradas.
                </p>
            @endif
        </div>

        {{-- Talleres --}}
        <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm">
            <div class="flex h-11 w-11 items-center justify-center rounded-full border-4 border-emerald-200 bg-white text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 5h6m-6 4h6m-6 4h4" />
                </svg>
            </div>

            <p class="mt-4 text-sm font-medium text-slate-600">Talleres Pendientes</p>
            <p class="mt-1 text-3xl font-bold text-slate-800">{{ $talleresPendientes }}</p>

            <a href="#talleres-pendientes"
               class="mt-2 inline-block text-sm font-semibold text-emerald-600 hover:text-emerald-700">
                Ver todos
            </a>
        </div>

        {{-- Guías --}}
        <div class="rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm">
            <div class="flex h-11 w-11 items-center justify-center rounded-full border-4 border-violet-200 bg-white text-violet-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 0v5h5M8 12h8M8 16h6" />
                </svg>
            </div>

            <p class="mt-4 text-sm font-medium text-slate-600">Guías Disponibles</p>
            <p class="mt-1 text-3xl font-bold text-slate-800">—</p>

            <span class="mt-2 inline-block text-sm font-semibold text-violet-600">
                Próximamente
            </span>
        </div>

        {{-- Próxima entrega --}}
        <div class="rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm">
            <div class="flex h-11 w-11 items-center justify-center rounded-full border-4 border-amber-200 bg-white text-amber-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="4" y="5" width="16" height="15" rx="2" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-width="1.8" d="M8 3v4m8-4v4M4 10h16" />
                </svg>
            </div>

            <p class="mt-4 text-sm font-medium text-slate-600">Próxima Entrega</p>

            @if ($proximaActividad)
                @php
                    $fechaProxima = \Carbon\Carbon::parse(
                        $proximaActividad->fecha_limite->format('Y-m-d') . ' ' . $proximaActividad->hora_limite
                    );
                @endphp

                <p class="mt-1 text-xl font-bold text-slate-800">
                    {{ $fechaProxima->format('d M Y') }}
                </p>

                <p class="mt-1 truncate text-xs text-slate-500">
                    {{ $proximaActividad->titulo }}
                </p>
            @else
                <p class="mt-1 text-xl font-bold text-slate-800">Sin entregas</p>
                <p class="mt-1 text-xs text-slate-500">No tienes actividades registradas.</p>
            @endif
        </div>
    </section>

    {{-- =========================================================
         CONTENIDO PRINCIPAL
    ========================================================== --}}
    <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">

        {{-- Mis notas --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-bold text-slate-800">Mis Notas</h3>
                <a href="{{ route('estudiante.notas') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                    Ver todas
                </a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($grupos->take(5) as $grupo)
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                      d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" />
                            </svg>
                        </div>

                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-slate-700">
                            {{ $grupo->materia->nombre }}
                        </p>

                        @if ($grupo->nota_final !== null)
                            <span class="rounded-lg px-3 py-1.5 text-sm font-bold {{ $grupo->nota_final < 3.0 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }}">
                                {{ number_format($grupo->nota_final, 1) }}
                            </span>
                        @else
                            <span class="rounded-lg bg-slate-50 px-3 py-1.5 text-sm font-bold text-slate-400">
                                —
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-500">
                        Aún no tienes materias inscritas.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Talleres pendientes --}}
        <div id="talleres-pendientes" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-bold text-slate-800">Talleres Pendientes</h3>
                <a href="#talleres-pendientes" class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                    Ver todos
                </a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($actividades->take(3) as $actividad)
                    @php
                        $fechaLimite = \Carbon\Carbon::parse(
                            $actividad->fecha_limite->format('Y-m-d') . ' ' . $actividad->hora_limite
                        );

                        $vencida = $fechaLimite->isPast();
                    @endphp

                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                      d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 5h6m-6 4h6m-6 4h4" />
                            </svg>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-700">
                                {{ $actividad->titulo }}
                            </p>

                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                {{ $actividad->materia_nombre }}
                                · Entrega: {{ $fechaLimite->format('d M Y') }}
                            </p>
                        </div>

                        <span class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold
                            {{ $vencida
                                ? 'bg-slate-100 text-slate-500'
                                : 'bg-amber-50 text-amber-600' }}">
                            {{ $vencida ? 'Vencida' : 'Pendiente' }}
                        </span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-500">
                        No tienes talleres pendientes.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- =========================================================
         BLOQUE INFERIOR
    ========================================================== --}}
    <section class="overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50 via-white to-blue-50">
        <div class="flex flex-col items-center gap-5 px-6 py-6 sm:flex-row sm:px-8">
            <div class="flex h-28 w-28 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm">
                <svg class="h-20 w-20 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 100 100" aria-hidden="true">
                    <rect x="18" y="42" width="64" height="36" rx="5" stroke="currentColor" stroke-width="5"/>
                    <path d="M27 72h46" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                    <circle cx="50" cy="55" r="5" fill="currentColor"/>
                    <path d="M32 25c5-9 17-13 27-8 7 3 12 10 12 18" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                    <path d="M29 31c-5 5-6 12-3 18" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                </svg>
            </div>

            <div class="flex-1 text-center sm:text-left">
                <h3 class="text-lg font-bold text-slate-800">
                    ¡Sigue aprendiendo!
                </h3>

                <p class="mt-1 max-w-xl text-sm text-slate-500">
                    Revisa tus actividades y mantente al día con tus entregas.
                </p>
            </div>

            <a href="#talleres-pendientes"
               class="inline-flex shrink-0 items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                Ver actividades
            </a>
        </div>
    </section>

</div>
