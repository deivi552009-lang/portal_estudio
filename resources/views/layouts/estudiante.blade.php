<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Aula Digital' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-slate-50 text-slate-800">
<div class="min-h-screen flex">

    {{-- SIDEBAR --}}
    <aside class="hidden w-64 shrink-0 flex-col bg-slate-900 text-white md:flex">
        <div class="flex h-20 items-center border-b border-slate-800 px-6">
            <div class="mr-3 flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-xl">
                🎓
            </div>
            <div>
                <div class="text-lg font-bold">Aula Digital</div>
                <div class="text-xs text-slate-400">Portal Estudio</div>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6">
            <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                Principal
            </p>

            <a href="{{ route('estudiante.dashboard') }}"
               class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
               {{ request()->routeIs('estudiante.dashboard')
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <span class="w-5 text-center">⌂</span>
                Inicio
            </a>

            <a href="{{ route('estudiante.notas') }}"
               class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
               {{ request()->routeIs('estudiante.notas')
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <span class="w-5 text-center">▣</span>
                Mis Notas
            </a>

            <a href="{{ route('estudiante.talleres') }}"
               class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
               {{ request()->routeIs('estudiante.talleres')
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <span class="w-5 text-center">▤</span>
                Talleres
            </a>

            <a href="#"
               class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white">
                <span class="w-5 text-center">▧</span>
                Guías de Estudio
            </a>

            <a href="#"
               class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white">
                <span class="w-5 text-center">□</span>
                Calendario
            </a>

            <a href="#"
               class="mb-6 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white">
                <span class="w-5 text-center">▱</span>
                Mensajes
            </a>

            <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                Cuenta
            </p>

            <a href="{{ route('profile.edit') }}"
               class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white">
                <span class="w-5 text-center">♙</span>
                Mi Perfil
            </a>
        </nav>

        <div class="border-t border-slate-800 p-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white">
                    <span class="w-5 text-center">⇥</span>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- CONTENIDO --}}
    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-5 backdrop-blur sm:px-8">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">Aula Digital</h1>
                <p class="text-xs text-slate-500">Panel del estudiante</p>
            </div>

            <div class="flex items-center gap-4">
                <button type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                        title="Notificaciones">
                    <span class="text-xl">♧</span>
                    <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">0</span>
                </button>

                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-slate-700">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">Estudiante</p>
                    </div>

                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 font-bold text-blue-700">
                        {{ strtoupper(substr(auth()->user()->name ?? 'E', 0, 1)) }}
                    </div>

                    <span class="text-slate-400">⌄</span>
                </div>
            </div>
        </header>

        <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 bg-white px-4 py-2 md:hidden">
            <a href="{{ route('estudiante.dashboard') }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('estudiante.dashboard') ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Inicio</a>
            <a href="{{ route('estudiante.notas') }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('estudiante.notas') ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Mis Notas</a>
            <a href="{{ route('estudiante.talleres') }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('estudiante.talleres') ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Talleres</a>
        </nav>

        <main class="p-5 sm:p-8">
            {{ $slot ?? '' }}
            @yield('contenido')
        </main>
    </div>
</div>

@livewireScripts
</body>
</html>
