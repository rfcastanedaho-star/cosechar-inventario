{{--
    Filtros comunes de los historiales. Variables:
    - $placeholder: texto de ayuda del buscador
    - $estados: [valor => etiqueta] del filtro de estado
    - $conAlmacen / $conFechas: si se muestran esos filtros (por defecto no)
    - $almacenes: lista de almacenes para el filtro (si $conAlmacen)
    - $total: cantidad de resultados del paginador
--}}
@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
    $conAlmacen = $conAlmacen ?? false;
    $conFechas = $conFechas ?? false;
@endphp

<div class="mb-4 grid gap-3 rounded-2xl border border-cosechar-border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="sm:col-span-2">
        <label for="buscar" class="mb-1 block text-xs font-medium text-cosechar-muted">Buscar</label>
        <input type="search" id="buscar" wire:model.live.debounce.300ms="buscar" placeholder="{{ $placeholder }}" autocomplete="off" class="{{ $campo }}">
    </div>

    <div>
        <label for="estado" class="mb-1 block text-xs font-medium text-cosechar-muted">Estado</label>
        <select id="estado" wire:model.live="estado" class="{{ $campo }}">
            <option value="">Todos</option>
            @foreach ($estados as $valor => $etiqueta)
                <option value="{{ $valor }}">{{ $etiqueta }}</option>
            @endforeach
        </select>
    </div>

    @if ($conAlmacen)
        <div>
            <label for="almacen_id" class="mb-1 block text-xs font-medium text-cosechar-muted">Almacén</label>
            <select id="almacen_id" wire:model.live="almacen_id" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach ($almacenes as $almacen)
                    <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($conFechas)
        <div>
            <label for="desde" class="mb-1 block text-xs font-medium text-cosechar-muted">Desde</label>
            <input type="date" id="desde" wire:model.live="desde" class="{{ $campo }}">
        </div>

        <div>
            <label for="hasta" class="mb-1 block text-xs font-medium text-cosechar-muted">Hasta</label>
            <input type="date" id="hasta" wire:model.live="hasta" class="{{ $campo }}">
        </div>
    @endif
</div>

@if ($this->hayFiltros())
    <p class="mb-3 text-sm text-cosechar-muted">
        {{ $total }} resultado(s) ·
        <button type="button" wire:click="limpiarFiltros" class="font-medium text-cosechar-olive underline">Quitar filtros</button>
    </p>
@endif
