<div class="mx-auto max-w-4xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Registrar compra</h1>
        <a href="{{ route('compras.historial') }}" class="rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
            Ver historial de compras
        </a>
    </div>

    @if ($creado)
        <div class="mb-4 flex items-center justify-between rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            <span>Compra registrada correctamente. El stock ya fue actualizado.</span>
            <span class="flex gap-4">
                @if ($compraId)
                    <a href="{{ route('compras.comprobante', $compraId) }}" target="_blank" class="font-medium underline hover:text-green-900">Imprimir comprobante</a>
                @endif
                <a href="{{ route('compras.historial') }}" class="font-medium underline hover:text-green-900">Ver historial</a>
            </span>
        </div>
    @endif

    <x-leyenda-obligatorios />

    <form wire:submit="guardar" class="flex flex-col gap-6">
        <div class="rounded-xl border border-cosechar-border bg-white p-4">
            <label for="buscar_proveedor" class="mb-1 block text-sm font-medium text-cosechar-ink">Buscar proveedor registrado <span class="font-normal text-cosechar-muted">(opcional)</span></label>

            @if ($this->proveedorSeleccionado)
                <div class="flex items-center justify-between gap-2 rounded-md border border-cosechar-border bg-cosechar-cream px-3 py-2 text-sm">
                    <span class="min-w-0 truncate text-cosechar-ink">
                        <span class="mr-2 rounded-full bg-cosechar-olive/15 px-2 py-0.5 text-[11px] font-semibold text-cosechar-olive">Proveedor registrado</span>
                        {{ $this->proveedorSeleccionado->nombre }}@if ($this->proveedorSeleccionado->ruc) · RUC {{ $this->proveedorSeleccionado->ruc }}@endif
                    </span>
                    <button type="button" wire:click="limpiarProveedor" class="shrink-0 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">Cambiar</button>
                </div>
            @else
                <input
                    type="text"
                    id="buscar_proveedor"
                    wire:model.live.debounce.250ms="buscarProveedor"
                    wire:keydown.enter.prevent="elegirPrimerProveedor"
                    placeholder="Escriba el nombre o el RUC del proveedor"
                    autocomplete="off"
                    class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
                >

                @if (trim($buscarProveedor) !== '')
                    <ul class="mt-1 max-h-60 overflow-auto rounded-md border border-cosechar-border bg-white shadow-sm">
                        @forelse ($this->proveedoresSugeridos as $sugerido)
                            <li>
                                <button
                                    type="button"
                                    wire:click="elegirProveedor({{ $sugerido->id }})"
                                    class="block w-full px-3 py-2 text-left text-sm text-cosechar-ink hover:bg-cosechar-cream"
                                >
                                    {{ $sugerido->nombre }}
                                    @if ($sugerido->ruc)
                                        <span class="text-xs text-cosechar-muted">· RUC {{ $sugerido->ruc }}</span>
                                    @endif
                                </button>
                            </li>
                        @empty
                            <li class="px-3 py-2 text-sm text-cosechar-muted">No se encontró ningún proveedor registrado. Puede escribir sus datos abajo.</li>
                        @endforelse
                    </ul>
                @endif
            @endif
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="proveedor_nombre" class="mb-1 block text-sm font-medium text-cosechar-ink">Nombre del proveedor <x-obligatorio /></label>
                <input type="text" id="proveedor_nombre" wire:model.blur="proveedor_nombre" autocomplete="off" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring">
                @error('proveedor_nombre')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="proveedor_ruc" class="mb-1 block text-sm font-medium text-cosechar-ink">RUC <span class="font-normal text-cosechar-muted">(opcional)</span></label>
                <input type="text" id="proveedor_ruc" wire:model.blur="proveedor_ruc" inputmode="numeric" autocomplete="off" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring">
                @error('proveedor_ruc')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="proveedor_telefono" class="mb-1 block text-sm font-medium text-cosechar-ink">Teléfono <span class="font-normal text-cosechar-muted">(opcional)</span></label>
                <input type="text" id="proveedor_telefono" wire:model.blur="proveedor_telefono" autocomplete="off" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring">
                @error('proveedor_telefono')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        @unless ($proveedor_id)
            <p class="-mt-3 text-xs text-cosechar-muted">Si el proveedor no está registrado, escriba aquí sus datos: se registra solo al guardar la compra.</p>
        @endunless

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @unless ($this->almacenes->count() === 1)
                <div>
                    <label for="almacen_id" class="mb-1 block text-sm font-medium text-cosechar-ink">Almacén destino <x-obligatorio /></label>
                    <select id="almacen_id" wire:model.blur="almacen_id" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring">
                        <option value="">Seleccione un almacén</option>
                        @foreach ($this->almacenes as $almacen)
                            <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                        @endforeach
                    </select>
                    @error('almacen_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endunless

            <div>
                <label for="fecha" class="mb-1 block text-sm font-medium text-cosechar-ink">Fecha <x-obligatorio /></label>
                <input type="date" id="fecha" wire:model.blur="fecha" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring">
                @error('fecha')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="numero_comprobante" class="mb-1 block text-sm font-medium text-cosechar-ink">N.º de comprobante <span class="font-normal text-cosechar-muted">(opcional)</span></label>
                <input type="text" id="numero_comprobante" wire:model.blur="numero_comprobante" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring">
            </div>
        </div>
        <div class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-cosechar-ink">Productos comprados</h2>

            @foreach ($lineas as $indice => $linea)
                <div wire:key="linea-{{ $indice }}" class="grid grid-cols-1 gap-3 rounded-xl border border-cosechar-border p-4 sm:grid-cols-12 sm:items-start">
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-cosechar-muted">Producto <x-obligatorio /></label>
                        @if ($productoLinea = $this->productoDeLinea($indice))
                            <div class="flex items-center justify-between gap-2 rounded-md border border-cosechar-border bg-white px-2 py-1.5 text-sm">
                                <span class="min-w-0 truncate text-cosechar-ink" title="{{ $productoLinea->codigo }} — {{ $productoLinea->nombre }}"><x-producto-etiqueta :producto="$productoLinea" /></span>
                                <button type="button" wire:click="limpiarProductoLinea({{ $indice }})" class="shrink-0 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">Cambiar</button>
                            </div>
                        @else
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="busquedaProducto.{{ $indice }}"
                                wire:keydown.enter.prevent="elegirPrimeroLinea({{ $indice }})"
                                placeholder="Escriba el nombre o el código"
                                autocomplete="off"
                                class="w-full rounded-md border border-cosechar-border px-2 py-1.5 text-sm"
                            >

                            @if (trim($busquedaProducto[$indice] ?? '') !== '')
                                <ul class="mt-1 max-h-56 overflow-auto rounded-md border border-cosechar-border bg-white shadow-sm">
                                    @forelse ($this->productosSugeridos($indice) as $sugerido)
                                        <li>
                                            <button
                                                type="button"
                                                wire:click="elegirProductoLinea({{ $indice }}, {{ $sugerido->id }})"
                                                class="block w-full px-2 py-1.5 text-left text-sm text-cosechar-ink hover:bg-cosechar-cream"
                                            >
                                                <x-producto-etiqueta :producto="$sugerido" />
                                            </button>
                                        </li>
                                    @empty
                                        <li class="px-2 py-1.5 text-sm text-cosechar-muted">No se encontró ningún producto con ese nombre o código.</li>
                                    @endforelse
                                </ul>
                            @endif
                        @endif
                        @error("lineas.{$indice}.producto_id")
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-cosechar-muted">N.º de lote <x-obligatorio /></label>
                        <input type="text" wire:model.blur="lineas.{{ $indice }}.numero_lote" class="w-full rounded-md border border-cosechar-border px-2 py-1.5 text-sm">
                        @error("lineas.{$indice}.numero_lote")
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-cosechar-muted">Vencimiento @if ($this->productoDeLinea($indice)?->maneja_vencimiento)<x-obligatorio />@else<span class="font-normal text-cosechar-muted">(si aplica)</span>@endif</label>
                        <input type="date" wire:model.blur="lineas.{{ $indice }}.fecha_vencimiento" class="w-full rounded-md border border-cosechar-border px-2 py-1.5 text-sm">
                        @error("lineas.{$indice}.fecha_vencimiento")
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-cosechar-muted">Cantidad <x-obligatorio /></label>
                        <input type="number" min="1" wire:model.blur="lineas.{{ $indice }}.cantidad" class="w-full rounded-md border border-cosechar-border px-2 py-1.5 text-sm">
                        @error("lineas.{$indice}.cantidad")
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-cosechar-muted">Costo unitario (S/) <x-obligatorio /></label>
                        <input type="number" step="0.01" min="0" wire:model.blur="lineas.{{ $indice }}.costo_unitario" class="w-full rounded-md border border-cosechar-border px-2 py-1.5 text-sm">
                        @error("lineas.{$indice}.costo_unitario")
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-end justify-end sm:col-span-1">
                        <button
                            type="button"
                            wire:click="quitarLinea({{ $indice }})"
                            @disabled(count($lineas) <= 1)
                            class="rounded-md border border-cosechar-border px-2 py-1.5 text-xs text-cosechar-muted hover:bg-cosechar-cream disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Quitar
                        </button>
                    </div>
                </div>
            @endforeach

            <button
                type="button"
                wire:click="agregarLinea"
                class="self-start rounded-md border border-dashed border-cosechar-border px-3 py-2 text-sm font-medium text-cosechar-olive hover:bg-cosechar-cream"
            >
                + Agregar producto
            </button>
        </div>

        <div class="flex items-center justify-between border-t border-cosechar-border pt-4">
            <span class="text-sm font-medium text-cosechar-muted">Total</span>
            <span class="font-sans text-xl font-semibold text-cosechar-ink">S/ {{ number_format($this->total, 2) }}</span>
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="self-start rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">Guardar compra</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
