<div class="mx-auto max-w-xl p-8">
    <h1 class="mb-6 text-lg font-semibold text-cosechar-ink">Registrar producto</h1>

    @if ($creado)
        <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            Producto registrado correctamente.
        </div>
    @endif

    <form wire:submit="guardar" class="flex flex-col gap-4">
        <div>
            <label for="imagen" class="mb-1 block text-sm font-medium text-cosechar-ink">Foto del producto <span class="font-normal text-cosechar-muted">(opcional)</span></label>
            <div class="flex items-center gap-4">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-cosechar-border bg-white text-cosechar-muted">
                    @if ($imagen && ! $errors->has('imagen') && $imagen->isPreviewable())
                        <img src="{{ $imagen->temporaryUrl() }}" alt="Vista previa" class="h-full w-full object-cover">
                    @else
                        <x-icon name="productos" class="h-8 w-8" />
                    @endif
                </div>
                <div class="min-w-0">
                    <input
                        type="file"
                        id="imagen"
                        wire:model="imagen"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-cosechar-muted file:mr-3 file:rounded-md file:border file:border-cosechar-border file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-cosechar-ink hover:file:bg-cosechar-cream"
                    >
                    <p class="mt-1 text-xs text-cosechar-muted">JPG, PNG o WebP, hasta 4 MB. Se ajusta sola para que pese poco.</p>
                    <p wire:loading wire:target="imagen" class="mt-1 text-xs text-cosechar-olive">Subiendo foto...</p>
                    @error('imagen')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div>
            <label for="codigo" class="mb-1 block text-sm font-medium text-cosechar-ink">Código</label>
            <input
                type="text"
                id="codigo"
                wire:model.live="codigo"
                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
            >
            @error('codigo')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="nombre" class="mb-1 block text-sm font-medium text-cosechar-ink">Nombre</label>
            <input
                type="text"
                id="nombre"
                wire:model.blur="nombre"
                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
            >
            @error('nombre')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="categoria_id" class="mb-1 block text-sm font-medium text-cosechar-ink">Categoría</label>
            <select
                id="categoria_id"
                wire:model.blur="categoria_id"
                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
            >
                <option value="">Seleccione una categoría</option>
                @foreach ($this->categoriasAgrupadas as $categoria)
                    @if ($categoria->subcategorias->isNotEmpty())
                        <optgroup label="{{ $categoria->nombre }}">
                            @foreach ($categoria->subcategorias as $subcategoria)
                                <option value="{{ $subcategoria->id }}">{{ $subcategoria->nombre }}</option>
                            @endforeach
                        </optgroup>
                    @else
                        <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                    @endif
                @endforeach
            </select>
            @error('categoria_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="unidad_medida" class="mb-1 block text-sm font-medium text-cosechar-ink">Unidad de medida</label>
            <select
                id="unidad_medida"
                wire:model.blur="unidad_medida"
                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
            >
                <option value="">Seleccione una unidad</option>
                <option value="saco">Saco</option>
                <option value="kg">Kilogramo</option>
                <option value="litro">Litro</option>
                <option value="galon">Galón</option>
                <option value="unidad">Unidad</option>
            </select>
            @error('unidad_medida')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="stock_minimo" class="mb-1 block text-sm font-medium text-cosechar-ink">Stock mínimo</label>
                <input
                    type="number"
                    id="stock_minimo"
                    wire:model.blur="stock_minimo"
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
                >
                @error('stock_minimo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="precio" class="mb-1 block text-sm font-medium text-cosechar-ink">Precio (S/)</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    id="precio"
                    wire:model.blur="precio"
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
                >
                @error('precio')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-cosechar-ink">
            <input type="checkbox" wire:model="maneja_vencimiento" class="rounded border border-cosechar-border">
            Maneja fecha de vencimiento
        </label>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">Guardar producto</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
