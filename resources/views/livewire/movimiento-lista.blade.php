@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
    $hayFiltros = trim($buscar) !== '' || $tipo !== '' || $almacen_id || $motivo !== '' || $desde !== '' || $hasta !== '';
@endphp

<div class="mx-auto max-w-6xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Historial de movimientos</h1>
        <a href="{{ route('movimientos.nuevo') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar movimiento
        </a>
    </div>

    <div class="mb-4 grid gap-3 rounded-2xl border border-cosechar-border bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="sm:col-span-2">
            <label for="buscar" class="mb-1 block text-xs font-medium text-cosechar-muted">Producto, código o lote</label>
            <input type="search" id="buscar" wire:model.live.debounce.300ms="buscar" placeholder="Escriba para buscar..." autocomplete="off" class="{{ $campo }}">
        </div>

        <div>
            <label for="tipo" class="mb-1 block text-xs font-medium text-cosechar-muted">Tipo</label>
            <select id="tipo" wire:model.live="tipo" class="{{ $campo }}">
                <option value="">Todos</option>
                <option value="entrada">Entrada</option>
                <option value="salida">Salida</option>
            </select>
        </div>

        <div>
            <label for="motivo" class="mb-1 block text-xs font-medium text-cosechar-muted">Motivo</label>
            <select id="motivo" wire:model.live="motivo" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach ($this->motivos as $clave => $etiqueta)
                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        @unless ($this->almacenes->count() === 1)
            <div>
                <label for="almacen_id" class="mb-1 block text-xs font-medium text-cosechar-muted">Almacén</label>
                <select id="almacen_id" wire:model.live="almacen_id" class="{{ $campo }}">
                    <option value="">Todos</option>
                    @foreach ($this->almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            </div>
        @endunless

        <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:col-span-6 lg:max-w-md">
            <div>
                <label for="desde" class="mb-1 block text-xs font-medium text-cosechar-muted">Desde</label>
                <input type="date" id="desde" wire:model.live="desde" class="{{ $campo }}">
            </div>
            <div>
                <label for="hasta" class="mb-1 block text-xs font-medium text-cosechar-muted">Hasta</label>
                <input type="date" id="hasta" wire:model.live="hasta" class="{{ $campo }}">
            </div>
        </div>
    </div>

    @if ($hayFiltros)
        <p class="mb-3 text-sm text-cosechar-muted">
            {{ $this->movimientos->total() }} resultado(s) ·
            <button type="button" wire:click="limpiarFiltros" class="font-medium text-cosechar-olive underline">Quitar filtros</button>
        </p>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Lote</th>
                    <th class="px-4 py-3">Almacén</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3">Motivo</th>
                    <th class="px-4 py-3">Responsable</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->movimientos as $movimiento)
                    <tr wire:key="movimiento-{{ $movimiento->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-cosechar-muted">{{ $movimiento->fecha->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-cosechar-ink">{{ $movimiento->lote->producto->nombre }}</span>
                            <span class="block text-xs text-cosechar-muted">{{ $movimiento->lote->producto->codigo }}</span>
                        </td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $movimiento->lote->numero_lote }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $movimiento->lote->almacen?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($movimiento->tipo === 'entrada')
                                <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Entrada</span>
                            @else
                                <span class="rounded-full bg-[#F3E0DC] px-2 py-1 text-xs font-semibold text-cosechar-danger">Salida</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-cosechar-ink">{{ $movimiento->tipo === 'entrada' ? '+' : '−' }}{{ $movimiento->cantidad }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">
                            {{ $movimiento->etiquetaMotivo }}
                            @if ($movimiento->venta_id)
                                <span class="block text-xs">Venta #{{ $movimiento->venta_id }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $movimiento->responsable?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-cosechar-muted">
                            {{ $hayFiltros ? 'No se encontraron movimientos con esos filtros.' : 'No hay movimientos registrados.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->movimientos->total() > 0)
        <div class="mt-4 flex items-center justify-between text-sm text-cosechar-muted">
            <p>Mostrando {{ $this->movimientos->firstItem() }}–{{ $this->movimientos->lastItem() }} de {{ $this->movimientos->total() }}</p>
            <div class="flex gap-2">
                <button
                    type="button"
                    wire:click="previousPage"
                    @disabled($this->movimientos->onFirstPage())
                    class="rounded-md border border-cosechar-border bg-white px-3 py-1.5 text-cosechar-ink hover:bg-cosechar-cream disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Anterior
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    @disabled(! $this->movimientos->hasMorePages())
                    class="rounded-md border border-cosechar-border bg-white px-3 py-1.5 text-cosechar-ink hover:bg-cosechar-cream disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Siguiente
                </button>
            </div>
        </div>
    @endif
</div>
