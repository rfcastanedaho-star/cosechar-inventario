<div class="mx-auto max-w-5xl p-6">
    <h1 class="mb-6 text-lg font-semibold text-gray-900">Reportes</h1>

    <div class="mb-6 flex gap-2 rounded-md bg-gray-100 p-1 text-sm font-medium">
        <button type="button" wire:click="$set('tab', 'stock')" class="flex-1 rounded-md py-2 {{ $tab === 'stock' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500' }}">
            Stock actual
        </button>
        <button type="button" wire:click="$set('tab', 'movimientos')" class="flex-1 rounded-md py-2 {{ $tab === 'movimientos' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500' }}">
            Movimientos
        </button>
    </div>

    @if ($tab === 'stock')
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-500">{{ $this->stock->count() }} lotes con stock</p>
            @can('exportar-reportes')
                <div class="flex gap-2">
                    <a href="{{ route('reportes.stock.pdf') }}" target="_blank" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">PDF</a>
                    <a href="{{ route('reportes.stock.csv') }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Excel (CSV)</a>
                </div>
            @endcan
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Producto</th>
                        <th class="px-4 py-2">Categoría</th>
                        <th class="px-4 py-2">Lote</th>
                        <th class="px-4 py-2">Vencimiento</th>
                        <th class="px-4 py-2">Cantidad</th>
                        <th class="px-4 py-2">Stock mínimo del producto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->stock as $lote)
                        <tr>
                            <td class="px-4 py-2">{{ $lote->producto->codigo }} — {{ $lote->producto->nombre }}</td>
                            <td class="px-4 py-2">{{ $lote->producto->categoria->nombre }}</td>
                            <td class="px-4 py-2">{{ $lote->numero_lote }}</td>
                            <td class="px-4 py-2">{{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}</td>
                            <td class="px-4 py-2">{{ $lote->cantidad }} {{ $lote->producto->unidad_medida }}</td>
                            <td class="px-4 py-2">{{ $lote->producto->stock_minimo }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">No hay lotes con stock disponible.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
            <input type="date" wire:model.live="fecha" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-300 focus:ring">

            <select wire:model.live="producto_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-300 focus:ring">
                <option value="">Todos los productos</option>
                @foreach ($this->productos as $producto)
                    <option value="{{ $producto->id }}">{{ $producto->nombre }}</option>
                @endforeach
            </select>

            <select wire:model.live="tipo" class="rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-300 focus:ring">
                <option value="">Todos los tipos</option>
                <option value="entrada">Entrada</option>
                <option value="salida">Salida</option>
            </select>

            <button type="button" wire:click="limpiarFiltros" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Limpiar filtros
            </button>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-500">{{ $this->movimientos->count() }} movimientos</p>
            @can('exportar-reportes')
                <div class="flex gap-2">
                    <a href="{{ route('reportes.movimientos.pdf', ['fecha' => $fecha, 'producto_id' => $producto_id, 'tipo' => $tipo]) }}" target="_blank" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">PDF</a>
                    <a href="{{ route('reportes.movimientos.csv', ['fecha' => $fecha, 'producto_id' => $producto_id, 'tipo' => $tipo]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Excel (CSV)</a>
                </div>
            @endcan
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Fecha</th>
                        <th class="px-4 py-2">Producto</th>
                        <th class="px-4 py-2">Lote</th>
                        <th class="px-4 py-2">Tipo</th>
                        <th class="px-4 py-2">Cantidad</th>
                        <th class="px-4 py-2">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->movimientos as $movimiento)
                        <tr>
                            <td class="px-4 py-2">{{ $movimiento->fecha->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $movimiento->lote->producto->nombre }}</td>
                            <td class="px-4 py-2">{{ $movimiento->lote->numero_lote }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $movimiento->tipo === 'entrada' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($movimiento->tipo) }}
                                </span>
                            </td>
                            <td class="px-4 py-2">{{ $movimiento->cantidad }}</td>
                            <td class="px-4 py-2">{{ $movimiento->motivo ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">No hay movimientos con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
