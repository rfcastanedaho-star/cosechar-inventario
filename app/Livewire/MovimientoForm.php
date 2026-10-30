<?php

namespace App\Livewire;

use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Repositories\LoteRepository;
use App\Repositories\ProductoRepository;
use App\Services\StockMovimientoService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MovimientoForm extends Component
{
    /**
     * Motivo de toda entrada manual: sumar al lote lo que el conteo físico
     * encontró de más. Los lotes nuevos nacen en Compras (con proveedor y
     * costo), nunca aquí.
     */
    public const MOTIVO_ENTRADA = 'ajuste_inventario';

    /** Motivos de una salida manual (las ventas tienen su propio flujo). */
    public const MOTIVOS_SALIDA = ['merma', 'producto_danado', 'ajuste_inventario'];

    public string $tipo = 'entrada';

    public ?int $producto_id = null;

    /** Texto del buscador de productos (nombre o código). */
    public string $buscarProducto = '';

    public ?int $almacen_id = null;

    public ?int $lote_id = null;

    /** Texto del buscador de lotes (número de lote). */
    public string $buscarLote = '';

    public int $cantidad = 0;

    public string $motivo = '';

    public bool $creado = false;

    /** Con un solo almacén activo no hay nada que elegir: se usa ese. */
    public function mount(): void
    {
        $this->almacen_id = Almacen::unicoActivo()?->id;
    }

    #[Computed]
    public function productoSeleccionado(): ?Producto
    {
        return $this->producto_id ? Producto::find($this->producto_id) : null;
    }

    /** Sugerencias del buscador mientras no haya un producto elegido. */
    #[Computed]
    public function productosSugeridos(): Collection
    {
        return $this->producto_id
            ? new Collection
            : app(ProductoRepository::class)->buscar($this->buscarProducto);
    }

    public function elegirProducto(int $id): void
    {
        if (! Producto::whereKey($id)->where('activo', true)->exists()) {
            return;
        }

        $this->producto_id = $id;
        $this->buscarProducto = '';
        $this->lote_id = null;
        $this->buscarLote = '';
        $this->creado = false;
        $this->resetErrorBag(['producto_id', 'lote_id']);
    }

    /** Enter en el buscador: elige la primera sugerencia. */
    public function elegirPrimero(): void
    {
        $primero = $this->productosSugeridos->first();

        if ($primero) {
            $this->elegirProducto($primero->id);
        }
    }

    public function limpiarProducto(): void
    {
        $this->producto_id = null;
        $this->buscarProducto = '';
        $this->lote_id = null;
        $this->buscarLote = '';
        $this->creado = false;
    }

    #[Computed]
    public function almacenes(): Collection
    {
        return Almacen::where('activo', true)->orderBy('nombre')->get();
    }

    /** ¿El producto tiene algún lote en el almacén elegido? Si no, hay que registrarlo en Compras. */
    #[Computed]
    public function hayLotes(): bool
    {
        return $this->producto_id && $this->almacen_id
            && Lote::where('producto_id', $this->producto_id)->where('almacen_id', $this->almacen_id)->exists();
    }

    #[Computed]
    public function loteSeleccionado(): ?Lote
    {
        return $this->lote_id ? Lote::find($this->lote_id) : null;
    }

    /** Sugerencias del buscador de lotes del producto y almacén elegidos. */
    #[Computed]
    public function lotesSugeridos(): Collection
    {
        if (! $this->producto_id || ! $this->almacen_id || $this->lote_id) {
            return new Collection;
        }

        return app(LoteRepository::class)->buscarPorNumero($this->producto_id, $this->almacen_id, $this->buscarLote);
    }

    public function elegirLote(int $id): void
    {
        $perteneceAlProductoYAlmacen = $this->producto_id && $this->almacen_id
            && Lote::whereKey($id)
                ->where('producto_id', $this->producto_id)
                ->where('almacen_id', $this->almacen_id)
                ->exists();

        if (! $perteneceAlProductoYAlmacen) {
            return;
        }

        $this->lote_id = $id;
        $this->buscarLote = '';
        $this->creado = false;
        $this->resetErrorBag('lote_id');
    }

    /** Enter en el buscador de lotes: elige la primera sugerencia. */
    public function elegirPrimerLote(): void
    {
        $primero = $this->lotesSugeridos->first();

        if ($primero) {
            $this->elegirLote($primero->id);
        }
    }

    public function limpiarLote(): void
    {
        $this->lote_id = null;
        $this->buscarLote = '';
        $this->creado = false;
    }

    /** Stock del producto; si hay un almacén elegido, solo el de ese almacén. */
    #[Computed]
    public function stockActual(): int
    {
        return $this->producto_id
            ? Lote::where('producto_id', $this->producto_id)
                ->when($this->almacen_id, fn ($q) => $q->where('almacen_id', $this->almacen_id))
                ->sum('cantidad')
            : 0;
    }

    protected function rules(): array
    {
        $almacen = ['required', Rule::exists('almacenes', 'id')->where('activo', true)];

        if ($this->tipo === 'entrada') {
            return [
                'producto_id' => ['required', 'exists:productos,id'],
                'almacen_id' => $almacen,
                'lote_id' => [
                    'required',
                    Rule::exists('lotes', 'id')
                        ->where('producto_id', $this->producto_id)
                        ->where('almacen_id', $this->almacen_id),
                ],
                'cantidad' => ['required', 'integer', 'min:1'],
            ];
        }

        return [
            'producto_id' => ['required', 'exists:productos,id'],
            'almacen_id' => $almacen,
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', Rule::in(self::MOTIVOS_SALIDA)],
        ];
    }

    protected function messages(): array
    {
        return [
            'producto_id.required' => 'Debe seleccionar un producto.',
            'almacen_id.required' => 'Debe seleccionar el almacén.',
            'almacen_id.exists' => 'Seleccione un almacén activo.',
            'lote_id.required' => 'Debe seleccionar el lote.',
            'lote_id.exists' => 'El lote no corresponde al producto y almacén elegidos.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'motivo.required' => 'Debe seleccionar un motivo de salida.',
            'motivo.in' => 'El motivo seleccionado no es válido.',
        ];
    }

    public function updated(string $property): void
    {
        $this->creado = false;

        if (in_array($property, ['buscarProducto', 'buscarLote'], true)) {
            return;
        }

        if ($property === 'tipo') {
            if (! in_array($this->tipo, ['entrada', 'salida'], true)) {
                $this->tipo = 'entrada';
            }

            $this->resetErrorBag();
            $this->reset(['motivo', 'lote_id', 'buscarLote']);

            return;
        }

        if (in_array($property, ['producto_id', 'almacen_id'], true)) {
            $this->lote_id = null;
            $this->buscarLote = '';
            $this->resetErrorBag('lote_id');
        }

        $this->validateOnly($property);
    }

    public function guardar(StockMovimientoService $servicio): void
    {
        $this->creado = false;

        $data = $this->validate();

        if ($this->tipo === 'entrada') {
            $servicio->registrarEntrada(
                Lote::findOrFail($data['lote_id']),
                $data['cantidad'],
                auth()->user(),
                self::MOTIVO_ENTRADA,
            );

            $this->reset(['cantidad', 'lote_id']);
        } else {
            try {
                $servicio->registrarSalidaEnAlmacen(
                    Producto::find($data['producto_id']),
                    $data['almacen_id'],
                    $data['cantidad'],
                    auth()->user(),
                    $data['motivo'],
                );
            } catch (StockInsuficienteException $e) {
                $this->addError('cantidad', $e->getMessage());

                return;
            }

            $this->reset(['cantidad', 'motivo']);
        }

        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.movimiento-form');
    }
}
