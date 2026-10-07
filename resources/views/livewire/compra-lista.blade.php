<div class="mx-auto max-w-6xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Historial de compras</h1>
        <a href="{{ route('compras.index') }}" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90">
            Registrar compra
        </a>
    </div>

    @if ($errorAnulacion)
        <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-800">{{ $errorAnulacion }}</div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Proveedor</th>
                    <th class="px-4 py-3">Almacén</th>
                    <th class="px-4 py-3">Comprobante</th>
                    <th class="px-4 py-3">Productos</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->compras as $compra)
                    <tr wire:key="compra-{{ $compra->id }}" class="{{ $compra->anulada_at ? 'bg-cosechar-cream/60 text-cosechar-muted' : '' }}">
                        <td class="px-4 py-3 text-cosechar-muted">{{ $compra->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-medium {{ $compra->anulada_at ? '' : 'text-cosechar-ink' }}">{{ $compra->proveedor->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $compra->almacen->nombre }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $compra->numero_comprobante ?: '—' }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $compra->detalles->pluck('producto.nombre')->join(', ') }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $compra->anulada_at ? 'line-through' : 'text-cosechar-ink' }}">S/ {{ number_format($compra->total, 2) }}</td>
                        <td class="px-4 py-3">
                            @if ($compra->anulada_at)
                                <span class="rounded-full bg-[#F3E0DC] px-2 py-1 text-xs font-semibold text-cosechar-danger">Anulada</span>
                                <p class="mt-1 max-w-48 text-xs text-cosechar-muted">
                                    {{ $compra->anulada_at->format('d/m/Y') }} por {{ $compra->anuladaPor?->name }}: {{ $compra->motivo_anulacion }}
                                </p>
                            @elseif ($anulandoId === $compra->id)
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
                                    <button type="button" wire:click="iniciarAnulacion({{ $compra->id }})" class="ml-2 text-xs font-medium text-cosechar-danger underline">Anular</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-cosechar-muted">No hay compras registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
