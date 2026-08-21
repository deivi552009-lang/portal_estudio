<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Portal Estudio</title>

</head>

<body>

    <header>

        <h2>Portal Estudio</h2>

        <hr>

    </header>

    <main>

        @yield('contenido')

        {{ $slot ?? '' }}

    </main>

    <footer>

        <hr>

        <p>© 2026 Portal Estudio</p>

    </footer>

</body>

</html>
