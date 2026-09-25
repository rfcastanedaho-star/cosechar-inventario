<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lote {{ $lote->numero_lote }} — COSECHAR Inventario</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-gray-50 p-6">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wide text-green-800">Cosechar Inventario</p>
            <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ $lote->producto->nombre }}</h1>
            <p class="text-sm text-gray-500">Código: {{ $lote->producto->codigo }}</p>

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Lote</dt>
                    <dd class="font-medium text-gray-900">{{ $lote->numero_lote }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Cantidad disponible</dt>
                    <dd class="font-medium text-gray-900">{{ $lote->cantidad }} {{ $lote->producto->unidad_medida }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Fecha de ingreso</dt>
                    <dd class="font-medium text-gray-900">{{ $lote->fecha_ingreso->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Fecha de vencimiento</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}
                    </dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-gray-500">Categoría</dt>
                    <dd class="font-medium text-gray-900">{{ $lote->producto->categoria->nombre }}</dd>
                </div>
            </dl>
        </div>
    </body>
</html>
