<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="bg-gray-50">
        @auth
            <header class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3">
                <div class="flex items-center gap-6">
                    <span class="text-sm font-semibold text-gray-900">COSECHAR Inventario</span>
                    <nav class="flex items-center gap-4 text-sm">
                        <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900 {{ request()->routeIs('dashboard') ? 'font-semibold text-gray-900' : '' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('productos.index') }}" class="text-gray-600 hover:text-gray-900 {{ request()->routeIs('productos.*') ? 'font-semibold text-gray-900' : '' }}">
                            Productos
                        </a>
                        <a href="{{ route('movimientos.nuevo') }}" class="text-gray-600 hover:text-gray-900 {{ request()->routeIs('movimientos.*') ? 'font-semibold text-gray-900' : '' }}">
                            Movimientos
                        </a>
                        <a href="{{ route('reportes') }}" class="text-gray-600 hover:text-gray-900 {{ request()->routeIs('reportes') ? 'font-semibold text-gray-900' : '' }}">
                            Reportes
                        </a>
                    </nav>
                </div>
                <div class="flex items-center gap-3 text-sm text-gray-600">
                    <span>{{ auth()->user()->name }}</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs uppercase text-gray-500">
                        {{ auth()->user()->rol }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-600 underline hover:text-gray-900">
                            Salir
                        </button>
                    </form>
                </div>
            </header>
        @endauth

        {{ $slot }}

        @livewireScripts
    </body>
</html>
