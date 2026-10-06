<div class="mx-auto max-w-5xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Productos</h1>
        <a href="{{ route('productos.nuevo') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar producto
        </a>
    </div>

    @if (trim($buscar) !== '')
        <p class="mb-4 text-sm text-cosechar-muted">
            {{ $this->productos->count() }} resultado(s) para «{{ $buscar }}» ·
            <a href="{{ route('productos.index') }}" class="font-medium text-cosechar-olive underline">Quitar búsqueda</a>
        </p>
    @endif

    <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3">Stock actual</th>
                    <th class="px-4 py-3">Stock mínimo</th>
                    @can('editar-stock-minimo')
                        <th class="px-4 py-3"></th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->productos as $producto)
                    <tr wire:key="producto-{{ $producto->id }}">
                        <td class="px-4 py-3 text-cosechar-muted">{{ $producto->codigo }}</td>
                        <td class="px-4 py-3 font-medium text-cosechar-ink">{{ $producto->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $producto->categoria->nombre }}</td>
                        <td class="px-4 py-3 {{ ($producto->stock_actual ?? 0) < $producto->stock_minimo ? 'font-semibold text-cosechar-danger' : 'text-cosechar-ink' }}">
                            {{ $producto->stock_actual ?? 0 }}
                        </td>
                        @can('editar-stock-minimo')
                            <td class="px-4 py-3">
                                <input
                                    type="number"
                                    min="0"
                                    wire:model="stockMinimoEdit.{{ $producto->id }}"
                                    class="w-20 rounded-md border border-cosechar-border px-2 py-1 text-sm focus:border-cosechar-olive focus:ring"
                                >
                                @error('stockMinimoEdit.'.$producto->id)
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    type="button"
                                    wire:click="guardarStockMinimo({{ $producto->id }})"
                                    class="rounded-md border border-cosechar-border px-3 py-1 text-xs text-cosechar-ink hover:bg-cosechar-cream"
                                >
                                    Guardar
                                </button>
                            </td>
                        @else
                            <td class="px-4 py-3 text-cosechar-muted">{{ $producto->stock_minimo }}</td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-cosechar-muted">No hay productos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
