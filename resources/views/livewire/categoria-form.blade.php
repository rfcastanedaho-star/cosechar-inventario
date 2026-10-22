@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring disabled:bg-cosechar-cream';
@endphp

<div class="mx-auto max-w-xl p-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-cosechar-ink">{{ $this->editando ? 'Renombrar categoría' : 'Registrar categoría' }}</h1>
        <a href="{{ route('categorias.index') }}" class="rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
            Volver a categorías
        </a>
    </div>

    <x-leyenda-obligatorios />

    <form wire:submit="guardar" class="flex flex-col gap-4">
        <div>
            <label for="nombre" class="mb-1 block text-sm font-medium text-cosechar-ink">Nombre <x-obligatorio /></label>
            <input type="text" id="nombre" wire:model.blur="nombre" autocomplete="off" class="{{ $campo }}">
            @error('nombre')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="categoria_padre_id" class="mb-1 block text-sm font-medium text-cosechar-ink">Grupo <span class="font-normal text-cosechar-muted">(opcional)</span></label>
            <select id="categoria_padre_id" wire:model.blur="categoria_padre_id" @disabled($this->editando) class="{{ $campo }}">
                <option value="">Ninguno (es un grupo principal)</option>
                @foreach ($this->grupos as $grupo)
                    <option value="{{ $grupo->id }}">{{ $grupo->nombre }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-cosechar-muted">
                @if ($this->editando)
                    El grupo de una categoría no se cambia; si hace falta, cree otra y mueva sus productos.
                @else
                    Elija un grupo para crear una subcategoría. Un grupo que ya tiene productos asignados no admite subcategorías.
                @endif
            </p>
            @error('categoria_padre_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">{{ $this->editando ? 'Guardar cambios' : 'Guardar categoría' }}</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
