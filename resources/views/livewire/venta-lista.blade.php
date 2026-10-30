@php($variosAlmacenes = ! \App\Models\Almacen::unicoActivo())
<div class="mx-auto max-w-6xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Historial de ventas</h1>
        <a href="{{ route('ventas.index') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar venta
        </a>
    </div>

    @if ($errorAnulacion)
        <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-800">{{ $errorAnulacion }}</div>
    @endif

    @include('livewire.partials.filtros-historial', [
        'placeholder' => 'Cliente, documento, comprobante o producto...',
        'estados' => ['vigente' => 'Vigente', 'anulada' => 'Anulada'],
        'conAlmacen' => true, 'conFechas' => true, 'almacenes' => $this->almacenesFiltro,
        'total' => $this->ventas->total(),
    ])

    <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Cliente</th>
                    @if ($variosAlmacenes)
                        <th class="px-4 py-3">Almacén</th>
                    @endif
                    <th class="px-4 py-3">Comprobante</th>
                    <th class="px-4 py-3">Productos</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->ventas as $venta)
                    <tr wire:key="venta-{{ $venta->id }}" class="{{ $venta->anulada_at ? 'bg-cosechar-cream/60 text-cosechar-muted' : '' }}">
                        <td class="px-4 py-3 text-cosechar-muted">{{ $venta->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-medium {{ $venta->anulada_at ? '' : 'text-cosechar-ink' }}">{{ $venta->cliente_nombre }}
                            @if ($venta->cliente_id)
                                <a href="{{ route('clientes.editar', ['cliente' => $venta->cliente_id, 'desde' => 'ventas']) }}" class="block text-xs font-normal text-cosechar-olive underline hover:text-cosechar-forest">Corregir cliente</a>
                            @endif
                        </td>
                        @if ($variosAlmacenes)
                            <td class="px-4 py-3 text-cosechar-muted">{{ $venta->almacen->nombre }}</td>
                        @endif
                        <td class="px-4 py-3 text-cosechar-muted">{{ $venta->numero_comprobante ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $venta->detalles->map(fn ($detalle) => $detalle->producto->nombre.($detalle->por_mayor ? ' (por mayor)' : ''))->join(', ') }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $venta->anulada_at ? 'line-through' : 'text-cosechar-ink' }}">S/ {{ number_format($venta->total, 2) }}</td>
                        <td class="px-4 py-3">
                            @if ($venta->anulada_at)
                                <span class="rounded-full bg-[#F3E0DC] px-2 py-1 text-xs font-semibold text-cosechar-danger">Anulada</span>
                                <p class="mt-1 max-w-48 text-xs text-cosechar-muted">
                                    {{ $venta->anulada_at->format('d/m/Y') }} por {{ $venta->anuladaPor?->name }}: {{ $venta->motivo_anulacion }}
                                </p>
                            @elseif ($anulandoId === $venta->id)
                                <div class="flex flex-col gap-2">
                                    <input
                                        type="text"
                                        wire:model="motivoAnulacion"
                                        wire:keydown.enter="confirmarAnulacion"
                                        placeholder="Motivo de la anulación"
                                        class="w-56 rounded-md border border-cosechar-border px-2 py-1 text-xs"
                                    >
                                    @error('motivoAnulacion')
                                        <p class="text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="confirmarAnulacion" class="rounded-md bg-cosechar-danger px-2 py-1 text-xs font-medium text-white">Confirmar anulación</button>
                                        <button type="button" wire:click="cancelarAnulacion" class="rounded-md border border-cosechar-border px-2 py-1 text-xs text-cosechar-muted">Cancelar</button>
                                    </div>
                                </div>
                            @else
                                <span class="rounded-full bg-cosechar-olive/15 px-2 py-1 text-xs font-semibold text-cosechar-olive">Vigente</span>
                                @can('anular-registros')
                                    <button type="button" wire:click="iniciarAnulacion({{ $venta->id }})" class="ml-2 text-xs font-medium text-cosechar-danger underline">Anular</button>
                                @endcan
                            @endif
                            <a href="{{ route('ventas.comprobante', $venta) }}" target="_blank" class="mt-1 block text-xs font-medium text-cosechar-olive underline hover:text-cosechar-forest">Comprobante PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $variosAlmacenes ? 7 : 6 }}" class="px-4 py-6 text-center text-cosechar-muted">{{ $this->hayFiltros() ? 'No se encontraron ventas con esos filtros.' : 'No hay ventas registradas.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('livewire.partials.paginacion', ['paginador' => $this->ventas])
</div>
