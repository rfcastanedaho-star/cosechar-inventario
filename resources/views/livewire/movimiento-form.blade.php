@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
@endphp

<div class="mx-auto max-w-xl p-8">
    <h1 class="mb-6 text-lg font-semibold text-cosechar-ink">Registrar movimiento de stock</h1>

    @if ($creado)
        <div class="mb-4 flex items-center justify-between rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            <span>Movimiento registrado correctamente.</span>
            @if ($ultimoLoteId)
                <a
                    href="{{ route('lotes.etiqueta', $ultimoLoteId) }}"
                    target="_blank"
                    class="font-medium underline hover:text-green-900"
                >
                    Ver etiqueta QR
                </a>
            @endif
        </div>
    @endif

    <div class="mb-6 flex gap-2 rounded-xl bg-cosechar-border/60 p-1 text-sm font-medium">
        <button
            type="button"
            wire:click="$set('tipo', 'entrada')"
            class="flex-1 rounded-lg py-2 {{ $tipo === 'entrada' ? 'bg-white text-cosechar-ink shadow-sm' : 'text-cosechar-muted' }}"
        >
            Entrada
        </button>
        <button
            type="button"
            wire:click="$set('tipo', 'salida')"
            class="flex-1 rounded-lg py-2 {{ $tipo === 'salida' ? 'bg-white text-cosechar-ink shadow-sm' : 'text-cosechar-muted' }}"
        >
            Salida
        </button>
    </div>

    <form wire:submit="guardar" class="flex flex-col gap-4">
        <div>
            <label for="producto_id" class="mb-1 block text-sm font-medium text-cosechar-ink">Producto</label>
            <select id="producto_id" wire:model.live="producto_id" class="{{ $campo }}">
                <option value="">Seleccione un producto</option>
                @foreach ($this->productos as $producto)
                    <option value="{{ $producto->id }}">{{ $producto->codigo }} — {{ $producto->nombre }}</option>
                @endforeach
            </select>
            @error('producto_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="almacen_id" class="mb-1 block text-sm font-medium text-cosechar-ink">
                {{ $tipo === 'entrada' ? 'Almacén de destino' : 'Almacén de origen' }}
            </label>
            <select id="almacen_id" wire:model.live="almacen_id" class="{{ $campo }}">
                <option value="">Seleccione un almacén</option>
                @foreach ($this->almacenes as $almacen)
                    <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                @endforeach
            </select>
            @error('almacen_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($producto_id)
                <p class="mt-1 text-sm text-cosechar-muted">
                    Stock actual {{ $almacen_id ? 'en este almacén' : '(todos los almacenes)' }}: {{ $this->stockActual }}
                </p>
            @endif
        </div>

        @if ($tipo === 'entrada')
            <div>
                <label for="numero_lote" class="mb-1 block text-sm font-medium text-cosechar-ink">Número de lote</label>
                <input type="text" id="numero_lote" wire:model.live="numero_lote" class="{{ $campo }}">
                @error('numero_lote')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="fecha_ingreso" class="mb-1 block text-sm font-medium text-cosechar-ink">Fecha de ingreso</label>
                    <input type="date" id="fecha_ingreso" wire:model.blur="fecha_ingreso" class="{{ $campo }}">
                    @error('fecha_ingreso')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="fecha_vencimiento" class="mb-1 block text-sm font-medium text-cosechar-ink">
                        Fecha de vencimiento
                        @if ($this->productoSeleccionado && ! $this->productoSeleccionado->maneja_vencimiento)
                            <span class="font-normal text-cosechar-muted">(no aplica)</span>
                        @endif
                    </label>
                    <input
                        type="date"
                        id="fecha_vencimiento"
                        wire:model.blur="fecha_vencimiento"
                        @disabled($this->productoSeleccionado && ! $this->productoSeleccionado->maneja_vencimiento)
                        class="{{ $campo }} disabled:bg-cosechar-cream"
                    >
                    @error('fecha_vencimiento')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @else
            <div>
                <label for="motivo" class="mb-1 block text-sm font-medium text-cosechar-ink">Motivo de la salida</label>
                <select id="motivo" wire:model.blur="motivo" class="{{ $campo }}">
                    <option value="">Seleccione un motivo</option>
                    <option value="venta">Venta</option>
                    <option value="merma">Merma</option>
                    <option value="producto_danado">Producto dañado</option>
                    <option value="ajuste_inventario">Ajuste de inventario</option>
                    <option value="transferencia">Transferencia</option>
                </select>
                @error('motivo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div>
            <label for="cantidad" class="mb-1 block text-sm font-medium text-cosechar-ink">Cantidad</label>
            <input type="number" min="1" id="cantidad" wire:model.blur="cantidad" class="{{ $campo }}">
            @error('cantidad')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="guardar"
            class="rounded-md bg-cosechar-forest px-4 py-2 text-sm font-medium text-white hover:bg-cosechar-forest/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="guardar">Guardar movimiento</span>
            <span wire:loading wire:target="guardar">Guardando...</span>
        </button>
    </form>
</div>
