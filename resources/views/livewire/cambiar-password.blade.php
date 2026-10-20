@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
@endphp

<div class="mx-auto max-w-md p-8">
    <h1 class="mb-6 text-lg font-semibold text-cosechar-ink">Cambiar contraseña</h1>

    @if ($actualizada)
        <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            Contraseña actualizada correctamente.
        </div>
    @endif

    <form wire:submit="guardar" class="flex flex-col gap-4">
        <div>
            <label for="password_actual" class="mb-1 block text-sm font-medium text-cosechar-ink">Contraseña actual</label>
            <input type="password" id="password_actual" wire:model="password_actual" autocomplete="current-password" class="{{ $campo }}">
            @error('password_actual')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-cosechar-ink">Nueva contraseña</label>
            <input type="password" id="password" wire:model="password" autocomplete="new-password" class="{{ $campo }}">
            <p class="mt-1 text-xs text-cosechar-muted">Mínimo 8 caracteres y distinta de la actual.</p>
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-cosechar-ink">Repetir la nueva contraseña</label>
            <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" class="{{ $campo }}">
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">Cambiar contraseña</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
