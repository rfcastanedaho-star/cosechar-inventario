<div class="mx-auto max-w-5xl p-8" x-data>
    <input
        type="file"
        class="hidden"
        x-ref="archivoFoto"
        wire:model="fotoNueva"
        accept="image/jpeg,image/png,image/webp"
    >

    @error('fotoNueva')
        <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <div wire:loading wire:target="fotoNueva" class="mb-4 rounded-md bg-cosechar-olive/10 px-4 py-2 text-sm text-cosechar-olive">
        Subiendo foto...
    </div>

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Productos</h1>
        <a href="{{ route('productos.nuevo') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar producto
        </a>
    </div>

    <div class="mb-4 flex items-center gap-3">
        <div class="flex w-full max-w-md items-center gap-2 rounded-full border border-cosechar-border bg-white px-4 py-2 shadow-sm focus-within:border-cosechar-olive">
            <x-icon name="search" class="h-4 w-4 shrink-0 text-cosechar-muted" />
            <input
                type="search"
                wire:model.live.debounce.300ms="buscar"
                placeholder="Buscar producto por nombre o código..."
                class="w-full border-0 bg-transparent p-0 text-sm text-cosechar-ink placeholder:text-cosechar-muted focus:ring-0"
            >
        </div>

        @if (trim($buscar) !== '')
            <p class="text-sm text-cosechar-muted">
                {{ $this->productos->count() }} resultado(s) ·
                <button type="button" wire:click="$set('buscar', '')" class="font-medium text-cosechar-olive underline">Quitar búsqueda</button>
            </p>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Foto</th>
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
                        <td class="px-4 py-3">
                            <div class="flex w-16 flex-col items-center gap-1">
                                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg border border-cosechar-border bg-cosechar-cream text-cosechar-muted">
                                    @if ($producto->imagen_version)
                                        <img
                                            src="{{ route('productos.imagen', $producto) }}?v={{ strtotime($producto->imagen_version) }}"
                                            alt="{{ $producto->nombre }}"
                                            loading="lazy"
                                            class="h-full w-full object-cover"
                                        >
                                    @else
                                        <x-icon name="productos" class="h-5 w-5" />
                                    @endif
                                </div>
                                <button
                                    type="button"
                                    @click="$wire.productoFotoId = {{ $producto->id }}; $refs.archivoFoto.click()"
                                    class="text-[11px] font-medium text-cosechar-olive underline hover:text-cosechar-forest"
                                >
                                    {{ $producto->imagen_version ? 'Cambiar' : 'Agregar' }}
                                </button>
                            </div>
                        </td>
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
                        <td colspan="7" class="px-4 py-6 text-center text-cosechar-muted">{{ trim($buscar) !== '' ? 'No se encontraron productos con esa búsqueda.' : 'No hay productos registrados.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
