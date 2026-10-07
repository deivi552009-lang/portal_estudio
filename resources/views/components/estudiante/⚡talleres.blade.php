<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-slate-900">Talleres</h2>
        <p class="mt-1 text-sm text-slate-500">
            Consulta tus talleres y actividades académicas.
        </p>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-bold text-slate-900">Mis talleres</h3>
                <p class="mt-1 text-xs text-slate-500">Actividades publicadas por tus docentes.</p>
            </div>

            <div class="flex rounded-lg bg-slate-100 p-1 text-sm">
                @foreach ([
                    'pendientes' => 'Pendientes',
                    'entregados' => 'Entregados',
                    'todos' => 'Todos',
                ] as $valor => $texto)
                    <button type="button"
                            wire:click="$set('filtro', '{{ $valor }}')"
                            class="rounded-md px-4 py-2 font-medium transition
                            {{ $filtro === $valor
                                ? 'bg-white text-blue-600 shadow-sm'
                                : 'text-slate-500 hover:text-slate-700' }}">
                        {{ $texto }}
                    </button>
                @endforeach
            </div>
        </div>

        @php
            $lista = $actividades;

            if ($filtro === 'pendientes') {
                $lista = $actividades;
            }

            if ($filtro === 'entregados') {
                $lista = collect();
            }
        @endphp

        @if ($lista->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                    ▤
                </div>
                <h4 class="mt-4 font-semibold text-slate-700">
                    {{ $filtro === 'entregados' ? 'No tienes talleres entregados' : 'No tienes talleres pendientes' }}
                </h4>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-400">
                    En la siguiente etapa conectaremos este apartado con el sistema real de entregas.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Taller</th>
                            <th class="px-6 py-4">Asignatura</th>
                            <th class="px-6 py-4">Entrega</th>
                            <th class="px-6 py-4">Estado</th>
                            <th class="px-6 py-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($lista as $actividad)
                            @php
                                $fecha = $actividad->fecha_limite
                                    ? \Carbon\Carbon::parse($actividad->fecha_limite->format('Y-m-d') . ' ' . ($actividad->hora_limite ?? '23:59'))
                                    : null;
                                $vencida = $fecha?->isPast() ?? false;
                            @endphp

                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                            ▤
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800">{{ $actividad->titulo }}</p>
                                            @if ($actividad->descripcion)
                                                <p class="mt-0.5 max-w-xs truncate text-xs text-slate-400">{{ $actividad->descripcion }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $actividad->materia_nombre }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $fecha ? $fecha->format('d M Y') : 'Sin fecha' }}
                                    @if ($fecha)
                                        <div class="text-xs text-slate-400">{{ $fecha->format('H:i') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                        {{ $vencida
                                            ? 'bg-slate-100 text-slate-500'
                                            : 'bg-amber-50 text-amber-600' }}">
                                        {{ $vencida ? 'Vencida' : 'Pendiente' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Ver detalle del taller (funcionalidad pendiente) --}}
                                        <button type="button"
                                                title="Ver taller"
                                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                                            <svg class="h-4 w-4"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24"
                                                 aria-hidden="true">
                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="1.8"
                                                      d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="1.8"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </button>

                                        {{-- Responder el taller (funcionalidad pendiente) --}}
                                        <button type="button"
                                                title="Responder taller"
                                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 transition hover:bg-blue-100">
                                            <svg class="h-4 w-4"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24"
                                                 aria-hidden="true">
                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="1.8"
                                                      d="M16.862 3.487a2.1 2.1 0 013 2.975L8.5 17.824 4 19l1.176-4.5L16.862 3.487z"/>
                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="1.8"
                                                      d="M14 5l5 5"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
