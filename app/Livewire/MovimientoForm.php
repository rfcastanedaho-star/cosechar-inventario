<?php

namespace App\Livewire;

use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Repositories\LoteRepository;
use App\Services\StockMovimientoService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MovimientoForm extends Component
{
    /** Motivos de una entrada manual (las compras generan sus entradas solas). */
    public const MOTIVOS_ENTRADA = ['saldo_inicial', 'ajuste_inventario'];

    /** Motivos de una salida manual (las ventas y la transferencia tienen su propio flujo). */
    public const MOTIVOS_SALIDA = ['merma', 'producto_danado', 'ajuste_inventario'];

    public string $tipo = 'entrada';

    public ?int $producto_id = null;

    public ?int $almacen_id = null;

    public ?int $almacen_destino_id = null;

    public string $numero_lote = '';

    public string $fecha_ingreso = '';

    public string $fecha_vencimiento = '';

    public int $cantidad = 0;

    public string $motivo = '';

    public bool $creado = false;

    public function mount(): void
    {
        $this->fecha_ingreso = now()->toDateString();
    }

    #[Computed]
    public function productos(): Collection
    {
        return Producto::orderBy('nombre')->get();
    }

    #[Computed]
    public function almacenes(): Collection
    {
        return Almacen::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function productoSeleccionado(): ?Producto
    {
        return $this->producto_id ? Producto::find($this->producto_id) : null;
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

        if ($this->tipo === 'transferencia') {
            return [
                'producto_id' => ['required', 'exists:productos,id'],
                'almacen_id' => $almacen,
                'almacen_destino_id' => [
                    'required',
                    'different:almacen_id',
                    Rule::exists('almacenes', 'id')->where('activo', true),
                ],
                'cantidad' => ['required', 'integer', 'min:1'],
            ];
        }

        if ($this->tipo === 'entrada') {
            return [
                'producto_id' => ['required', 'exists:productos,id'],
                'almacen_id' => $almacen,
                'motivo' => ['required', Rule::in(self::MOTIVOS_ENTRADA)],
                'numero_lote' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('lotes', 'numero_lote')->where(
                        fn ($query) => $query
                            ->where('producto_id', $this->producto_id)
                            ->where('almacen_id', $this->almacen_id)
                    ),
                ],
                'fecha_ingreso' => ['required', 'date'],
                'fecha_vencimiento' => [
                    $this->productoSeleccionado?->maneja_vencimiento ? 'required' : 'nullable',
                    'date',
                    'after_or_equal:fecha_ingreso',
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
            'numero_lote.required' => 'El número de lote es obligatorio.',
            'numero_lote.unique' => 'Ya existe un lote con este número para el producto seleccionado.',
            'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',
            'fecha_vencimiento.required' => 'Este producto maneja vencimiento: la fecha es obligatoria.',
            'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento no puede ser anterior al ingreso.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'motivo.required' => $this->tipo === 'entrada'
                ? 'Debe seleccionar un motivo de entrada.'
                : 'Debe seleccionar un motivo de salida.',
            'motivo.in' => 'El motivo seleccionado no es válido.',
            'almacen_destino_id.required' => 'Debe seleccionar el almacén de destino.',
            'almacen_destino_id.different' => 'El almacén de destino debe ser distinto del de origen.',
            'almacen_destino_id.exists' => 'Seleccione un almacén de destino activo.',
        ];
    }

    public function updated(string $property): void
    {
        $this->creado = false;

        if ($property === 'tipo') {
            $this->resetErrorBag();
            $this->reset(['motivo', 'almacen_destino_id']);

            return;
        }

        $this->validateOnly($property);

        if (in_array($property, ['producto_id', 'almacen_id'], true) && $this->tipo === 'entrada' && $this->numero_lote !== '') {
            $this->validateOnly('numero_lote');
        }

        if ($property === 'almacen_id' && $this->tipo === 'transferencia' && $this->almacen_destino_id) {
            $this->validateOnly('almacen_destino_id');
        }
    }

    public function guardar(StockMovimientoService $servicio, LoteRepository $lotes): void
    {
        $this->creado = false;

        $data = $this->validate();

        if ($this->tipo === 'entrada') {
            $lote = $lotes->create([
                'producto_id' => $data['producto_id'],
                'almacen_id' => $data['almacen_id'],
                'numero_lote' => $data['numero_lote'],
                'fecha_ingreso' => $data['fecha_ingreso'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?: null,
                'cantidad' => 0,
            ]);

            $servicio->registrarEntrada($lote, $data['cantidad'], auth()->user(), $data['motivo']);

            $this->reset(['numero_lote', 'fecha_vencimiento', 'cantidad', 'motivo']);
            $this->fecha_ingreso = now()->toDateString();
        } elseif ($this->tipo === 'transferencia') {
            try {
                $servicio->transferir(
                    Producto::find($data['producto_id']),
                    $data['almacen_id'],
                    $data['almacen_destino_id'],
                    $data['cantidad'],
                    auth()->user(),
                );
            } catch (StockInsuficienteException $e) {
                $this->addError('cantidad', $e->getMessage());

                return;
            }

            $this->reset(['cantidad', 'almacen_destino_id']);
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
