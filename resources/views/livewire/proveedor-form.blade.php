<div class="mx-auto max-w-xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">{{ $this->editando ? 'Editar proveedor' : 'Registrar proveedor' }}</h1>
        <a href="{{ route('proveedores.historial') }}" class="rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
            Ver historial de proveedores
        </a>
    </div>

    @if ($creado)
        <div class="mb-4 flex items-center justify-between rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            <span>{{ $this->editando ? 'Cambios guardados correctamente.' : 'Proveedor registrado correctamente.' }}</span>
            <a href="{{ route('proveedores.historial') }}" class="font-medium underline hover:text-green-900">Ver historial</a>
        </div>
    @endif

    <form wire:submit="guardar" class="flex flex-col gap-4">
        <div>
            <label for="nombre" class="mb-1 block text-sm font-medium text-cosechar-ink">Nombre / Razón social</label>
            <input
                type="text"
                id="nombre"
                wire:model.blur="nombre"
                @disabled(! $this->puedeEditarIdentidad)
                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring disabled:bg-cosechar-cream disabled:text-cosechar-muted"
            >
            @error('nombre')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="ruc" class="mb-1 block text-sm font-medium text-cosechar-ink">RUC</label>
            <input
                type="text"
                id="ruc"
                wire:model.blur="ruc"
                @disabled(! $this->puedeEditarIdentidad)
                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring disabled:bg-cosechar-cream disabled:text-cosechar-muted"
            >
            @unless ($this->puedeEditarIdentidad)
                <p class="mt-1 text-xs text-cosechar-muted">Solo el administrador puede cambiar el nombre y el RUC.</p>
            @endunless
            @error('ruc')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="telefono" class="mb-1 block text-sm font-medium text-cosechar-ink">Teléfono</label>
                <input
                    type="text"
                    id="telefono"
                    wire:model.blur="telefono"
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
                >
                @error('telefono')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="direccion" class="mb-1 block text-sm font-medium text-cosechar-ink">Dirección</label>
                <input
                    type="text"
                    id="direccion"
                    wire:model.blur="direccion"
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
                >
                @error('direccion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="guardar"
                class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="guardar">{{ $this->editando ? 'Guardar cambios' : 'Guardar proveedor' }}</span>
                <span wire:loading wire:target="guardar">Guardando...</span>
            </button>
        </div>
    </form>
</div>
