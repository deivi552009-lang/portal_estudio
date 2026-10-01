<div class="max-w-7xl mx-auto">

    {{-- =========================================================
         BIENVENIDA
    ========================================================== --}}
    <div class="mb-8">

        <h1 class="text-3xl font-bold text-slate-900">
            ¡Hola, {{ auth()->user()->name ?? 'Docente' }}!
        </h1>

        <p class="mt-1 text-slate-500">
            Bienvenido a tu panel docente.
        </p>

    </div>


    {{-- =========================================================
         TARJETAS
    ========================================================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

        {{-- Estudiantes --}}
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm p-5">

            <div class="flex items-center justify-between">

                <div>
                    <p class="text-sm text-slate-500">
                        Estudiantes
                    </p>

                    <p class="mt-2 text-3xl font-bold text-slate-900">
                        {{ $totalEstudiantes }}
                    </p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600
                            flex items-center justify-center text-xl">
                    ♙
                </div>

            </div>

            <p class="mt-3 text-xs text-slate-400">
                Estudiantes asignados a tus grupos
            </p>

        </div>


        {{-- Grupos --}}
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm p-5">

            <div class="flex items-center justify-between">

                <div>
                    <p class="text-sm text-slate-500">
                        Grupos
                    </p>

                    <p class="mt-2 text-3xl font-bold text-slate-900">
                        {{ $grupos->count() }}
                    </p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-violet-50 text-violet-600
                            flex items-center justify-center text-xl">
                    ▣
                </div>

            </div>

            <p class="mt-3 text-xs text-slate-400">
                Grupos asignados actualmente
            </p>

        </div>


        {{-- Talleres --}}
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm p-5">

            <div class="flex items-center justify-between">

                <div>
                    <p class="text-sm text-slate-500">
                        Talleres
                    </p>

                    <p class="mt-2 text-3xl font-bold text-slate-900">
                        {{ $totalTalleres }}
                    </p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600
                            flex items-center justify-center text-xl">
                    ▤
                </div>

            </div>

            <p class="mt-3 text-xs text-slate-400">
                 Talleres publicados
            </p>

        </div>


        {{-- Guías --}}
        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm p-5">

            <div class="flex items-center justify-between">

                <div>
                    <p class="text-sm text-slate-500">
                        Guías publicadas
                    </p>

                    <p class="mt-2 text-3xl font-bold text-slate-900">
                        0
                    </p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600
                            flex items-center justify-center text-xl">
                    ▧
                </div>

            </div>

            <p class="mt-3 text-xs text-slate-400">
                Próximamente
            </p>

        </div>

    </div>


    {{-- =========================================================
         MIS GRUPOS
    ========================================================== --}}
    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm">

        <div class="px-6 py-5 border-b border-slate-100">

            <h2 class="text-lg font-bold text-slate-800">
                Mis grupos
            </h2>

            <p class="text-sm text-slate-500 mt-1">
                Accede rápidamente a las herramientas de cada grupo.
            </p>

        </div>


        @if ($grupos->isEmpty())

            <div class="px-6 py-12 text-center">

                <div class="text-4xl mb-3">
                    📚
                </div>

                <p class="font-medium text-slate-700">
                    No tienes grupos asignados.
                </p>

                <p class="text-sm text-slate-500 mt-1">
                    Cuando se te asignen grupos aparecerán aquí.
                </p>

            </div>

        @else

            <div class="divide-y divide-slate-100">

                @foreach ($grupos as $grupo)

                    <div class="px-6 py-5 flex flex-col lg:flex-row
                                lg:items-center justify-between gap-4">

                        <div>

                            <h3 class="font-semibold text-slate-800">
                                {{ $grupo->materia->nombre }}
                            </h3>

                            <p class="text-sm text-slate-500 mt-1">

                                {{ $grupo->semestre }} -
                                {{ $grupo->anio }}

                                <span class="mx-1">•</span>

                                {{ $grupo->estudiantes->count() }}
                                estudiantes

                            </p>

                        </div>


                        <div class="flex flex-wrap gap-2">

                            <a
                                href="{{ route('docente.grupo.notas', $grupo->id) }}"
                                class="px-3 py-2 rounded-lg bg-blue-50
                                       text-blue-700 text-sm font-medium
                                       hover:bg-blue-100 transition">
                                📝 Notas
                            </a>

                            <a
                                href="{{ route('docente.grupo.asistencia', $grupo->id) }}"
                                class="px-3 py-2 rounded-lg bg-emerald-50
                                       text-emerald-700 text-sm font-medium
                                       hover:bg-emerald-100 transition">
                                ✓ Asistencia
                            </a>

                            <a
                                href="{{ route('docente.grupo.asistencia.historial', $grupo->id) }}"
                                class="px-3 py-2 rounded-lg bg-violet-50
                                       text-violet-700 text-sm font-medium
                                       hover:bg-violet-100 transition">
                                ▦ Historial
                            </a>

                        </div>

                    </div>

                @endforeach

            </div>

        @endif

    </div>


    {{-- =========================================================
         ACCIONES RÁPIDAS
    ========================================================== --}}
    <div class="mt-8">

        <h2 class="text-lg font-bold text-slate-800 mb-4">
            Acciones rápidas
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <a href="#"
               class="bg-white border border-slate-200/70 rounded-xl p-4
                      hover:shadow-md transition">

                <div class="text-blue-600 text-xl mb-2">
                    📝
                </div>

                <p class="font-semibold text-slate-800">
                    Registrar notas
                </p>

                <p class="text-xs text-slate-500 mt-1">
                    Accede a las notas de tus grupos.
                </p>

            </a>


            <a href="#"
               class="bg-white border border-slate-200/70 rounded-xl p-4
                      hover:shadow-md transition">

                <div class="text-emerald-600 text-xl mb-2">
                    ✓
                </div>

                <p class="font-semibold text-slate-800">
                    Tomar asistencia
                </p>

                <p class="text-xs text-slate-500 mt-1">
                    Registra la asistencia de tus estudiantes.
                </p>

            </a>


            <a href="#"
               class="bg-white border border-slate-200/70 rounded-xl p-4
                      hover:shadow-md transition">

                <div class="text-violet-600 text-xl mb-2">
                    ▤
                </div>

                <p class="font-semibold text-slate-800">
                    Nuevo taller
                </p>

                <p class="text-xs text-slate-500 mt-1">
                    Próximamente.
                </p>

            </a>


            <a href="#"
               class="bg-white border border-slate-200/70 rounded-xl p-4
                      hover:shadow-md transition">

                <div class="text-amber-600 text-xl mb-2">
                    ▧
                </div>

                <p class="font-semibold text-slate-800">
                    Subir guía
                </p>

                <p class="text-xs text-slate-500 mt-1">
                    Próximamente.
                </p>

            </a>

        </div>

    </div>

</div>
