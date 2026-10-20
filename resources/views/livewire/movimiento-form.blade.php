@php
    $campo = 'w-full rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm focus:border-cosechar-olive focus:ring';
@endphp

<div class="mx-auto max-w-xl p-8">
    <h1 class="mb-6 text-lg font-semibold text-cosechar-ink">Registrar movimiento de stock</h1>

    @if ($creado)
        <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-800">
            Movimiento registrado correctamente.
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
        <button
            type="button"
            wire:click="$set('tipo', 'transferencia')"
            class="flex-1 rounded-lg py-2 {{ $tipo === 'transferencia' ? 'bg-white text-cosechar-ink shadow-sm' : 'text-cosechar-muted' }}"
        >
            Transferencia
        </button>
    </div>

    <form wire:submit="guardar" class="flex flex-col gap-4">
        <div>
            <label for="buscar_producto" class="mb-1 block text-sm font-medium text-cosechar-ink">Producto</label>

            @if ($this->productoSeleccionado)
                <div class="flex items-center justify-between gap-3 rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm">
                    <span class="text-cosechar-ink">{{ $this->productoSeleccionado->codigo }} — {{ $this->productoSeleccionado->nombre }}</span>
                    <button type="button" wire:click="limpiarProducto" class="text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">
                        Cambiar
                    </button>
                </div>
            @else
                <input
                    type="text"
                    id="buscar_producto"
                    wire:model.live.debounce.250ms="buscarProducto"
                    wire:keydown.enter.prevent="elegirPrimero"
                    placeholder="Escriba el nombre o el código del producto"
                    autocomplete="off"
                    class="{{ $campo }}"
                >

                @if (trim($buscarProducto) !== '')
                    <ul class="mt-1 max-h-64 overflow-auto rounded-md border border-cosechar-border bg-white shadow-sm">
                        @forelse ($this->productosSugeridos as $sugerido)
                            <li>
                                <button
                                    type="button"
                                    wire:click="elegirProducto({{ $sugerido->id }})"
                                    class="block w-full px-3 py-2 text-left text-sm text-cosechar-ink hover:bg-cosechar-cream"
                                >
                                    {{ $sugerido->codigo }} — {{ $sugerido->nombre }}
                                </button>
                            </li>
                        @empty
                            <li class="px-3 py-2 text-sm text-cosechar-muted">No se encontró ningún producto con ese nombre o código.</li>
                        @endforelse
                    </ul>
                @endif
            @endif
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

        @if ($tipo === 'transferencia')
            <div>
                <label for="almacen_destino_id" class="mb-1 block text-sm font-medium text-cosechar-ink">Almacén de destino</label>
                <select id="almacen_destino_id" wire:model.live="almacen_destino_id" class="{{ $campo }}">
                    <option value="">Seleccione un almacén</option>
                    @foreach ($this->almacenes as $almacen)
                        @if ($almacen->id !== $almacen_id)
                            <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                        @endif
                    @endforeach
                </select>
                @error('almacen_destino_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-cosechar-muted">
                    Sale del almacén de origen (por vencimiento más próximo) y entra al de destino con el mismo lote.
                </p>
            </div>
        @elseif ($tipo === 'entrada')
            <div>
                <label for="buscar_lote" class="mb-1 block text-sm font-medium text-cosechar-ink">Lote</label>

                @if ($this->loteSeleccionado)
                    <div class="flex items-center justify-between gap-3 rounded-md border border-cosechar-border bg-white px-3 py-2 text-sm shadow-sm">
                        <span class="text-cosechar-ink">
                            {{ $this->loteSeleccionado->numero_lote }}
                            · {{ $this->loteSeleccionado->fecha_vencimiento ? 'vence '.$this->loteSeleccionado->fecha_vencimiento->format('d/m/Y') : 'sin vencimiento' }}
                            · stock {{ $this->loteSeleccionado->cantidad }}
                        </span>
                        <button type="button" wire:click="limpiarLote" class="text-xs font-medium text-cosechar-muted underline hover:text-cosechar-ink">
                            Cambiar
                        </button>
                    </div>
                @else
                    <input
                        type="text"
                        id="buscar_lote"
                        wire:model.live.debounce.250ms="buscarLote"
                        wire:keydown.enter.prevent="elegirPrimerLote"
                        placeholder="Escriba el número de lote"
                        autocomplete="off"
                        @disabled(! $this->hayLotes)
                        class="{{ $campo }} disabled:bg-cosechar-cream"
                    >

                    @if (trim($buscarLote) !== '')
                        <ul class="mt-1 max-h-64 overflow-auto rounded-md border border-cosechar-border bg-white shadow-sm">
                            @forelse ($this->lotesSugeridos as $sugerido)
                                <li>
                                    <button
                                        type="button"
                                        wire:click="elegirLote({{ $sugerido->id }})"
                                        class="block w-full px-3 py-2 text-left text-sm text-cosechar-ink hover:bg-cosechar-cream"
                                    >
                                        {{ $sugerido->numero_lote }}
                                        · {{ $sugerido->fecha_vencimiento ? 'vence '.$sugerido->fecha_vencimiento->format('d/m/Y') : 'sin vencimiento' }}
                                        · stock {{ $sugerido->cantidad }}
                                    </button>
                                </li>
                            @empty
                                <li class="px-3 py-2 text-sm text-cosechar-muted">No se encontró ningún lote con ese número.</li>
                            @endforelse
                        </ul>
                    @endif
                @endif
                @error('lote_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                @if (! $producto_id || ! $almacen_id)
                    <p class="mt-1 text-sm text-cosechar-muted">Elija el producto y el almacén para buscar sus lotes.</p>
                @elseif (! $this->hayLotes)
                    <p class="mt-1 text-sm text-amber-700">
                        Este producto no tiene lotes en este almacén. Regístrelo primero en Compras.
                    </p>
                @endif
            </div>

            <div>
                <span class="mb-1 block text-sm font-medium text-cosechar-ink">Motivo de la entrada</span>
                <p class="rounded-md border border-cosechar-border bg-cosechar-cream px-3 py-2 text-sm text-cosechar-ink">
                    Ajuste por conteo (sobrante)
                </p>
                <p class="mt-1 text-sm text-cosechar-muted">
                    Solo suma al lote lo que el conteo físico encontró de más. La mercadería nueva se registra en Compras.
                </p>
            </div>
        @else
            <div>
                <label for="motivo" class="mb-1 block text-sm font-medium text-cosechar-ink">Motivo de la salida</label>
                <select id="motivo" wire:model.blur="motivo" class="{{ $campo }}">
                    <option value="">Seleccione un motivo</option>
                    <option value="merma">Merma</option>
                    <option value="producto_danado">Producto dañado</option>
                    <option value="ajuste_inventario">Ajuste por conteo (faltante)</option>
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
