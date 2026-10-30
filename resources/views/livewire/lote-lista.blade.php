@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
    $insignias = [
        'vigente' => 'bg-cosechar-olive/15 text-cosechar-olive',
        'por_vencer' => 'bg-amber-100 text-amber-800',
        'vencido' => 'bg-[#F3E0DC] text-cosechar-danger',
        'agotado' => 'bg-cosechar-border text-cosechar-muted',
    ];
    $conCosto = auth()->user()->can('ver-valorizacion');
@endphp

<div class="mx-auto max-w-6xl p-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-cosechar-ink">Lotes</h1>
            <p class="mt-1 text-sm text-cosechar-muted">Controle el vencimiento y corrija las cantidades de cada lote. Los lotes nuevos nacen al registrar una compra.</p>
        </div>
        <a href="{{ route('inventario.index', ['tab' => 'kardex']) }}" class="shrink-0 rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
            Ver kardex
        </a>
    </div>

    @if ($mensaje)
        <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">{{ $mensaje }} Quedó registrado en el kardex.</div>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-cosechar-border bg-white p-4">
            <p class="text-xs font-medium text-cosechar-muted">Lotes con stock</p>
            <p class="mt-1 text-2xl font-semibold text-cosechar-ink">{{ $this->resumen['con_stock'] }}</p>
        </div>
        <div class="rounded-xl border border-cosechar-border bg-white p-4">
            <p class="text-xs font-medium text-cosechar-muted">Por vencer (30 días)</p>
            <p class="mt-1 text-2xl font-semibold {{ $this->resumen['por_vencer'] ? 'text-amber-700' : 'text-cosechar-ink' }}">{{ $this->resumen['por_vencer'] }}</p>
        </div>
        <div class="rounded-xl border border-cosechar-border bg-white p-4">
            <p class="text-xs font-medium text-cosechar-muted">Vencidos con stock</p>
            <p class="mt-1 text-2xl font-semibold {{ $this->resumen['vencidos'] ? 'text-cosechar-danger' : 'text-cosechar-ink' }}">{{ $this->resumen['vencidos'] }}</p>
        </div>
        <div class="rounded-xl border border-cosechar-border bg-white p-4">
            <p class="text-xs font-medium text-cosechar-muted">Agotados</p>
            <p class="mt-1 text-2xl font-semibold text-cosechar-ink">{{ $this->resumen['agotados'] }}</p>
        </div>
    </div>

    @if ($this->loteEnAjuste)
        @php($loteAjuste = $this->loteEnAjuste)
        <form wire:submit="guardarAjuste" class="mb-4 rounded-2xl border border-cosechar-olive/40 bg-white p-4">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-cosechar-ink">{{ $ajusteTipo === 'entrada' ? 'Aumentar lote' : 'Reducir lote' }}</h2>
                    <p class="text-sm text-cosechar-muted">
                        <x-producto-etiqueta :producto="$loteAjuste->producto" /> · Lote <span class="font-medium text-cosechar-ink">{{ $loteAjuste->etiqueta }}</span> · Disponible: <span class="font-medium text-cosechar-ink">{{ $loteAjuste->cantidad }}</span>
                    </p>
                </div>
                <button type="button" wire:click="cancelarAjuste" class="text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">Cancelar</button>
            </div>

            <x-leyenda-obligatorios />

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="ajusteCantidad" class="mb-1 block text-xs font-medium text-cosechar-muted">{{ $ajusteTipo === 'entrada' ? 'Cantidad a sumar' : 'Cantidad a restar' }} <x-obligatorio /></label>
                    <input type="number" min="1" id="ajusteCantidad" wire:model="ajusteCantidad" placeholder="Ej: 5" class="{{ $campo }}">
                    @error('ajusteCantidad')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ajusteMotivo" class="mb-1 block text-xs font-medium text-cosechar-muted">Razón <x-obligatorio /></label>
                    <select id="ajusteMotivo" wire:model="ajusteMotivo" class="{{ $campo }}">
                        <option value="">Seleccione una razón</option>
                        @foreach ($this->razones as $clave => $etiqueta)
                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @error('ajusteMotivo')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardarAjuste" class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50">
                        <span wire:loading.remove wire:target="guardarAjuste">Guardar ajuste</span>
                        <span wire:loading wire:target="guardarAjuste">Guardando...</span>
                    </button>
                </div>
            </div>
        </form>
    @endif

    @include('livewire.partials.filtros-historial', [
        'placeholder' => 'Producto, código, marca o N.º de lote...',
        'estados' => \App\Models\Lote::ESTADOS,
        'total' => $this->lotes->total(),
    ])

    <div class="overflow-x-auto rounded-2xl border border-cosechar-border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-cosechar-cream text-left text-xs uppercase text-cosechar-muted">
                <tr>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">N.º de lote</th>
                    <th class="px-4 py-3">Vencimiento</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    @if ($conCosto)
                        <th class="px-4 py-3 text-right">Costo</th>
                    @endif
                    <th class="px-4 py-3">Creado</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Ajustar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-cosechar-border">
                @forelse ($this->lotes as $lote)
                    <tr wire:key="lote-{{ $lote->id }}" class="{{ $lote->estado === 'agotado' ? 'bg-cosechar-cream/60 text-cosechar-muted' : '' }}">
                        <td class="px-4 py-3 font-medium text-cosechar-ink"><x-producto-etiqueta :producto="$lote->producto" /></td>
                        <td class="px-4 py-3 text-cosechar-ink">{{ $lote->etiqueta }}</td>
                        <td class="px-4 py-3 text-cosechar-muted">{{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-cosechar-ink">{{ $lote->cantidad }}</td>
                        @if ($conCosto)
                            <td class="px-4 py-3 text-right text-cosechar-muted">{{ $lote->costo_unitario !== null ? 'S/ '.number_format($lote->costo_unitario, 2) : '—' }}</td>
                        @endif
                        <td class="whitespace-nowrap px-4 py-3 text-cosechar-muted">{{ $lote->created_at?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $insignias[$lote->estado] }}">{{ \App\Models\Lote::ESTADOS[$lote->estado] }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <button type="button" wire:click="iniciarAjuste({{ $lote->id }}, 'salida')" aria-label="Reducir lote {{ $lote->etiqueta }}" title="Reducir" class="rounded-md border border-cosechar-border px-2.5 py-1 text-sm font-medium text-cosechar-danger hover:bg-cosechar-cream">−</button>
                            <button type="button" wire:click="iniciarAjuste({{ $lote->id }}, 'entrada')" aria-label="Aumentar lote {{ $lote->etiqueta }}" title="Aumentar" class="rounded-md border border-cosechar-border px-2.5 py-1 text-sm font-medium text-cosechar-olive hover:bg-cosechar-cream">+</button>
                            <button type="button" wire:click="verDetalle({{ $lote->id }})" class="ml-2 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">{{ $detalleId === $lote->id ? 'Ocultar' : 'Detalle' }}</button>
                        </td>
                    </tr>

                    @if ($detalleId === $lote->id)
                        <tr wire:key="detalle-{{ $lote->id }}" class="bg-cosechar-cream/40">
                            <td colspan="{{ $conCosto ? 8 : 7 }}" class="px-4 py-4">
                                @php($totales = $this->totalesDelLote)
                                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-xl border border-cosechar-border bg-white p-3">
                                        <p class="text-xs font-medium text-cosechar-muted">Vendido</p>
                                        <p class="mt-1 text-lg font-semibold text-cosechar-ink">{{ $totales['vendido'] }} <span class="text-xs font-normal text-cosechar-muted">unidades en ventas</span></p>
                                    </div>
                                    <div class="rounded-xl border border-cosechar-border bg-white p-3">
                                        <p class="text-xs font-medium text-cosechar-muted">Ajustes (merma, dañado, conteo)</p>
                                        <p class="mt-1 text-lg font-semibold text-cosechar-ink">
                                            <span class="text-cosechar-danger">−{{ $totales['ajustes_menos'] }}</span>
                                            <span class="text-cosechar-olive">+{{ $totales['ajustes_mas'] }}</span>
                                            <span class="text-xs font-normal text-cosechar-muted">no cuentan como venta</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="grid gap-6 lg:grid-cols-2">
                                    <div>
                                        <h3 class="mb-2 text-xs font-semibold uppercase text-cosechar-muted">Movimientos del lote</h3>
                                        @forelse ($this->movimientosDelLote as $movimiento)
                                            <p class="text-sm text-cosechar-ink">
                                                {{ $movimiento->fecha->format('d/m/Y H:i') }} ·
                                                <span class="{{ $movimiento->tipo === 'entrada' ? 'text-cosechar-olive' : 'text-cosechar-danger' }}">{{ $movimiento->tipo === 'entrada' ? '+' : '−' }}{{ $movimiento->cantidad }}</span> ·
                                                {{ $movimiento->etiquetaMotivo }}
                                                <span class="text-cosechar-muted">· {{ $movimiento->responsable?->name }}</span>
                                            </p>
                                        @empty
                                            <p class="text-sm text-cosechar-muted">Este lote todavía no tiene movimientos.</p>
                                        @endforelse
                                    </div>
                                    <div>
                                        <h3 class="mb-2 text-xs font-semibold uppercase text-cosechar-muted">Ventas que salieron de este lote</h3>
                                        @forelse ($this->ventasDelLote as $consumo)
                                            <p class="text-sm text-cosechar-ink">
                                                {{ $consumo->detalle->venta->fecha->format('d/m/Y') }} · {{ $consumo->detalle->venta->cliente_nombre }} ·
                                                <span class="text-cosechar-danger">−{{ $consumo->cantidad }}</span>
                                                @if ($consumo->detalle->venta->anulada_at)
                                                    <span class="text-cosechar-muted">(anulada)</span>
                                                @endif
                                            </p>
                                        @empty
                                            <p class="text-sm text-cosechar-muted">Todavía no se vendió nada de este lote.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ $conCosto ? 8 : 7 }}" class="px-4 py-6 text-center text-cosechar-muted">{{ $this->hayFiltros() ? 'No se encontraron lotes con esos filtros.' : 'Todavía no hay lotes. Se crean al registrar una compra.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('livewire.partials.paginacion', ['paginador' => $this->lotes])
</div>
