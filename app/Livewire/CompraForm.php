<?php

namespace App\Livewire;

use App\Livewire\Concerns\BuscaProductoPorLinea;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use App\Services\CompraService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CompraForm extends Component
{
    use BuscaProductoPorLinea;

    public ?int $proveedor_id = null;

    /** Texto del buscador de proveedor (nombre o RUC). */
    public string $buscarProveedor = '';

    /** Datos del proveedor: se rellenan al elegir uno registrado o se escriben para registrarlo al guardar. */
    public string $proveedor_nombre = '';

    public string $proveedor_ruc = '';

    public string $proveedor_telefono = '';

    public ?int $almacen_id = null;

    public string $fecha = '';

    public string $numero_comprobante = '';

    /** @var array<int, array{producto_id: ?int, numero_lote: string, fecha_vencimiento: string, cantidad: int, costo_unitario: string}> */
    public array $lineas = [];

    public bool $creado = false;

    public ?int $compraId = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->lineas = [$this->lineaVacia()];
    }

    /**
     * @return array{producto_id: ?int, numero_lote: string, fecha_vencimiento: string, cantidad: int, costo_unitario: string}
     */
    private function lineaVacia(): array
    {
        return ['producto_id' => null, 'numero_lote' => '', 'fecha_vencimiento' => '', 'cantidad' => 1, 'costo_unitario' => ''];
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
        $this->quitarBusquedaDeLinea($indice);
    }

    #[Computed]
    public function proveedorSeleccionado(): ?Proveedor
    {
        return $this->proveedor_id ? Proveedor::find($this->proveedor_id) : null;
    }

    /** Sugerencias del buscador mientras no haya un proveedor elegido. */
    #[Computed]
    public function proveedoresSugeridos(): Collection
    {
        return $this->proveedor_id
            ? new Collection
            : app(ProveedorRepository::class)->buscar($this->buscarProveedor);
    }

    public function elegirProveedor(int $id): void
    {
        if (! Proveedor::whereKey($id)->where('activo', true)->exists()) {
            return;
        }

        $proveedor = Proveedor::findOrFail($id);

        $this->proveedor_id = $proveedor->id;
        $this->proveedor_nombre = $proveedor->nombre;
        $this->proveedor_ruc = $proveedor->ruc ?? '';
        $this->proveedor_telefono = $proveedor->telefono ?? '';
        $this->buscarProveedor = '';
        $this->resetErrorBag(['proveedor_nombre', 'proveedor_ruc']);
    }

    /** Si se cambia a mano el nombre o el RUC, ya no es el proveedor registrado: se tratará como uno por registrar. */
    public function updatedProveedorNombre(): void
    {
        $this->proveedor_id = null;
    }

    public function updatedProveedorRuc(): void
    {
        $this->proveedor_id = null;
    }

    /** Enter en el buscador: elige la primera sugerencia. */
    public function elegirPrimerProveedor(): void
    {
        $primero = $this->proveedoresSugeridos->first();

        if ($primero) {
            $this->elegirProveedor($primero->id);
        }
    }

    public function limpiarProveedor(): void
    {
        $this->reset(['proveedor_id', 'buscarProveedor', 'proveedor_nombre', 'proveedor_ruc', 'proveedor_telefono']);
    }

    #[Computed]
    public function almacenes(): Collection
    {
        return Almacen::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function productos(): Collection
    {
        return Producto::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function total(): float
    {
        return collect($this->lineas)->sum(
            fn (array $linea) => (float) ($linea['cantidad'] ?: 0) * (float) ($linea['costo_unitario'] ?: 0)
        );
    }

    protected function rules(): array
    {
        $reglas = [
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'proveedor_nombre' => ['required', 'string', 'max:150'],
            'proveedor_ruc' => ['nullable', 'digits:11'],
            'proveedor_telefono' => ['nullable', 'string', 'max:30'],
            'almacen_id' => ['required', 'exists:almacenes,id'],
            'fecha' => ['required', 'date'],
            'numero_comprobante' => ['nullable', 'string', 'max:50'],
            'lineas' => ['required', 'array', 'min:1'],
        ];

        foreach ($this->lineas as $indice => $linea) {
            $producto = ! empty($linea['producto_id']) ? Producto::find($linea['producto_id']) : null;

            $reglas["lineas.{$indice}.producto_id"] = ['required', 'exists:productos,id'];
            $reglas["lineas.{$indice}.numero_lote"] = ['required', 'string', 'max:50'];
            $reglas["lineas.{$indice}.cantidad"] = ['required', 'integer', 'min:1'];
            $reglas["lineas.{$indice}.costo_unitario"] = ['required', 'numeric', 'min:0'];
            $reglas["lineas.{$indice}.fecha_vencimiento"] = [
                $producto?->maneja_vencimiento ? 'required' : 'nullable',
                'date',
            ];
        }

        return $reglas;
    }

    protected function messages(): array
    {
        return [
            'proveedor_nombre.required' => 'Escriba el nombre del proveedor o elija uno registrado.',
            'proveedor_ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'almacen_id.required' => 'Debe seleccionar el almacén destino.',
            'fecha.required' => 'La fecha es obligatoria.',
            '*.producto_id.required' => 'Debe seleccionar un producto.',
            '*.numero_lote.required' => 'El número de lote es obligatorio.',
            '*.cantidad.required' => 'La cantidad es obligatoria.',
            '*.cantidad.min' => 'La cantidad debe ser mayor a cero.',
            '*.costo_unitario.required' => 'El costo unitario es obligatorio.',
            '*.fecha_vencimiento.required' => 'Este producto maneja vencimiento: la fecha es obligatoria.',
        ];
    }

    public function guardar(CompraService $servicio): void
    {
        $this->proveedor_nombre = trim($this->proveedor_nombre);
        $this->proveedor_ruc = trim($this->proveedor_ruc);
        $this->proveedor_telefono = trim($this->proveedor_telefono);

        $data = $this->validate();

        $compra = $servicio->registrar(
            [
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'proveedor_nombre' => $data['proveedor_nombre'],
                'proveedor_ruc' => $data['proveedor_ruc'] ?: null,
                'proveedor_telefono' => $data['proveedor_telefono'] ?: null,
                'almacen_id' => $data['almacen_id'],
                'fecha' => $data['fecha'],
                'numero_comprobante' => $data['numero_comprobante'] ?: null,
            ],
            $data['lineas'],
            auth()->user(),
        );

        $this->reset(['proveedor_id', 'buscarProveedor', 'proveedor_nombre', 'proveedor_ruc', 'proveedor_telefono', 'almacen_id', 'numero_comprobante']);
        $this->lineas = [$this->lineaVacia()];
        $this->busquedaProducto = [];
        $this->fecha = now()->toDateString();
        $this->compraId = $compra->id;
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.compra-form');
    }
}
