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
    public string $tipo = 'entrada';

    public ?int $producto_id = null;

    public ?int $almacen_id = null;

    public string $numero_lote = '';

    public string $fecha_ingreso = '';

    public string $fecha_vencimiento = '';

    public int $cantidad = 0;

    public string $motivo = '';

    public bool $creado = false;

    public ?int $ultimoLoteId = null;

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

        if ($this->tipo === 'entrada') {
            return [
                'producto_id' => ['required', 'exists:productos,id'],
                'almacen_id' => $almacen,
                'numero_lote' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('lotes', 'numero_lote')->where(
                        fn ($query) => $query->where('producto_id', $this->producto_id)
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
            'motivo' => ['required', 'in:venta,merma,producto_danado,ajuste_inventario,transferencia'],
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
            'motivo.required' => 'Debe seleccionar un motivo de salida.',
        ];
    }

    public function updated(string $property): void
    {
        $this->creado = false;
        $this->ultimoLoteId = null;

        if ($property === 'tipo') {
            $this->resetErrorBag();

            return;
        }

        $this->validateOnly($property);

        if ($property === 'producto_id' && $this->numero_lote !== '') {
            $this->validateOnly('numero_lote');
        }
    }

    public function guardar(StockMovimientoService $servicio, LoteRepository $lotes): void
    {
        $this->creado = false;
        $this->ultimoLoteId = null;

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

            $servicio->registrarEntrada($lote, $data['cantidad'], auth()->user());

            $this->ultimoLoteId = $lote->id;
            $this->reset(['numero_lote', 'fecha_vencimiento', 'cantidad']);
            $this->fecha_ingreso = now()->toDateString();
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
