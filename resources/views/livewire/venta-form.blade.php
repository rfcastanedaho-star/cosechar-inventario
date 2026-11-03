<div class="mx-auto max-w-4xl p-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-cosechar-ink">Registrar venta</h1>
        <a href="{{ route('ventas.historial') }}" class="rounded-md border border-cosechar-border bg-white px-4 py-2 text-sm font-medium text-cosechar-ink hover:bg-cosechar-cream">
            Ver historial de ventas
        </a>
    </div>

    @if ($creado)
        <div class="mb-4 flex items-center justify-between rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            <span>Venta registrada correctamente. El stock ya fue actualizado.</span>
            <span class="flex gap-4">
                @if ($ventaId)
                    <a href="{{ route('ventas.comprobante', $ventaId) }}" target="_blank" class="font-medium underline hover:text-green-900">Imprimir comprobante</a>
                @endif
                <a href="{{ route('ventas.historial') }}" class="font-medium underline hover:text-green-900">Ver historial</a>
            </span>
        </div>
    @endif

    @error('lineas')
        <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <x-leyenda-obligatorios />

    <form wire:submit="guardar" class="flex flex-col gap-6">
        <div class="rounded-xl border border-cosechar-border bg-white p-4">
            <x-selector-registrado
                id="cliente"
                etiqueta="Cliente"
                prop-buscar="buscarCliente"
                :texto-buscar="$buscarCliente"
                prop-abierto="listaClientes"
                prop-error="cliente_id"
                :seleccionado="$this->clienteSeleccionado"
                :sugeridos="$this->clientesSugeridos"
                elegir="elegirCliente"
                limpiar="limpiarCliente"
                alternar="alternarListaClientes"
                placeholder="Escriba las iniciales del nombre, el DNI/RUC o el teléfono, o abra la lista"
                campo-doc="documento"
                etiqueta-doc="Doc."
                :nueva-ruta="route('clientes.nuevo', ['desde' => 'venta'])"
                nuevo-texto="Registrar cliente nuevo"
                vacio="No hay ningún cliente registrado que coincida."
            />
            @unless ($cliente_id)
                <p class="mt-2 text-xs text-cosechar-muted">Solo se pueden elegir clientes ya registrados. ¿No está? <a href="{{ route('clientes.nuevo', ['desde' => 'venta']) }}" class="font-medium text-cosechar-forest underline hover:text-cosechar-ink">Regístrelo en Clientes</a>.</p>
            @endunless
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @unless ($this->almacenes->count() === 1)
                <div>
                    <label for="almacen_id" class="mb-1 block text-sm font-medium text-cosechar-ink">Almacén de origen <x-obligatorio /></label>
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

        </div>

        <div class="grid grid-cols-1 gap-4 sm:max-w-md sm:grid-cols-2">
            <div>
                <label for="fecha" class="mb-1 block text-sm font-medium text-cosechar-ink">Fecha <x-obligatorio /></label>
                <input type="date" id="fecha" wire:model.blur="fecha" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring">
                @error('fecha')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="numero_comprobante" class="mb-1 block text-sm font-medium text-cosechar-ink">N.º de comprobante (opcional)</label>
                <input type="text" id="numero_comprobante" wire:model.blur="numero_comprobante" class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring">
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-cosechar-ink">Productos vendidos</h2>

            @foreach ($lineas as $indice => $linea)
                <div wire:key="linea-{{ $indice }}" class="grid grid-cols-1 gap-3 rounded-xl border border-cosechar-border p-4 sm:grid-cols-12 sm:items-start">
                    <div class="sm:col-span-6">
                        <label class="mb-1 block text-xs font-medium text-cosechar-muted">Producto <x-obligatorio /></label>
                        @if ($productoLinea = $this->productoDeLinea($indice))
                            <div class="flex items-center justify-between gap-2 rounded-md border border-cosechar-border bg-white px-2 py-1.5 text-sm">
                                <span class="min-w-0 truncate text-cosechar-ink" title="{{ $productoLinea->codigo }} — {{ $productoLinea->nombre }}"><x-producto-etiqueta :producto="$productoLinea" /></span>
                                <button type="button" wire:click="limpiarProductoLinea({{ $indice }})" class="shrink-0 text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">Cambiar</button>
                            </div>
                        @else
                            <x-selector-producto
                                buscar="busquedaProducto.{{ $indice }}"
                                :texto="$busquedaProducto[$indice] ?? ''"
                                ruta-abierto="listaProducto.{{ $indice }}"
                                alternar="alternarListaProducto({{ $indice }})"
                                enter="elegirPrimeroLinea({{ $indice }})"
                                elegir="elegirProductoLinea({{ $indice }}, "
                                :sugeridos="$this->productosSugeridos($indice)"
                            />
                        @endif
                        @error("lineas.{$indice}.producto_id")
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

                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-cosechar-muted">{{ $linea['por_mayor'] ? 'Precio por mayor (S/)' : 'Precio unitario (S/)' }} <x-obligatorio /></label>
                        <input type="number" step="0.01" min="0" wire:model.blur="lineas.{{ $indice }}.precio_unitario" class="w-full rounded-md border border-cosechar-border px-2 py-1.5 text-sm">
                        @error("lineas.{$indice}.precio_unitario")
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <label class="mt-2 flex items-center gap-2 text-xs text-cosechar-ink">
                            <input type="checkbox" wire:model.live="lineas.{{ $indice }}.por_mayor" class="rounded border border-cosechar-border">
                            Venta por mayor
                        </label>
                        @if ($linea['por_mayor'] && ($productoPrecio = $this->productoDeLinea($indice)))
                            <p class="mt-1 text-xs text-cosechar-muted">Precio normal: S/ {{ number_format($productoPrecio->precio, 2) }}</p>
                        @endif
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

        <div class="rounded-xl border border-cosechar-border bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-cosechar-ink">Método de pago <x-obligatorio /></h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach (\App\Models\Venta::METODOS_PAGO as $clave => $texto)
                    <label
                        wire:key="pago-{{ $clave }}"
                        class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 px-4 py-3 text-sm font-semibold transition {{ $metodo_pago === $clave ? 'border-cosechar-forest bg-cosechar-forest/10 text-cosechar-forest' : 'border-cosechar-border text-cosechar-muted hover:bg-cosechar-cream' }}"
                    >
                        <input type="radio" wire:model.live="metodo_pago" value="{{ $clave }}" class="sr-only">
                        <span aria-hidden="true">{{ ['efectivo' => '💵', 'tarjeta' => '💳', 'transferencia' => '🏦'][$clave] }}</span>
                        {{ $texto }}
                    </label>
                @endforeach
            </div>
            @error('metodo_pago')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($metodo_pago === \App\Models\Venta::EFECTIVO)
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="monto_recibido" class="mb-1 block text-sm font-medium text-cosechar-ink">Monto recibido (S/) <span class="font-normal text-cosechar-muted">(opcional)</span></label>
                        <div class="flex gap-2">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="monto_recibido"
                                wire:model.live.debounce.300ms="monto_recibido"
                                placeholder="Vacío = paga el total exacto"
                                class="w-full rounded-md border border-cosechar-border px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring"
                            >
                            <button type="button" wire:click="montoExacto" class="shrink-0 rounded-md border border-cosechar-border px-3 py-2 text-xs font-medium text-cosechar-ink hover:bg-cosechar-cream">Exacto</button>
                        </div>
                        @error('monto_recibido')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between rounded-lg border px-4 py-3 {{ $this->faltante !== null ? 'border-red-300 bg-red-50' : 'border-cosechar-olive/40 bg-cosechar-cream' }}">
                        @if ($this->faltante !== null)
                            <span class="text-sm font-medium text-red-700">Falta dinero</span>
                            <span class="font-sans text-2xl font-semibold text-red-700">S/ {{ number_format($this->faltante, 2) }}</span>
                        @else
                            <span class="text-sm font-medium text-cosechar-muted">Vuelto a entregar</span>
                            <span class="font-sans text-2xl font-semibold text-cosechar-ink">S/ {{ number_format($this->vuelto ?? 0, 2) }}</span>
                        @endif
                    </div>
                </div>
            @else
                <p class="mt-3 text-sm text-cosechar-muted">Se cobra el total exacto: S/ {{ number_format($this->total, 2) }}. No hay vuelto.</p>
            @endif
        </div>
        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="self-start rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">Guardar venta</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
