<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Aula Digital - Panel Docente</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-50 text-slate-800">

    <div class="min-h-screen flex">

        {{-- =========================================================
             SIDEBAR
        ========================================================== --}}
        <aside class="w-64 bg-slate-900 text-white flex flex-col min-h-screen">

            {{-- Logo --}}
            <div class="h-20 flex items-center px-6 border-b border-slate-800">

                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center mr-3">
                    🎓
                </div>

                <div>
                    <div class="font-bold text-lg">
                        Aula Digital
                    </div>

                    <div class="text-xs text-slate-400">
                        Panel Docente
                    </div>
                </div>

            </div>


            {{-- Navegación --}}
            <nav class="flex-1 px-4 py-6">

                <p class="px-3 mb-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Principal
                </p>

                {{-- Dashboard --}}
                <a href="{{ route('docente.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          {{ request()->routeIs('docente.dashboard')
                              ? 'bg-blue-600 text-white'
                              : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}
                          transition">

                    <span class="w-5 text-center">⌂</span>

                    <span class="text-sm font-medium">
                        Dashboard
                    </span>
                </a>


                {{-- Estudiantes --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">♙</span>

                    <span class="text-sm font-medium">
                        Estudiantes
                    </span>
                </a>


                {{-- Asignaturas --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">▣</span>

                    <span class="text-sm font-medium">
                        Asignaturas
                    </span>
                </a>
                {{-- Actividades --}}
                <a
    href="{{ route('docente.actividades') }}"
    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white"
>
    <svg
        class="h-5 w-5"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
    >
        <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.8"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 006 0M9 5h6m-6 7h6m-6 4h4"
        />
    </svg>

    Actividades
</a>


                {{-- Talleres --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">▤</span>

                    <span class="text-sm font-medium">
                        Talleres
                    </span>
                </a>


                {{-- Guías --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">▧</span>

                    <span class="text-sm font-medium">
                        Guías de Estudio
                    </span>
                </a>


                {{-- Calendario --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">□</span>

                    <span class="text-sm font-medium">
                        Calendario
                    </span>
                </a>


                {{-- Mensajes --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-6 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">▱</span>

                    <span class="text-sm font-medium">
                        Mensajes
                    </span>
                </a>


                <p class="px-3 mb-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Gestión académica
                </p>


                {{-- Notas --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">✎</span>

                    <span class="text-sm font-medium">
                        Notas
                    </span>
                </a>


                {{-- Reportes --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">▥</span>

                    <span class="text-sm font-medium">
                        Reportes
                    </span>
                </a>


                <p class="px-3 mt-6 mb-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    Sistema
                </p>


                {{-- Configuración --}}
                <a href="#"
                   class="flex items-center gap-3 px-3 py-2.5 mb-1 rounded-xl
                          text-slate-300 hover:bg-slate-800 hover:text-white transition">

                    <span class="w-5 text-center">⚙</span>

                    <span class="text-sm font-medium">
                        Configuración
                    </span>
                </a>

            </nav>


            {{-- Cerrar sesión --}}
            <div class="p-4 border-t border-slate-800">

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl
                               text-slate-300 hover:bg-slate-800 hover:text-white transition">

                        <span class="w-5 text-center">
                            ⇥
                        </span>

                        <span class="text-sm font-medium">
                            Cerrar sesión
                        </span>

                    </button>
                </form>

            </div>

        </aside>


        {{-- =========================================================
             CONTENIDO PRINCIPAL
        ========================================================== --}}
        <div class="flex-1 min-w-0">

            {{-- Barra superior --}}
            <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-8">

                <div>
                    <h2 class="text-lg font-semibold text-slate-800">
                        Panel Docente
                    </h2>

                    <p class="text-xs text-slate-500">
                        Gestión académica
                    </p>
                </div>


                {{-- Usuario --}}
                <div class="flex items-center gap-3">

                    <div class="text-right">
                        <p class="text-sm font-semibold text-slate-700">
                            {{ auth()->user()->name ?? 'Docente' }}
                        </p>

                        <p class="text-xs text-slate-500">
                            Docente
                        </p>
                    </div>

                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700
                                flex items-center justify-center font-bold">

                        {{ strtoupper(substr(auth()->user()->name ?? 'D', 0, 1)) }}

                    </div>

                </div>

            </header>


            {{-- Página --}}
            <main class="p-6">

                {{ $slot ?? '' }}

                @yield('contenido')

            </main>

        </div>

    </div>

</body>

</html>
