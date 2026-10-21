<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>@yield('titulo') · COSECHAR</title>
        <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Lora:wght@500;600;700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-cosechar-cream">
        <main class="flex min-h-screen items-center justify-center px-6">
            <div class="w-full max-w-md text-center">
                <img src="{{ asset('images/icono-cosechar-badge.png') }}" alt="" class="mx-auto mb-6 h-16 w-16 rounded-full object-cover">
                <p class="font-sans text-sm font-semibold tracking-widest text-cosechar-olive">COSECHAR · ERROR @yield('codigo')</p>
                <h1 class="mt-2 font-serif text-3xl font-semibold text-cosechar-ink">@yield('titulo')</h1>
                <p class="mt-3 text-sm text-cosechar-muted">@yield('mensaje')</p>
                <a href="{{ url('/') }}" class="mt-8 inline-block rounded-md bg-cosechar-forest px-5 py-2.5 text-sm font-medium text-white hover:bg-cosechar-forest/90">
                    Volver al inicio
                </a>
            </div>
        </main>
    </body>
</html>