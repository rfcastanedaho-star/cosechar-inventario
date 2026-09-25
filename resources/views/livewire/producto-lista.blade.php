<div class="mx-auto max-w-5xl p-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-900">Productos</h1>
        <a href="{{ route('productos.nuevo') }}" class="rounded-md bg-black px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
            Registrar producto
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2">Código</th>
                    <th class="px-4 py-2">Producto</th>
                    <th class="px-4 py-2">Categoría</th>
                    <th class="px-4 py-2">Stock actual</th>
                    <th class="px-4 py-2">Stock mínimo</th>
                    @can('editar-stock-minimo')
                        <th class="px-4 py-2"></th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->productos as $producto)
                    <tr wire:key="producto-{{ $producto->id }}">
                        <td class="px-4 py-2">{{ $producto->codigo }}</td>
                        <td class="px-4 py-2">{{ $producto->nombre }}</td>
                        <td class="px-4 py-2">{{ $producto->categoria->nombre }}</td>
                        <td class="px-4 py-2 {{ ($producto->stock_actual ?? 0) < $producto->stock_minimo ? 'font-semibold text-red-600' : '' }}">
                            {{ $producto->stock_actual ?? 0 }}
                        </td>
                        @can('editar-stock-minimo')
                            <td class="px-4 py-2">
                                <input
                                    type="number"
                                    min="0"
                                    wire:model="stockMinimoEdit.{{ $producto->id }}"
                                    class="w-20 rounded-md border border-gray-300 px-2 py-1 text-sm"
                                >
                                @error('stockMinimoEdit.'.$producto->id)
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="px-4 py-2">
                                <button
                                    type="button"
                                    wire:click="guardarStockMinimo({{ $producto->id }})"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                >
                                    Guardar
                                </button>
                            </td>
                        @else
                            <td class="px-4 py-2">{{ $producto->stock_minimo }}</td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">No hay productos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
