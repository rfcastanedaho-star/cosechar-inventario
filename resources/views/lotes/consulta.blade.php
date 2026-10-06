<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lote {{ $lote->numero_lote }} — COSECHAR Inventario</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Lora:wght@500;600;700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-cosechar-cream p-6">
        <div class="w-full max-w-sm rounded-2xl border border-cosechar-border bg-white p-6 shadow-lg">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/icono-cosechar-badge.png') }}" alt="" class="h-10 w-10 rounded-full object-cover">
                <p class="text-xs font-semibold uppercase tracking-wide text-cosechar-olive">Cosechar Inventario</p>
            </div>
            <h1 class="mt-4 font-serif text-xl font-semibold text-cosechar-ink">{{ $lote->producto->nombre }}</h1>
            <p class="text-sm text-cosechar-muted">Código: {{ $lote->producto->codigo }}</p>

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-cosechar-muted">Lote</dt>
                    <dd class="font-medium text-cosechar-ink">{{ $lote->numero_lote }}</dd>
                </div>
                <div>
                    <dt class="text-cosechar-muted">Cantidad disponible</dt>
                    <dd class="font-medium text-cosechar-ink">{{ $lote->cantidad }} {{ $lote->producto->unidad_medida }}</dd>
                </div>
                <div>
                    <dt class="text-cosechar-muted">Fecha de ingreso</dt>
                    <dd class="font-medium text-cosechar-ink">{{ $lote->fecha_ingreso->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-cosechar-muted">Fecha de vencimiento</dt>
                    <dd class="font-medium text-cosechar-ink">
                        {{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-cosechar-muted">Categoría</dt>
                    <dd class="font-medium text-cosechar-ink">{{ $lote->producto->categoria->nombre }}</dd>
                </div>
                <div>
                    <dt class="text-cosechar-muted">Almacén</dt>
                    <dd class="font-medium text-cosechar-ink">{{ $lote->almacen?->nombre ?? 'Sin asignar' }}</dd>
                </div>
            </dl>
        </div>
    </body>
</html>
