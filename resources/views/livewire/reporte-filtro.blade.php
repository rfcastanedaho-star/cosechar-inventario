@php
    $campo = 'rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
    $botonExportar = 'rounded-md border border-cosechar-border bg-white px-3 py-1.5 text-sm text-cosechar-ink hover:bg-cosechar-cream';
    $filtrosRango = ['desde' => $desde, 'hasta' => $hasta, 'almacen_id' => $almacen_id];
    $pestanas = ['stock' => 'Stock actual', 'movimientos' => 'Movimientos', 'compras' => 'Compras', 'ventas' => 'Ventas'];

    if (auth()->user()->can('ver-valorizacion')) {
        $pestanas['valorizacion'] = 'Valorización';
    }
@endphp

<div class="mx-auto max-w-6xl p-8">
    <h1 class="mb-6 text-lg font-semibold text-cosechar-ink">Reportes</h1>

    <div class="mb-6 flex gap-2 rounded-xl bg-cosechar-border/60 p-1 text-sm font-medium">
        @foreach ($pestanas as $clave => $etiqueta)
            <button type="button" wire:click="$set('tab', '{{ $clave }}')" class="flex-1 rounded-lg py-2 {{ $tab === $clave ? 'bg-white text-cosechar-ink shadow-sm' : 'text-cosechar-muted' }}">
                {{ $etiqueta }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'stock')
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
            @unless ($this->almacenes->count() === 1)
                <select wire:model.live="almacen_id" class="{{ $campo }}">
                    <option value="">Todos los almacenes</option>
                    @foreach ($this->almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            @endunless
            <button type="button" wire:click="limpiarFiltros" class="rounded-md border border-cosechar-border px-3 py-2 text-sm text-cosechar-muted hover:bg-cosechar-cream">Limpiar filtros</button>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-cosechar-muted">{{ $this->stock->count() }} lotes con stock</p>
            @can('exportar-reportes')
                <div class="flex gap-2">
                    <a href="{{ route('reportes.stock.pdf', ['almacen_id' => $almacen_id]) }}" target="_blank" class="{{ $botonExportar }}">PDF</a>
                    <a href="{{ route('reportes.stock.excel', ['almacen_id' => $almacen_id]) }}" class="{{ $botonExportar }}">Excel</a>
                </div>
            @endcan
        </div>

        <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3">Almacén</th>
                        <th class="px-4 py-3">Lote</th>
                        <th class="px-4 py-3">Vencimiento</th>
                        <th class="px-4 py-3">Cantidad</th>
                        <th class="px-4 py-3">Stock mínimo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cosechar-border">
                    @forelse ($this->stock as $lote)
                        <tr>
                            <td class="px-4 py-3">{{ $lote->producto->codigo }} — {{ $lote->producto->nombre }}</td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $lote->producto->categoria->nombre }}</td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $lote->almacen?->nombre ?? 'Sin almacén' }}</td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $lote->etiqueta }}</td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}</td>
                            <td class="px-4 py-3">{{ $lote->cantidad }} {{ $lote->producto->unidad_medida }}</td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $lote->producto->stock_minimo }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-cosechar-muted">No hay lotes con stock disponible.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'valorizacion')
        @php
            $valorizacion = $this->valorizacion;
        @endphp

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
            @unless ($this->almacenes->count() === 1)
                <select wire:model.live="almacen_id" class="{{ $campo }}">
                    <option value="">Todos los almacenes</option>
                    @foreach ($this->almacenes as $almacen)
                        <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                    @endforeach
                </select>
            @endunless
            <button type="button" wire:click="limpiarFiltros" class="rounded-md border border-cosechar-border px-3 py-2 text-sm text-cosechar-muted hover:bg-cosechar-cream">Limpiar filtros</button>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-cosechar-border bg-white p-4">
                <p class="text-xs uppercase text-cosechar-muted">Valor a costo</p>
                <p class="mt-1 text-xl font-semibold text-cosechar-ink">S/ {{ number_format($valorizacion['totales']['valorCosto'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-cosechar-border bg-white p-4">
                <p class="text-xs uppercase text-cosechar-muted">Valor a precio de venta</p>
                <p class="mt-1 text-xl font-semibold text-cosechar-ink">S/ {{ number_format($valorizacion['totales']['valorVenta'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-cosechar-border bg-white p-4">
                <p class="text-xs uppercase text-cosechar-muted">Utilidad potencial</p>
                <p class="mt-1 text-xl font-semibold text-cosechar-olive">S/ {{ number_format($valorizacion['totales']['utilidad'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-cosechar-border bg-white p-4">
                <p class="text-xs uppercase text-cosechar-muted">Stock valorizado</p>
                <p class="mt-1 text-xl font-semibold text-cosechar-ink">{{ $valorizacion['totales']['unidades'] }} unidades</p>
                <p class="text-xs text-cosechar-muted">{{ $valorizacion['totales']['productos'] }} producto(s)</p>
            </div>
        </div>

        @unless ($valorizacion['totales']['completo'])
            <div class="mb-4 rounded-md bg-amber-100 px-4 py-2 text-sm text-amber-900">
                Hay {{ $valorizacion['totales']['unidadesSinCosto'] }} unidades sin costo registrado: no suman al valor a costo, así que el total es incompleto.
            </div>
        @endunless

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-cosechar-muted">{{ $valorizacion['filas']->count() }} línea(s) de producto y almacén</p>
            <div class="flex gap-2">
                <a href="{{ route('reportes.valorizacion.pdf', ['almacen_id' => $almacen_id]) }}" target="_blank" class="{{ $botonExportar }}">PDF</a>
                <a href="{{ route('reportes.valorizacion.excel', ['almacen_id' => $almacen_id]) }}" class="{{ $botonExportar }}">Excel</a>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Almacén</th>
                        <th class="px-4 py-3 text-right">Unidades</th>
                        <th class="px-4 py-3 text-right">Costo promedio</th>
                        <th class="px-4 py-3 text-right">Valor a costo</th>
                        <th class="px-4 py-3 text-right">Valor a precio de venta</th>
                        <th class="px-4 py-3 text-right">Utilidad potencial</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cosechar-border">
                    @forelse ($valorizacion['filas'] as $fila)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-medium text-cosechar-ink">{{ $fila->producto->nombre }}</span>
                                <span class="block text-xs text-cosechar-muted">{{ $fila->producto->codigo }}</span>
                            </td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $fila->almacen?->nombre ?? 'Sin almacén' }}</td>
                            <td class="px-4 py-3 text-right">{{ $fila->unidades }}</td>
                            <td class="px-4 py-3 text-right text-cosechar-muted">
                                {{ $fila->costoPromedio !== null ? 'S/ '.number_format($fila->costoPromedio, 2) : 'sin costo' }}
                            </td>
                            <td class="px-4 py-3 text-right">S/ {{ number_format($fila->valorCosto, 2) }}</td>
                            <td class="px-4 py-3 text-right">S/ {{ number_format($fila->valorVenta, 2) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-cosechar-olive">S/ {{ number_format($fila->utilidad, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-cosechar-muted">No hay stock para valorizar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'movimientos')
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
            <input type="date" wire:model.live="fecha" class="{{ $campo }}">

            <div>
                @if ($this->productoSeleccionado)
                    <div class="{{ $campo }} flex items-center justify-between gap-2">
                        <span class="min-w-0 truncate text-cosechar-ink"><x-producto-etiqueta :producto="$this->productoSeleccionado" /></span>
                        <button type="button" wire:click="limpiarProducto" class="shrink-0 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">Cambiar</button>
                    </div>
                @else
                    <input
                        type="search"
                        wire:model.live.debounce.250ms="buscarProducto"
                        placeholder="Todos los productos (escriba para buscar)"
                        autocomplete="off"
                        class="{{ $campo }}"
                    >

                    @if (trim($buscarProducto) !== '')
                        <ul class="mt-1 max-h-56 overflow-auto rounded-md border border-cosechar-border bg-white shadow-sm">
                            @forelse ($this->productosSugeridos as $sugerido)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="elegirProducto({{ $sugerido->id }})"
                                        class="block w-full px-3 py-2 text-left text-sm text-cosechar-ink hover:bg-cosechar-cream"
                                    >
                                        <x-producto-etiqueta :producto="$sugerido" />
                                    </button>
                                </li>
                            @empty
                                <li class="px-3 py-2 text-sm text-cosechar-muted">No se encontró ningún producto con ese nombre o código.</li>
                            @endforelse
                        </ul>
                    @endif
                @endif
            </div>

            <select wire:model.live="tipo" class="{{ $campo }}">
                <option value="">Todos los tipos</option>
                <option value="entrada">Entrada</option>
                <option value="salida">Salida</option>
            </select>

            <button type="button" wire:click="limpiarFiltros" class="rounded-md border border-cosechar-border px-3 py-2 text-sm text-cosechar-muted hover:bg-cosechar-cream">Limpiar filtros</button>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-cosechar-muted">{{ $this->movimientos->count() }} movimientos</p>
            @can('exportar-reportes')
                <div class="flex gap-2">
                    <a href="{{ route('reportes.movimientos.pdf', ['fecha' => $fecha, 'producto_id' => $producto_id, 'tipo' => $tipo]) }}" target="_blank" class="{{ $botonExportar }}">PDF</a>
                    <a href="{{ route('reportes.movimientos.excel', ['fecha' => $fecha, 'producto_id' => $producto_id, 'tipo' => $tipo]) }}" class="{{ $botonExportar }}">Excel</a>
                </div>
            @endcan
        </div>

        <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Lote</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Cantidad</th>
                        <th class="px-4 py-3">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cosechar-border">
                    @forelse ($this->movimientos as $movimiento)
                        <tr>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $movimiento->fecha->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $movimiento->lote->producto->nombre }}</td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $movimiento->lote->etiqueta }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $movimiento->tipo === 'entrada' ? 'bg-cosechar-olive/15 text-cosechar-olive' : 'bg-[#F3E0DC] text-cosechar-danger' }}">
                                    {{ ucfirst($movimiento->tipo) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $movimiento->cantidad }}</td>
                            <td class="px-4 py-3 text-cosechar-muted">{{ $movimiento->motivo ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-cosechar-muted">No hay movimientos con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        @php
            $esCompras = $tab === 'compras';
            $registros = $esCompras ? $this->compras : $this->ventas;
            $total = $esCompras ? $this->totalCompras : $this->totalVentas;
            $rutaPdf = $esCompras ? 'reportes.compras.pdf' : 'reportes.ventas.pdf';
            $rutaExcel = $esCompras ? 'reportes.compras.excel' : 'reportes.ventas.excel';
        @endphp

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs text-cosechar-muted">Desde</label>
                <input type="date" wire:model.live="desde" class="{{ $campo }} w-full">
            </div>
            <div>
                <label class="mb-1 block text-xs text-cosechar-muted">Hasta</label>
                <input type="date" wire:model.live="hasta" class="{{ $campo }} w-full">
            </div>
            @unless ($this->almacenes->count() === 1)
                <div>
                    <label class="mb-1 block text-xs text-cosechar-muted">Almacén</label>
                    <select wire:model.live="almacen_id" class="{{ $campo }} w-full">
                        <option value="">Todos los almacenes</option>
                        @foreach ($this->almacenes as $almacen)
                            <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="flex items-end">
                <button type="button" wire:click="limpiarFiltros" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm text-cosechar-muted hover:bg-cosechar-cream">Limpiar filtros</button>
            </div>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-cosechar-muted">
                {{ $registros->count() }} {{ $esCompras ? 'compras' : 'ventas' }} ·
                <span class="font-semibold text-cosechar-ink">S/ {{ number_format($total, 2) }}</span> en vigentes
            </p>
            @can('exportar-reportes')
                <div class="flex gap-2">
                    <a href="{{ route($rutaPdf, $filtrosRango) }}" target="_blank" class="{{ $botonExportar }}">PDF</a>
                    <a href="{{ route($rutaExcel, $filtrosRango) }}" class="{{ $botonExportar }}">Excel</a>
                </div>
            @endcan
        </div>

        <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">{{ $esCompras ? 'Proveedor' : 'Cliente' }}</th>
                        <th class="px-4 py-3">Almacén</th>
                        <th class="px-4 py-3">Comprobante</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cosechar-border">
                    @forelse ($registros as $registro)
                        <tr class="{{ $registro->anulada_at ? 'bg-cosechar-cream/60 text-cosechar-muted' : '' }}">
                            <td class="px-4 py-3">{{ $registro->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $esCompras ? $registro->proveedor->nombre : $registro->cliente_nombre }}</td>
                            <td class="px-4 py-3">{{ $registro->almacen->nombre }}</td>
                            <td class="px-4 py-3">{{ $registro->numero_comprobante ?: '—' }}</td>
                            <td class="px-4 py-3 text-right {{ $registro->anulada_at ? 'line-through' : 'font-medium' }}">S/ {{ number_format($registro->total, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $registro->anulada_at ? 'bg-[#F3E0DC] text-cosechar-danger' : 'bg-cosechar-olive/15 text-cosechar-olive' }}">
                                    {{ $registro->anulada_at ? 'Anulada' : 'Vigente' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-cosechar-muted">No hay {{ $esCompras ? 'compras' : 'ventas' }} con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
