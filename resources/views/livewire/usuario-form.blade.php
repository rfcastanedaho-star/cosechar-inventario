@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
@endphp

<div class="mx-auto max-w-xl p-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-cosechar-ink">{{ $this->editando ? 'Editar usuario' : 'Registrar usuario' }}</h1>
        <a href="{{ route('usuarios.index') }}" class="rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
            Volver a usuarios
        </a>
    </div>

    <x-leyenda-obligatorios />

    <form wire:submit="guardar" class="flex flex-col gap-4">
        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-cosechar-ink">Nombre <x-obligatorio /></label>
            <input type="text" id="name" wire:model.blur="name" class="{{ $campo }}">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-cosechar-ink">Correo <x-obligatorio /></label>
            <input type="email" id="email" wire:model.blur="email" autocomplete="off" class="{{ $campo }}">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="rol" class="mb-1 block text-sm font-medium text-cosechar-ink">Rol <x-obligatorio /></label>
            <select id="rol" wire:model.blur="rol" class="{{ $campo }}">
                @foreach (\App\Models\User::ROLES as $clave => $etiqueta)
                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-cosechar-muted">El administrador edita datos sensibles, anula registros y exporta reportes; el operador registra y consulta.</p>
            @error('rol')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        @unless ($this->editando)
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-cosechar-ink">Contraseña <x-obligatorio /></label>
                    <input type="password" id="password" wire:model.blur="password" autocomplete="new-password" class="{{ $campo }}">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-cosechar-ink">Repetir contraseña <x-obligatorio /></label>
                    <input type="password" id="password_confirmation" wire:model.blur="password_confirmation" autocomplete="new-password" class="{{ $campo }}">
                </div>
            </div>
            <p class="-mt-2 text-xs text-cosechar-muted">Mínimo 8 caracteres. La persona puede cambiarla luego desde «Mi contraseña».</p>
        @endunless

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">{{ $this->editando ? 'Guardar cambios' : 'Guardar usuario' }}</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>

    @if ($this->editando)
        <form wire:submit="restablecerPassword" class="mt-8 flex flex-col gap-4 border-t border-cosechar-border pt-6">
            <h2 class="text-sm font-semibold text-cosechar-ink">Restablecer contraseña</h2>

            @if ($passwordRestablecida)
                <div class="rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
                    Contraseña restablecida correctamente. Comuníquesela a la persona por un medio seguro.
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nuevaPassword" class="mb-1 block text-sm font-medium text-cosechar-ink">Nueva contraseña <x-obligatorio /></label>
                    <input type="password" id="nuevaPassword" wire:model="nuevaPassword" autocomplete="new-password" class="{{ $campo }}">
                    @error('nuevaPassword')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="nuevaPassword_confirmation" class="mb-1 block text-sm font-medium text-cosechar-ink">Repetir contraseña <x-obligatorio /></label>
                    <input type="password" id="nuevaPassword_confirmation" wire:model="nuevaPassword_confirmation" autocomplete="new-password" class="{{ $campo }}">
                </div>
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="restablecerPassword"
                class="self-start rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream disabled:cursor-not-allowed disabled:opacity-50"
            >
                Restablecer contraseña
            </button>
        </form>
    @endif
</div>
