@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
    $insignias = [
        'sin_stock' => 'bg-cosechar-border text-cosechar-muted',
        'bajo' => 'bg-[#F3E0DC] text-cosechar-danger',
        'normal' => 'bg-cosechar-olive/15 text-cosechar-olive',
    ];
    $conValor = auth()->user()->can('ver-valorizacion');
    $pestana = 'rounded-md px-4 py-2 text-sm font-medium';
@endphp

<div class="mx-auto max-w-6xl p-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-cosechar-ink">Inventario</h1>
            <p class="mt-1 text-sm text-cosechar-muted">El stock sale de la suma de los lotes. Las compras, las ventas y los ajustes de lotes quedan anotados en el kardex.</p>
        </div>
        <a href="{{ route('lotes.index') }}" class="shrink-0 rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Ir a Lotes
        </a>
    </div>

    <div class="mb-4 inline-flex gap-1 rounded-lg bg-cosechar-cream p-1">
        <button type="button" wire:click="$set('tab', 'stock')" class="{{ $pestana }} {{ $tab === 'stock' ? 'bg-white text-cosechar-ink shadow-sm' : 'text-cosechar-muted hover:text-cosechar-ink' }}">Stock por producto</button>
        <button type="button" wire:click="$set('tab', 'kardex')" class="{{ $pestana }} {{ $tab === 'kardex' ? 'bg-white text-cosechar-ink shadow-sm' : 'text-cosechar-muted hover:text-cosechar-ink' }}">Kardex</button>
    </div>

    @if ($tab === 'kardex')
        <livewire:movimiento-lista />
    @else
        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-cosechar-border bg-white p-4">
                <p class="text-xs font-medium text-cosechar-muted">Productos</p>
                <p class="mt-1 text-2xl font-semibold text-cosechar-ink">{{ $this->resumen['productos'] }}</p>
            </div>
            <div class="rounded-xl border border-cosechar-border bg-white p-4">
                <p class="text-xs font-medium text-cosechar-muted">Unidades en stock</p>
                <p class="mt-1 text-2xl font-semibold text-cosechar-ink">{{ number_format($this->resumen['unidades']) }}</p>
            </div>
            @if ($conValor)
                <div class="rounded-xl border border-cosechar-border bg-white p-4">
                    <p class="text-xs font-medium text-cosechar-muted">Valor a costo</p>
                    <p class="mt-1 text-2xl font-semibold text-cosechar-ink">S/ {{ number_format($this->resumen['valor'], 2) }}</p>
                </div>
            @endif
            <div class="rounded-xl border border-cosechar-border bg-white p-4">
                <p class="text-xs font-medium text-cosechar-muted">Con stock bajo</p>
                <p class="mt-1 text-2xl font-semibold {{ $this->resumen['bajo'] ? 'text-cosechar-danger' : 'text-cosechar-ink' }}">{{ $this->resumen['bajo'] }}</p>
            </div>
        </div>

        <div class="mb-4 grid gap-3 rounded-2xl border border-cosechar-border bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="buscar" class="mb-1 block text-xs font-medium text-cosechar-muted">Buscar</label>
                <input type="search" id="buscar" wire:model.live.debounce.300ms="buscar" placeholder="Producto, código o marca..." autocomplete="off" class="{{ $campo }}">
            </div>
            <div>
                <label for="estado" class="mb-1 block text-xs font-medium text-cosechar-muted">Estado del stock</label>
                <select id="estado" wire:model.live="estado" class="{{ $campo }}">
                    <option value="">Todos</option>
                    @foreach (\App\Livewire\InventarioLista::ESTADOS as $clave => $etiqueta)
                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($this->hayFiltros())
            <p class="mb-3 text-sm text-cosechar-muted">
                {{ $this->productos->count() }} resultado(s) ·
                <button type="button" wire:click="limpiarFiltros" class="font-medium text-cosechar-olive underline">Quitar filtros</button>
            </p>
        @endif

        <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                    <tr>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3 text-right">Stock actual</th>
                        <th class="px-4 py-3">Lotes</th>
                        <th class="px-4 py-3 text-right">Vendido</th>
                        <th class="px-4 py-3 text-right" title="Mermas, dañados y errores de conteo: no cuentan como venta">Ajustes</th>
                        <th class="px-4 py-3 text-right">Stock mínimo</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cosechar-border">
                    @forelse ($this->productos as $producto)
                        @php($estadoStock = \App\Livewire\InventarioLista::estadoDe($producto))
                        <tr wire:key="inv-{{ $producto->id }}">
                            <td class="px-4 py-3 text-cosechar-muted">{{ $producto->codigo }}</td>
                            <td class="px-4 py-3 font-medium text-cosechar-ink"><x-producto-etiqueta :producto="$producto" /></td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $producto->categoria->nombre }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $estadoStock === 'normal' ? 'text-cosechar-ink' : 'text-cosechar-danger' }}">{{ (int) ($producto->stock_actual ?? 0) }}</td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $producto->lotes->count() }} {{ $producto->lotes->count() === 1 ? 'lote' : 'lotes' }}</td>
                            @php($total = $this->totales[$producto->id])
                            <td class="px-4 py-3 text-right text-cosechar-ink">{{ $total['vendido'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <span class="text-cosechar-danger">−{{ $total['ajustes_menos'] }}</span>
                                <span class="text-cosechar-olive">+{{ $total['ajustes_mas'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-cosechar-muted">{{ $producto->stock_minimo }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $insignias[$estadoStock] }}">{{ \App\Livewire\InventarioLista::ESTADOS[$estadoStock] }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="abrir({{ $producto->id }})" class="text-xs font-medium text-cosechar-olive underline hover:text-cosechar-forest">{{ $abierto === $producto->id ? 'Ocultar lotes' : 'Ver lotes' }}</button>
                            </td>
                        </tr>

                        @if ($abierto === $producto->id)
                            <tr wire:key="inv-lotes-{{ $producto->id }}" class="bg-cosechar-cream/40">
                                <td colspan="10" class="px-4 py-4">
                                    @forelse ($producto->lotes as $lote)
                                        <p class="text-sm text-cosechar-ink">
                                            Lote <span class="font-medium">{{ $lote->etiqueta }}</span> ·
                                            vence {{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }} ·
                                            <span class="font-medium">{{ $lote->cantidad }}</span> unidades
                                        </p>
                                    @empty
                                        <p class="text-sm text-cosechar-muted">Este producto no tiene lotes con stock. Los lotes nacen al registrar una compra.</p>
                                    @endforelse
                                    <a href="{{ route('lotes.index', ['buscar' => $producto->nombre]) }}" class="mt-2 inline-block text-xs font-medium text-cosechar-olive underline hover:text-cosechar-forest">Ajustar en Lotes</a>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-6 text-center text-cosechar-muted">{{ $this->hayFiltros() ? 'No se encontraron productos con esos filtros.' : 'No hay productos registrados.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
