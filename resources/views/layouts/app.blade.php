<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="bg-cosechar-cream">
        @auth
            <div class="flex min-h-screen">
                <x-sidebar />

                <div class="flex min-w-0 flex-1 flex-col">
                    <x-topbar />

                    <main class="flex-1">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        @else
            {{ $slot }}
        @endauth

        @livewireScripts
    </body>
</html>
