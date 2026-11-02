{{--
    Campo con lista desplegable para elegir un producto (líneas de Compras y Ventas, filtro de Reportes).
    Al escribir se filtran los que coinciden (por nombre, código o marca); la flechita abre la lista
    completa en orden alfabético. Las rutas son de propiedades Livewire (p. ej. "listaProducto.0").
--}}
@props([
    'buscar',
    'texto' => '',
    'rutaAbierto',
    'alternar',
    'enter' => null,
    'elegir',
    'sugeridos',
    'placeholder' => 'Escriba las iniciales del nombre o el código, o abra la lista',
    'tipo' => 'text',
    'claseInput' => 'w-full rounded-md border border-cosechar-border px-2 py-1.5 text-sm',
])

<div
    x-data
    x-on:click.outside="if ($wire.$get('{{ $rutaAbierto }}')) $wire.$set('{{ $rutaAbierto }}', false)"
    class="relative"
>
    <div class="relative">
        <input
            type="{{ $tipo }}"
            wire:model.live.debounce.250ms="{{ $buscar }}"
            wire:focus="$set('{{ $rutaAbierto }}', true)"
            @if ($enter) wire:keydown.enter.prevent="{{ $enter }}" @endif
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            class="{{ $claseInput }} pr-9"
        >
        <button
            type="button"
            wire:click="{{ $alternar }}"
            tabindex="-1"
            aria-label="Mostrar la lista de productos"
            class="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-cosechar-muted hover:text-cosechar-ink"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
        </button>
    </div>

    @if ($sugeridos->isNotEmpty())
        <ul class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-cosechar-border bg-white shadow-lg">
            @foreach ($sugeridos as $sugerido)
                <li wire:key="sp-{{ $buscar }}-{{ $sugerido->id }}">
                    <button
                        type="button"
                        wire:click="{{ $elegir }}{{ $sugerido->id }})"
                        class="block w-full px-3 py-1.5 text-left text-sm text-cosechar-ink hover:bg-cosechar-cream"
                    >
                        <x-producto-etiqueta :producto="$sugerido" />
                    </button>
                </li>
            @endforeach
        </ul>
    @elseif (trim($texto) !== '')
        <div class="absolute z-20 mt-1 w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm text-cosechar-muted shadow-lg">
            No se encontró ningún producto con ese nombre o código.
        </div>
    @endif
</div>
