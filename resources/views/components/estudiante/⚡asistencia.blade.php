<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-slate-900">Mi Asistencia</h2>
        <p class="mt-1 text-sm text-slate-500">
            Consulta tus registros de asistencia por asignatura.
        </p>
    </div>

    @if ($grupos->isEmpty())

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                    ▦
                </div>
                <h4 class="mt-4 font-semibold text-slate-700">No hay materias registradas</h4>
                <p class="mt-1 text-sm text-slate-400">
                    Cuando tengas una inscripción académica aparecerá aquí.
                </p>
            </div>
        </section>

    @else

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="font-bold text-slate-900">Asignatura</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Selecciona la materia para ver su asistencia.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <label for="selector-grupo-asistencia" class="text-sm font-medium text-slate-600">
                        Materia:
                    </label>

                    <select
                        id="selector-grupo-asistencia"
                        wire:change="seleccionarGrupo($event.target.value)"
                        wire:loading.attr="disabled"
                        wire:target="seleccionarGrupo"
                        class="max-w-md rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    >
                        @foreach ($grupos as $grupoOpcion)
                            <option
                                value="{{ $grupoOpcion->id }}"
                                @selected($grupoOpcion->id === $grupoSeleccionado->id)
                            >
                                {{ $grupoOpcion->materia->nombre }}
                                — Semestre {{ $grupoOpcion->semestre }}
                                — {{ $grupoOpcion->anio }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Presente</p>
                <p class="mt-1 text-2xl font-bold text-emerald-800">{{ $resumen['presente'] }}</p>
            </div>

            <div class="rounded-2xl border border-red-100 bg-red-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Ausente</p>
                <p class="mt-1 text-2xl font-bold text-red-800">{{ $resumen['ausente'] }}</p>
            </div>

            <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Excusa</p>
                <p class="mt-1 text-2xl font-bold text-sky-800">{{ $resumen['excusa'] }}</p>
            </div>

            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Tarde</p>
                <p class="mt-1 text-2xl font-bold text-amber-800">{{ $resumen['tarde'] }}</p>
            </div>

            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Asistencia</p>
                <p class="mt-1 text-2xl font-bold text-blue-800">
                    {{ $porcentaje !== null ? $porcentaje . '%' : '—' }}
                </p>
                <p class="mt-1 text-xs text-blue-700">
                    {{ $porcentaje !== null ? 'Presente + tarde' : 'Sin registros' }}
                </p>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h3 class="font-bold text-slate-900">Detalle por fecha</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $grupoSeleccionado->materia->nombre }}
                        — {{ $registros->count() }}
                        {{ $registros->count() === 1 ? 'registro' : 'registros' }}
                    </p>
                </div>
            </div>

            @if ($registros->isEmpty())

                <div class="px-6 py-12 text-center">
                    <h4 class="font-semibold text-slate-700">Sin registros de asistencia</h4>
                    <p class="mt-1 text-sm text-slate-400">
                        Todavía no tienes asistencias registradas en esta materia.
                    </p>
                </div>

            @else

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Fecha</th>
                                <th class="px-6 py-4">Estado</th>
                                <th class="px-6 py-4">Observación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($registros as $registro)
                                @php
                                    $estado = $registro->presente;

                                    $clasesEstado = match ($estado) {
                                        'presente' => 'bg-emerald-50 text-emerald-700',
                                        'ausente' => 'bg-red-50 text-red-700',
                                        'excusa' => 'bg-sky-50 text-sky-700',
                                        'tarde' => 'bg-amber-50 text-amber-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };

                                    $etiquetaEstado = match ($estado) {
                                        'presente' => 'Presente',
                                        'ausente' => 'Ausente',
                                        'excusa' => 'Excusa',
                                        'tarde' => 'Tarde',
                                        default => ucfirst((string) $estado),
                                    };
                                @endphp

                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 text-sm font-medium text-slate-700">
                                        {{ $registro->fecha->format('d/m/Y') }}
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="rounded-lg px-2.5 py-1 text-xs font-semibold {{ $clasesEstado }}">
                                            {{ $etiquetaEstado }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ $registro->observacion ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            @endif
        </section>

    @endif

</div>
