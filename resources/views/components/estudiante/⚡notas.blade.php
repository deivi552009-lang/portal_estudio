<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-slate-900">Mis Notas</h2>
        <p class="mt-1 text-sm text-slate-500">
            Consulta tus evaluaciones y promedio por asignatura.
        </p>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
            <div>
                <h3 class="font-bold text-slate-900">Resumen académico</h3>
                <p class="mt-1 text-xs text-slate-500">Tus calificaciones registradas actualmente.</p>
            </div>
            <span class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-600">
                {{ $materias->count() }} asignaturas
            </span>
        </div>

        @if ($materias->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                    ▣
                </div>
                <h4 class="mt-4 font-semibold text-slate-700">No hay materias registradas</h4>
                <p class="mt-1 text-sm text-slate-400">
                    Cuando tengas una inscripción académica aparecerá aquí.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Asignatura</th>
                            <th class="px-6 py-4">Evaluaciones</th>
                            <th class="px-6 py-4 text-center">Promedio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($materias as $materia)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                            ▣
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800">{{ $materia['materia'] }}</p>
                                            <p class="text-xs text-slate-400">Calificaciones registradas</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-5">
                                    @if ($materia['evaluaciones']->isEmpty())
                                        <span class="text-sm text-slate-400">Sin evaluaciones</span>
                                    @else
                                        <div class="flex flex-nowrap gap-2 overflow-x-auto pb-1">
                                            @foreach ($materia['evaluaciones'] as $index => $evaluacion)
                                                @php
                                                    $nombreEvaluacion = $evaluacion['nombre'] ?: 'Nota ' . ($index + 1);
                                                    $nota = $evaluacion['nota'] !== null ? (float) $evaluacion['nota'] : null;
                                                    $claseNota = $nota === null
                                                        ? 'border-slate-200 bg-slate-50 text-slate-400'
                                                        : ($nota >= 4
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                            : ($nota >= 3
                                                                ? 'border-amber-200 bg-amber-50 text-amber-700'
                                                                : 'border-red-200 bg-red-50 text-red-700'));
                                                @endphp
                                                <div
                                                    class="flex h-14 w-16 shrink-0 flex-col items-center justify-center rounded-lg border px-1 text-center {{ $claseNota }}"
                                                    title="{{ $nombreEvaluacion }}{{ $nota !== null ? ': ' . number_format($nota, 1) : '' }}"
                                                    aria-label="{{ $nombreEvaluacion }}"
                                                >
                                                    <div class="w-full truncate text-[10px] font-semibold uppercase leading-tight">
                                                        {{ $nombreEvaluacion }}
                                                    </div>
                                                    <div class="mt-0.5 text-sm font-bold leading-tight">
                                                        {{ $nota !== null ? number_format($nota, 1) : '—' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-5 text-center">
                                    @if ($materia['promedio'] !== null)
                                        @php
                                            $promedio = (float) $materia['promedio'];
                                            $clasePromedio = $promedio >= 4
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : ($promedio >= 3
                                                    ? 'bg-amber-50 text-amber-700'
                                                    : 'bg-red-50 text-red-700');
                                        @endphp
                                        <span class="inline-flex min-w-14 justify-center rounded-lg px-3 py-2 text-sm font-bold {{ $clasePromedio }}">
                                            {{ number_format($promedio, 1) }}
                                        </span>
                                    @else
                                        <span class="text-sm text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
