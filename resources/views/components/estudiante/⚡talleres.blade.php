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
                                <td class="px-6 py-4 text-right">
                                    <button type="button"
                                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                        Ver taller
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
