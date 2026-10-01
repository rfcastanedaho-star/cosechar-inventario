<div class="mx-auto max-w-xl p-8">
    <h1 class="mb-6 text-lg font-semibold text-cosechar-ink">Registrar almacén</h1>

    @if ($creado)
        <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            Almacén registrado correctamente.
        </div>
    @endif

    <form wire:submit="guardar" class="flex flex-col gap-4">
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

        <div>
            <label for="encargado" class="mb-1 block text-sm font-medium text-cosechar-ink">Encargado</label>
            <input
                type="text"
                id="encargado"
                wire:model.blur="encargado"
                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
            >
            @error('encargado')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">Guardar almacén</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
