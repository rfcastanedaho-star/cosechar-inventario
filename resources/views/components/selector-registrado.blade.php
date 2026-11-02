{{--
    Campo con lista desplegable para elegir un proveedor o cliente YA registrado (Compras y Ventas).
    Al escribir se filtran los que coinciden; la flechita abre la lista completa en orden alfabético.
    Si no está registrado, se envía a su formulario del menú: aquí no se crea nada.
--}}
@props([
    'id',
    'etiqueta',
    'propBuscar',
    'textoBuscar' => '',
    'propAbierto',
    'propError',
    'seleccionado' => null,
    'sugeridos',
    'elegir',
    'limpiar',
    'alternar',
    'placeholder',
    'campoDoc',
    'etiquetaDoc',
    'nuevaRuta',
    'nuevoTexto',
    'vacio',
])

<div
    x-data
    x-on:click.outside="if ($wire.{{ $propAbierto }}) $wire.$set('{{ $propAbierto }}', false)"
    class="relative"
>
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-cosechar-ink">{{ $etiqueta }} <x-obligatorio /></label>

    @if ($seleccionado)
        <div class="flex items-center justify-between gap-2 rounded-md border border-cosechar-border bg-cosechar-cream px-3 py-2 text-sm">
            <span class="min-w-0 truncate text-cosechar-ink">
                <span class="mr-2 rounded-full bg-cosechar-olive/15 px-2 py-0.5 text-[11px] font-semibold text-cosechar-olive">Registrado</span>
                {{ $seleccionado->nombre }}@if ($seleccionado->{$campoDoc}) · {{ $etiquetaDoc }} {{ $seleccionado->{$campoDoc} }}@endif
            </span>
            <button type="button" wire:click="{{ $limpiar }}" class="shrink-0 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">Cambiar</button>
        </div>

        {{ $slot }}
    @else
        <div class="relative">
            <input
                type="text"
                id="{{ $id }}"
                wire:model.live.debounce.250ms="{{ $propBuscar }}"
                wire:focus="$set('{{ $propAbierto }}', true)"
                wire:keydown.enter.prevent="{{ $elegir }}"
                placeholder="{{ $placeholder }}"
                autocomplete="off"
                class="w-full rounded-md border border-cosechar-border py-2 pl-3 pr-10 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
            >
            <button
                type="button"
                wire:click="{{ $alternar }}"
                tabindex="-1"
                aria-label="Mostrar la lista de registrados"
                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-cosechar-muted hover:text-cosechar-ink"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
            </button>
        </div>

        @if ($sugeridos->isNotEmpty())
            <ul class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-cosechar-border bg-white shadow-lg">
                @foreach ($sugeridos as $sugerido)
                    <li wire:key="sugerido-{{ $id }}-{{ $sugerido->id }}">
                        <button
                            type="button"
                            wire:click="{{ $elegir }}({{ $sugerido->id }})"
                            class="block w-full px-3 py-2 text-left text-sm text-cosechar-ink hover:bg-cosechar-cream"
                        >
                            {{ $sugerido->nombre }}
                            @if ($sugerido->{$campoDoc})
                                <span class="text-xs text-cosechar-muted">· {{ $etiquetaDoc }} {{ $sugerido->{$campoDoc} }}</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        @elseif (trim($textoBuscar) !== '')
            <div class="absolute z-20 mt-1 w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm text-cosechar-muted shadow-lg">
                {{ $vacio }}
                <a href="{{ $nuevaRuta }}" class="font-medium text-cosechar-forest underline hover:text-cosechar-ink">{{ $nuevoTexto }}</a>
            </div>
        @endif
    @endif

    @error($propError)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
