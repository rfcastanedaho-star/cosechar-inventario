@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring disabled:bg-cosechar-cream';
@endphp

<div class="mx-auto max-w-xl p-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-cosechar-ink">{{ $this->editando ? 'Editar cliente' : 'Registrar cliente' }}</h1>
        <a href="{{ route('clientes.index') }}" class="rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
            Volver a clientes
        </a>
    </div>

    <x-leyenda-obligatorios />

    <form wire:submit="guardar" class="flex flex-col gap-4">
        <div>
            <label for="nombre" class="mb-1 block text-sm font-medium text-cosechar-ink">Nombre o razón social <x-obligatorio /></label>
            <input type="text" id="nombre" wire:model.blur="nombre" @disabled(! $this->puedeEditarIdentidad) class="{{ $campo }}">
            @error('nombre')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="documento" class="mb-1 block text-sm font-medium text-cosechar-ink">DNI o RUC <span class="font-normal text-cosechar-muted">(opcional)</span></label>
            <input type="text" id="documento" wire:model.blur="documento" @disabled(! $this->puedeEditarIdentidad) autocomplete="off" class="{{ $campo }}">
            @error('documento')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="telefono" class="mb-1 block text-sm font-medium text-cosechar-ink">Teléfono <span class="font-normal text-cosechar-muted">(opcional)</span></label>
                <input type="text" id="telefono" wire:model.blur="telefono" class="{{ $campo }}">
                @error('telefono')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="direccion" class="mb-1 block text-sm font-medium text-cosechar-ink">Dirección <span class="font-normal text-cosechar-muted">(opcional)</span></label>
                <input type="text" id="direccion" wire:model.blur="direccion" class="{{ $campo }}">
                @error('direccion')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        @if ($this->editando && ! $this->puedeEditarIdentidad)
            <p class="text-xs text-cosechar-muted">Solo el administrador puede cambiar el nombre y el documento.</p>
        @endif

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">{{ $this->editando ? 'Guardar cambios' : 'Guardar cliente' }}</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
