<div class="mx-auto max-w-5xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Compras</h1>
        <a href="{{ route('compras.nueva') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar compra
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Proveedor</th>
                    <th class="px-4 py-3">Almacén</th>
                    <th class="px-4 py-3">Comprobante</th>
                    <th class="px-4 py-3">Productos</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->compras as $compra)
                    <tr wire:key="compra-{{ $compra->id }}">
                        <td class="px-4 py-3 text-cosechar-muted">{{ $compra->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-medium text-cosechar-ink">{{ $compra->proveedor->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $compra->almacen->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $compra->numero_comprobante ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $compra->detalles->pluck('producto.nombre')->join(', ') }}</td>
                        <td class="px-4 py-3 text-right font-medium text-cosechar-ink">S/ {{ number_format($compra->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-cosechar-muted">No hay compras registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
