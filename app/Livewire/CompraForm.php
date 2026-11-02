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

    /** Texto escrito en el campo Proveedor (nombre o RUC) para filtrar los registrados. */
    public string $buscarProveedor = '';

    /** La lista desplegable de proveedores está abierta (flechita o foco en el campo). */
    public bool $listaProveedores = false;

    public ?int $almacen_id = null;

    public string $fecha = '';

    public string $numero_comprobante = '';

    /** @var array<int, array{producto_id: ?int, cantidad: int, costo_unitario: string}> */
    public array $lineas = [];

    public bool $creado = false;

    public ?int $compraId = null;

    /** Paso de verificación: se muestra el resumen con el total antes de guardar. */
    public bool $confirmando = false;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->lineas = [$this->lineaVacia()];
        $this->almacen_id = Almacen::unicoActivo()?->id;
    }

    /**
     * @return array{producto_id: ?int, cantidad: int, costo_unitario: string}
     */
    private function lineaVacia(): array
    {
        return ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => ''];
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

    /**
     * Lista desplegable del campo Proveedor: sin texto, todos los registrados en orden alfabético;
     * con texto, los que empiezan o contienen lo escrito. Solo si la lista está abierta o hay texto.
     */
    #[Computed]
    public function proveedoresSugeridos(): Collection
    {
        if ($this->proveedor_id || (! $this->listaProveedores && trim($this->buscarProveedor) === '')) {
            return new Collection;
        }

        return app(ProveedorRepository::class)->sugerir($this->buscarProveedor);
    }

    public function alternarListaProveedores(): void
    {
        $this->listaProveedores = ! $this->listaProveedores;
    }

    public function elegirProveedor(int $id): void
    {
        if (! Proveedor::whereKey($id)->where('activo', true)->exists()) {
            return;
        }

        $this->proveedor_id = $id;
        $this->buscarProveedor = '';
        $this->listaProveedores = false;
        $this->resetErrorBag('proveedor_id');
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
        $this->reset(['proveedor_id', 'buscarProveedor', 'listaProveedores']);
    }

    /** Cualquier cambio durante la verificación la anula: hay que revisar de nuevo. */
    public function updated(): void
    {
        $this->confirmando = false;
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
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'almacen_id' => ['required', 'exists:almacenes,id'],
            'fecha' => ['required', 'date'],
            'numero_comprobante' => ['nullable', 'string', 'max:50'],
            'lineas' => ['required', 'array', 'min:1'],
        ];

        foreach ($this->lineas as $indice => $linea) {
            $reglas["lineas.{$indice}.producto_id"] = ['required', 'exists:productos,id'];
            $reglas["lineas.{$indice}.cantidad"] = ['required', 'integer', 'min:1'];
            $reglas["lineas.{$indice}.costo_unitario"] = ['required', 'numeric', 'min:0'];
        }

        return $reglas;
    }

    protected function messages(): array
    {
        return [
            'proveedor_id.required' => 'Elija un proveedor registrado. Si no está en la lista, regístrelo primero en Proveedores.',
            'proveedor_id.exists' => 'Elija un proveedor registrado.',
            'almacen_id.required' => 'Debe seleccionar el almacén destino.',
            'fecha.required' => 'La fecha de la factura es obligatoria.',
            '*.producto_id.required' => 'Debe seleccionar un producto.',
            '*.cantidad.required' => 'La cantidad es obligatoria.',
            '*.cantidad.min' => 'La cantidad debe ser mayor a cero.',
            '*.costo_unitario.required' => 'El costo unitario es obligatorio.',
        ];
    }

    /** Valida todo y pasa al resumen con el total; todavía no se guarda nada. */
    public function revisar(): void
    {
        $this->validate();
        $this->confirmando = true;
    }

    public function volverAEditar(): void
    {
        $this->confirmando = false;
    }

    public function guardar(CompraService $servicio): void
    {
        $data = $this->validate();

        $compra = $servicio->registrar(
            [
                'proveedor_id' => $data['proveedor_id'],
                'almacen_id' => $data['almacen_id'],
                'fecha' => $data['fecha'],
                'numero_comprobante' => $data['numero_comprobante'] ?: null,
            ],
            $data['lineas'],
            auth()->user(),
        );

        $this->reset(['proveedor_id', 'buscarProveedor', 'listaProveedores', 'numero_comprobante', 'confirmando']);
        $this->almacen_id = Almacen::unicoActivo()?->id;
        $this->lineas = [$this->lineaVacia()];
        $this->busquedaProducto = [];
        $this->listaProducto = [];
        $this->fecha = now()->toDateString();
        $this->compraId = $compra->id;
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.compra-form');
    }
}
