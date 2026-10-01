<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Portal Estudio' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen bg-slate-100 text-slate-800">

    <div class="min-h-screen">

        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">

                <div>
                    <h1 class="text-xl font-bold text-slate-800">
                        Portal Estudio
                    </h1>

                    <p class="text-sm text-slate-500">
                        Panel del estudiante
                    </p>
                </div>

                <div class="flex items-center gap-4">

                    <span class="text-sm text-slate-600">
                        {{ auth()->user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button
                            type="submit"
                            class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        >
                            Cerrar sesión
                        </button>
                    </form>

                </div>

            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-6">
            {{ $slot }}
        </main>

    </div>

    @livewireScripts

</body>

</html>
