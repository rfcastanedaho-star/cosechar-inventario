<?php

namespace App\Livewire;

use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Producto;
use App\Services\VentaService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VentaForm extends Component
{
    public string $cliente_nombre = '';

    public string $cliente_documento = '';

    public ?int $almacen_id = null;

    public string $fecha = '';

    public string $numero_comprobante = '';

    /** @var array<int, array{producto_id: ?int, cantidad: int, precio_unitario: string}> */
    public array $lineas = [];

    public bool $creado = false;

    public ?int $ventaId = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->lineas = [$this->lineaVacia()];
    }

    /**
     * @return array{producto_id: ?int, cantidad: int, precio_unitario: string}
     */
    private function lineaVacia(): array
    {
        return ['producto_id' => null, 'cantidad' => 1, 'precio_unitario' => ''];
    }

    public function agregarLinea(): void
    {
        $this->lineas[] = $this->lineaVacia();
    }

    public function quitarLinea(int $indice): void
    {
        if (count($this->lineas) <= 1) {
            return;
        }

        unset($this->lineas[$indice]);
        $this->lineas = array_values($this->lineas);
    }

    #[Computed]
    public function almacenes(): Collection
    {
        return Almacen::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function productos(): Collection
    {
        return Producto::orderBy('nombre')->get();
    }

    #[Computed]
    public function total(): float
    {
        return collect($this->lineas)->sum(
            fn (array $linea) => (float) ($linea['cantidad'] ?: 0) * (float) ($linea['precio_unitario'] ?: 0)
        );
    }

    protected function rules(): array
    {
        $reglas = [
            'cliente_nombre' => ['required', 'string', 'max:150'],
            'cliente_documento' => ['nullable', 'string', 'max:20'],
            'almacen_id' => ['required', 'exists:almacenes,id'],
            'fecha' => ['required', 'date'],
            'numero_comprobante' => ['nullable', 'string', 'max:50'],
            'lineas' => ['required', 'array', 'min:1'],
        ];

        foreach ($this->lineas as $indice => $linea) {
            $reglas["lineas.{$indice}.producto_id"] = ['required', 'exists:productos,id'];
            $reglas["lineas.{$indice}.cantidad"] = ['required', 'integer', 'min:1'];
            $reglas["lineas.{$indice}.precio_unitario"] = ['required', 'numeric', 'min:0'];
        }

        return $reglas;
    }

    protected function messages(): array
    {
        return [
            'cliente_nombre.required' => 'El nombre del cliente es obligatorio.',
            'almacen_id.required' => 'Debe seleccionar el almacén de origen.',
            'fecha.required' => 'La fecha es obligatoria.',
            '*.producto_id.required' => 'Debe seleccionar un producto.',
            '*.cantidad.required' => 'La cantidad es obligatoria.',
            '*.cantidad.min' => 'La cantidad debe ser mayor a cero.',
            '*.precio_unitario.required' => 'El precio unitario es obligatorio.',
        ];
    }

    public function guardar(VentaService $servicio): void
    {
        $data = $this->validate();

        try {
            $venta = $servicio->registrar(
                [
                    'cliente_nombre' => $data['cliente_nombre'],
                    'cliente_documento' => $data['cliente_documento'] ?: null,
                    'almacen_id' => $data['almacen_id'],
                    'fecha' => $data['fecha'],
                    'numero_comprobante' => $data['numero_comprobante'] ?: null,
                ],
                $data['lineas'],
                auth()->user(),
            );
        } catch (StockInsuficienteException $e) {
            $this->addError('lineas', $e->getMessage());

            return;
        }

        $this->reset(['cliente_nombre', 'cliente_documento', 'almacen_id', 'numero_comprobante']);
        $this->lineas = [$this->lineaVacia()];
        $this->fecha = now()->toDateString();
        $this->ventaId = $venta->id;
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.venta-form');
    }
}
