<div class="mx-auto max-w-xl p-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-cosechar-ink">{{ $this->editando ? 'Editar producto' : 'Registrar producto' }}</h1>
        <a href="{{ route('productos.historial') }}" class="rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
            Ver historial de productos
        </a>
    </div>

    @if ($creado)
        <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            {{ $this->editando ? 'Producto actualizado correctamente.' : 'Producto registrado correctamente.' }}
        </div>
    @endif

    <x-leyenda-obligatorios />

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
            <label for="codigo" class="mb-1 block text-sm font-medium text-cosechar-ink">Código <x-obligatorio /></label>
            <input
                type="text"
                id="codigo"
                wire:model.blur="codigo" @disabled(! $this->puedeEditarSensibles)
                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
            >
            @error('codigo')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @unless ($this->editando)
                <p class="mt-1 text-xs text-cosechar-muted">Se propone el siguiente código libre; puede cambiarlo.</p>
            @endunless
        </div>

        <div>
            <label for="nombre" class="mb-1 block text-sm font-medium text-cosechar-ink">Nombre <x-obligatorio /></label>
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

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="marca" class="mb-1 block text-sm font-medium text-cosechar-ink">Marca <span class="font-normal text-cosechar-muted">(opcional)</span></label>
                <input
                    type="text"
                    id="marca"
                    wire:model.blur="marca" @disabled(! $this->puedeEditarSensibles)
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring disabled:bg-cosechar-cream"
                >
                @error('marca')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="presentacion" class="mb-1 block text-sm font-medium text-cosechar-ink">Presentación <span class="font-normal text-cosechar-muted">(opcional)</span></label>
                <input
                    type="text"
                    id="presentacion"
                    wire:model.blur="presentacion" @disabled(! $this->puedeEditarSensibles)
                    placeholder="Ej.: frasco de 250 ml"
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring disabled:bg-cosechar-cream"
                >
                @error('presentacion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="categoria_id" class="mb-1 block text-sm font-medium text-cosechar-ink">Categoría <x-obligatorio /></label>
            <select
                id="categoria_id"
                wire:model.blur="categoria_id" @disabled(! $this->puedeEditarSensibles)
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
            <label for="unidad_medida" class="mb-1 block text-sm font-medium text-cosechar-ink">Unidad de medida <x-obligatorio /></label>
            <select
                id="unidad_medida"
                wire:model.blur="unidad_medida" @disabled(! $this->puedeEditarSensibles)
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
                <label for="stock_minimo" class="mb-1 block text-sm font-medium text-cosechar-ink">Stock mínimo <x-obligatorio /></label>
                <input
                    type="number"
                    id="stock_minimo"
                    wire:model.blur="stock_minimo" @disabled(! $this->puedeEditarSensibles)
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
                >
                @error('stock_minimo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="precio" class="mb-1 block text-sm font-medium text-cosechar-ink">Precio (S/) <x-obligatorio /></label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    id="precio"
                    wire:model.blur="precio" @disabled(! $this->puedeEditarSensibles)
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
                >
                @error('precio')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="rounded-xl border border-cosechar-border bg-white p-4">
            <p class="mb-1 text-sm font-medium text-cosechar-ink">Precio por mayor <span class="font-normal text-cosechar-muted">(opcional)</span></p>
            <p class="mb-3 text-xs text-cosechar-muted">En Ventas se propone este precio cuando se lleva la cantidad mínima o más, por ejemplo desde 12 unidades.</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="precio_mayor" class="mb-1 block text-xs font-medium text-cosechar-muted">Precio por mayor (S/)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="precio_mayor"
                        wire:model.blur="precio_mayor" @disabled(! $this->puedeEditarSensibles)
                        class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring disabled:bg-cosechar-cream"
                    >
                    @error('precio_mayor')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="cantidad_mayor" class="mb-1 block text-xs font-medium text-cosechar-muted">Desde cuántas unidades</label>
                    <input
                        type="number"
                        min="2"
                        id="cantidad_mayor"
                        wire:model.blur="cantidad_mayor" @disabled(! $this->puedeEditarSensibles)
                        class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring disabled:bg-cosechar-cream"
                    >
                    @error('cantidad_mayor')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-cosechar-ink">
            <input type="checkbox" wire:model="maneja_vencimiento" @disabled(! $this->puedeEditarSensibles || $this->venceBloqueado) class="rounded border border-cosechar-border">
            Maneja fecha de vencimiento
            @if ($this->venceBloqueado)
                <span class="text-xs text-cosechar-muted">(no se puede cambiar: el producto ya tiene lotes)</span>
            @endif
        </label>

        @if ($this->editando && ! $this->puedeEditarSensibles)
            <p class="text-xs text-cosechar-muted">Solo el administrador puede cambiar el código, la marca, la presentación, la categoría, la unidad, el stock mínimo, los precios y el vencimiento.</p>
        @endif

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">{{ $this->editando ? 'Guardar cambios' : 'Guardar producto' }}</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
