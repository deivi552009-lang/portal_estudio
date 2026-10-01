<div class="max-w-7xl mx-auto p-6 bg-slate-50 min-h-screen text-slate-800 font-sans">

    @php
        // Garantizar que las fechas estén ordenadas cronológicamente de izquierda a derecha (más antigua -> más reciente)
        $fechasOrdenadas = collect($historial['fechas'])->sort()->values()->toArray();
    @endphp

    <!-- Encabezado Principal y Navegación -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/60">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    Historial: {{ $grupo->materia->nombre }}
                </h1>
                <span class="px-2.5 py-0.5 text-xs font-semibold bg-blue-50 text-blue-600 rounded-full border border-blue-100">
                    Semestre {{ $grupo->semestre }} - {{ $grupo->anio }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Docente: <span class="font-medium text-slate-700">{{ $grupo->docente->user->name }}</span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('docente.grupo.asistencia', $grupo->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium text-sm rounded-xl transition-all shadow-sm">
                ← Volver a Asistencia
            </a>
        </div>
    </div>

    <!-- Leyenda Informativa -->
    <div class="mb-6 bg-white p-4 rounded-2xl border border-slate-200/60 shadow-sm flex flex-wrap items-center justify-between gap-4 text-xs">
        <span class="font-semibold text-slate-500 uppercase tracking-wider">Leyenda:</span>
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 font-medium rounded-lg border border-emerald-100">
                <strong class="font-bold">P</strong> Presente
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-rose-50 text-rose-700 font-medium rounded-lg border border-rose-100">
                <strong class="font-bold">A</strong> Ausente
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 font-medium rounded-lg border border-amber-100">
                <strong class="font-bold">T</strong> Tarde
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-700 font-medium rounded-lg border border-slate-200">
                <strong class="font-bold">E</strong> Excusa
            </span>
        </div>
    </div>

    <!-- Tabla de Historial -->
    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs text-slate-500 uppercase tracking-wider">

                        {{-- Estudiante (Fijo a la izquierda) --}}
                        <th class="sticky left-0 z-20 bg-slate-50/95 backdrop-blur-sm border-r border-slate-200/80 px-6 py-4 font-semibold min-w-[220px]">
                            Estudiantes
                        </th>

                        {{-- Fechas en Rotación Vertical (Ordenadas de izquierda a derecha) --}}
                        @foreach ($fechasOrdenadas as $fecha)
                            <th class="border-r border-slate-100 px-1 py-3 bg-slate-50/80 align-bottom min-w-[42px] max-w-[42px]" style="height: 120px;">
                                <div class="flex items-end justify-center h-full pb-2">
                                    <span class="text-xs font-semibold text-slate-600"
                                          style="writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap;">
                                        {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                                    </span>
                                </div>
                            </th>
                        @endforeach

                        {{-- Nota Final / Resumen (Fijo a la derecha) --}}
                        <th class="sticky right-0 z-20 bg-slate-100/80 backdrop-blur-sm border-l border-slate-200/80 px-4 py-4 text-center font-bold text-slate-700 min-w-[100px]">
                            Nota Final
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 text-sm">
                    @foreach ($historial['estudiantes'] as $estudiante)
                        <tr class="hover:bg-slate-50/50 transition-colors">

                            {{-- Nombre de Estudiante --}}
                            <td class="sticky left-0 z-10 bg-white border-r border-slate-200/60 px-6 py-3 font-medium text-slate-800 whitespace-nowrap">
                                {{ $estudiante['nombre'] }}
                            </td>

                            {{-- Registros de Asistencia por Fecha --}}
                            @foreach ($fechasOrdenadas as $fecha)
                                @php
                                    $registro = $estudiante['asistencias'][$fecha] ?? null;
                                    $estado = $registro['estado'] ?? null;

                                    $letra = match ($estado) {
                                        'presente' => 'P',
                                        'ausente' => 'A',
                                        'excusa' => 'E',
                                        'tarde' => 'T',
                                        default => '—',
                                    };

                                    $clase = match ($estado) {
                                        'presente' => 'bg-emerald-50 text-emerald-700 font-bold',
                                        'ausente' => 'bg-rose-50 text-rose-700 font-bold',
                                        'tarde' => 'bg-amber-50 text-amber-700 font-bold',
                                        'excusa' => 'bg-slate-100 text-slate-700 font-semibold',
                                        default => 'text-slate-300 font-normal',
                                    };
                                @endphp

                                <td class="border-r border-slate-100 p-1 text-center min-w-[42px]">
                                    <div class="w-8 h-8 mx-auto flex items-center justify-center rounded-lg text-xs {{ $clase }}"
                                         title="{{ $registro['observacion'] ?? '' }}">
                                        {{ $letra }}
                                    </div>
                                </td>
                            @endforeach

                            {{-- Nota Final --}}
                            <td class="sticky right-0 z-10 bg-slate-50/30 border-l border-slate-200/80 px-4 py-3 text-center font-bold text-slate-700">
                                @if ($estudiante['nota_final'] !== null)
                                    {{ number_format($estudiante['nota_final'], 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
