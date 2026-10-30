<?php

namespace App\Livewire;

use App\Exceptions\StockInsuficienteException;
use App\Livewire\Concerns\BuscaProductoPorLinea;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Producto;
use App\Repositories\ClienteRepository;
use App\Services\VentaService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VentaForm extends Component
{
    use BuscaProductoPorLinea;

    /** Cliente registrado elegido; null si se vende a alguien no registrado. */
    public ?int $cliente_id = null;

    /** Texto del buscador de clientes registrados (nombre, documento o teléfono). */
    public string $buscarCliente = '';

    public string $cliente_nombre = '';

    public string $cliente_documento = '';

    public ?int $almacen_id = null;

    public string $fecha = '';

    public string $numero_comprobante = '';

    /** @var array<int, array{producto_id: ?int, cantidad: int, precio_unitario: string, por_mayor: bool, precio_manual: bool}> */
    public array $lineas = [];

    public bool $creado = false;

    public ?int $ventaId = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->lineas = [$this->lineaVacia()];
    }

    /**
     * @return array{producto_id: ?int, cantidad: int, precio_unitario: string, por_mayor: bool, precio_manual: bool}
     */
    private function lineaVacia(): array
    {
        return ['producto_id' => null, 'cantidad' => 1, 'precio_unitario' => '', 'por_mayor' => false, 'precio_manual' => false];
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

    /** Sugerencias del buscador mientras no haya un cliente registrado elegido. */
    #[Computed]
    public function clientesSugeridos(): Collection
    {
        return $this->cliente_id
            ? new Collection
            : app(ClienteRepository::class)->buscar($this->buscarCliente);
    }

    public function elegirCliente(int $id): void
    {
        $cliente = Cliente::where('activo', true)->find($id);

        if (! $cliente) {
            return;
        }

        $this->cliente_id = $cliente->id;
        $this->cliente_nombre = $cliente->nombre;
        $this->cliente_documento = $cliente->documento ?? '';
        $this->buscarCliente = '';
        $this->resetErrorBag('cliente_nombre');
    }

    /** Enter en el buscador: elige el primer cliente sugerido. */
    public function elegirPrimerCliente(): void
    {
        $primero = $this->clientesSugeridos->first();

        if ($primero) {
            $this->elegirCliente($primero->id);
        }
    }

    public function limpiarCliente(): void
    {
        $this->reset(['cliente_id', 'cliente_nombre', 'cliente_documento', 'buscarCliente']);
    }

    /** Si se cambia a mano el nombre o el documento, ya no es el cliente registrado. */
    public function updatedClienteNombre(): void
    {
        $this->cliente_id = null;
    }

    public function updatedClienteDocumento(): void
    {
        $this->cliente_id = null;
    }

    /** Al elegir el producto se propone su precio normal, sin pisar uno escrito a mano. */
    protected function alElegirProducto(int $indice, Producto $producto): void
    {
        if ($this->lineas[$indice]['por_mayor']) {
            return;
        }

        if (! $this->lineas[$indice]['precio_manual']) {
            $this->lineas[$indice]['precio_unitario'] = (string) $producto->precio;
        }
    }

    /**
     * Escribir un precio lo fija a mano. Marcar «Venta por mayor» pide escribir ese precio;
     * desmarcarla vuelve al precio normal.
     */
    public function updatedLineas(mixed $valor, string $clave): void
    {
        [$indice, $campo] = array_pad(explode('.', $clave, 2), 2, null);
        $indice = (int) $indice;

        if (! isset($this->lineas[$indice])) {
            return;
        }

        if ($campo === 'precio_unitario') {
            $this->lineas[$indice]['precio_manual'] = trim((string) $valor) !== '';
        }

        if ($campo === 'por_mayor') {
            $producto = $this->productoDeLinea($indice);

            $this->lineas[$indice]['precio_manual'] = false;
            $this->lineas[$indice]['precio_unitario'] = $this->lineas[$indice]['por_mayor'] || $producto === null
                ? ''
                : (string) $producto->precio;
        }
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
            fn (array $linea) => (float) ($linea['cantidad'] ?: 0) * (float) ($linea['precio_unitario'] ?: 0)
        );
    }

    protected function rules(): array
    {
        $reglas = [
            'cliente_nombre' => ['required', 'string', 'max:150'],
            'cliente_documento' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'almacen_id' => ['required', 'exists:almacenes,id'],
            'fecha' => ['required', 'date'],
            'numero_comprobante' => ['nullable', 'string', 'max:50'],
            'lineas' => ['required', 'array', 'min:1'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
        ];

        foreach ($this->lineas as $indice => $linea) {
            $reglas["lineas.{$indice}.producto_id"] = ['required', 'exists:productos,id'];
            $reglas["lineas.{$indice}.cantidad"] = ['required', 'integer', 'min:1'];
            $reglas["lineas.{$indice}.precio_unitario"] = ['required', 'numeric', 'min:0'];
            $reglas["lineas.{$indice}.por_mayor"] = ['boolean'];

            // El precio por mayor se escribe en la venta, pero nunca supera el precio normal del producto.
            if (($linea['por_mayor'] ?? false) && ($normal = $this->productoDeLinea($indice)?->precio) !== null) {
                $reglas["lineas.{$indice}.precio_unitario"][] = 'lte:'.$normal;
            }
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
            '*.precio_unitario.lte' => 'El precio por mayor no puede ser mayor que el precio normal del producto.',
        ];
    }

    public function guardar(VentaService $servicio): void
    {
        $this->cliente_nombre = trim($this->cliente_nombre);
        $this->cliente_documento = trim($this->cliente_documento);

        $data = $this->validate();

        try {
            $venta = $servicio->registrar(
                [
                    'cliente_id' => $data['cliente_id'] ?? null,
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

        $this->reset(['cliente_id', 'buscarCliente', 'cliente_nombre', 'cliente_documento', 'almacen_id', 'numero_comprobante']);
        $this->lineas = [$this->lineaVacia()];
        $this->busquedaProducto = [];
        $this->fecha = now()->toDateString();
        $this->ventaId = $venta->id;
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.venta-form');
    }
}
