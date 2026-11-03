<?php

namespace App\Livewire;

use App\Exceptions\StockInsuficienteException;
use App\Livewire\Concerns\BuscaProductoPorLinea;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use App\Repositories\ClienteRepository;
use App\Services\VentaService;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VentaForm extends Component
{
    use BuscaProductoPorLinea;

    /** Cliente registrado elegido: la venta solo se hace a clientes registrados en Clientes. */
    public ?int $cliente_id = null;

    /** Texto escrito en el campo Cliente (nombre, documento o teléfono) para filtrar los registrados. */
    public string $buscarCliente = '';

    /** La lista desplegable de clientes está abierta (flechita o foco en el campo). */
    public bool $listaClientes = false;

    public ?int $almacen_id = null;

    public string $fecha = '';

    public string $numero_comprobante = '';

    /** Cómo paga el cliente: efectivo, tarjeta o transferencia. */
    public string $metodo_pago = Venta::EFECTIVO;

    /** Solo en efectivo: lo que entrega el cliente. Vacío = paga el total exacto. */
    public string $monto_recibido = '';

    /** El monto recibido se puso con «Exacto»: sigue al total mientras se cambian cantidades o precios. */
    public bool $montoSigueTotal = false;

    /** @var array<int, array{producto_id: ?int, cantidad: int, precio_unitario: string, por_mayor: bool, precio_manual: bool}> */
    public array $lineas = [];

    public bool $creado = false;

    public ?int $ventaId = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->lineas = [$this->lineaVacia()];
        $this->almacen_id = Almacen::unicoActivo()?->id;
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

        if ($this->montoSigueTotal) {
            $this->sincronizarMontoExacto();
        }
    }

    #[Computed]
    public function clienteSeleccionado(): ?Cliente
    {
        return $this->cliente_id ? Cliente::find($this->cliente_id) : null;
    }

    /**
     * Lista desplegable del campo Cliente: sin texto, todos los registrados en orden alfabético;
     * con texto, los que coinciden. Solo si la lista está abierta o hay texto.
     */
    #[Computed]
    public function clientesSugeridos(): Collection
    {
        if ($this->cliente_id || (! $this->listaClientes && trim($this->buscarCliente) === '')) {
            return new Collection;
        }

        return app(ClienteRepository::class)->sugerir($this->buscarCliente);
    }

    public function alternarListaClientes(): void
    {
        $this->listaClientes = ! $this->listaClientes;
    }

    public function elegirCliente(int $id): void
    {
        if (! Cliente::where('activo', true)->whereKey($id)->exists()) {
            return;
        }

        $this->cliente_id = $id;
        $this->buscarCliente = '';
        $this->listaClientes = false;
        $this->resetErrorBag('cliente_id');
    }

    /** Enter en el campo: elige el primer cliente de la lista. */
    public function elegirPrimerCliente(): void
    {
        $primero = $this->clientesSugeridos->first();

        if ($primero) {
            $this->elegirCliente($primero->id);
        }
    }

    public function limpiarCliente(): void
    {
        $this->reset(['cliente_id', 'buscarCliente', 'listaClientes']);
    }

    /** Al cambiar de método de pago se borra el monto recibido: solo aplica al efectivo. */
    public function updatedMetodoPago(): void
    {
        $this->monto_recibido = '';
        $this->montoSigueTotal = false;
        $this->resetErrorBag('monto_recibido');
    }

    /** Escribir el monto a mano lo desvincula del total. */
    public function updatedMontoRecibido(): void
    {
        $this->montoSigueTotal = false;
    }

    /** El cliente paga justo el total: deja el monto recibido igual al total. */
    public function montoExacto(): void
    {
        $this->montoSigueTotal = true;
        $this->sincronizarMontoExacto();
    }

    private function sincronizarMontoExacto(): void
    {
        unset($this->total); // el total depende de las líneas que acaban de cambiar
        $this->monto_recibido = number_format($this->total, 2, '.', '');
    }

    /** ¿Se está cobrando en efectivo con un monto recibido escrito? */
    private function hayMontoRecibido(): bool
    {
        return $this->metodo_pago === Venta::EFECTIVO && trim($this->monto_recibido) !== '' && is_numeric($this->monto_recibido);
    }

    /** Vuelto a entregar: lo recibido menos el total; null si no hay un monto recibido suficiente. */
    #[Computed]
    public function vuelto(): ?float
    {
        if (! $this->hayMontoRecibido()) {
            return null;
        }

        $vuelto = round((float) $this->monto_recibido - $this->total, 2);

        return $vuelto >= 0 ? $vuelto : null;
    }

    /** Lo que falta cuando el monto recibido no alcanza para pagar el total; null si alcanza o no hay monto. */
    #[Computed]
    public function faltante(): ?float
    {
        if (! $this->hayMontoRecibido()) {
            return null;
        }

        $falta = round($this->total - (float) $this->monto_recibido, 2);

        return $falta > 0 ? $falta : null;
    }

    /** Al elegir el producto se propone su precio normal, sin pisar uno escrito a mano. */
    protected function alElegirProducto(int $indice, Producto $producto): void
    {
        if (! $this->lineas[$indice]['por_mayor'] && ! $this->lineas[$indice]['precio_manual']) {
            $this->lineas[$indice]['precio_unitario'] = (string) $producto->precio;
        }

        if ($this->montoSigueTotal) {
            $this->sincronizarMontoExacto();
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

    /** Un cambio de cantidad o precio (ya aplicado por updatedLineas) mantiene al día el monto «Exacto». */
    public function updated(string $property): void
    {
        if ($this->montoSigueTotal && str_starts_with($property, 'lineas')) {
            $this->sincronizarMontoExacto();
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

            'almacen_id' => ['required', 'exists:almacenes,id'],
            'fecha' => ['required', 'date'],
            'numero_comprobante' => ['nullable', 'string', 'max:50'],
            'lineas' => ['required', 'array', 'min:1'],
            'cliente_id' => ['required', 'exists:clientes,id'],
            'metodo_pago' => ['required', Rule::in(array_keys(Venta::METODOS_PAGO))],
            'monto_recibido' => [
                'nullable', 'numeric', 'min:0',
                fn (string $atributo, mixed $valor, Closure $falla) => $this->metodo_pago === Venta::EFECTIVO && trim((string) $valor) !== '' && round((float) $valor, 2) < round($this->total, 2)
                    ? $falla('El monto recibido es menor que el total: faltan S/ '.number_format($this->total - (float) $valor, 2).'.')
                    : null,
            ],
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
            'metodo_pago.required' => 'Elija el método de pago.',
            'metodo_pago.in' => 'Elija el método de pago.',
            'monto_recibido.numeric' => 'Escriba el monto recibido en números.',
            'cliente_id.required' => 'Elija un cliente registrado. Si no está en la lista, regístrelo primero en Clientes.',
            'cliente_id.exists' => 'Elija un cliente registrado.',
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
        $data = $this->validate();

        $cliente = Cliente::findOrFail($data['cliente_id']);

        try {
            $venta = $servicio->registrar(
                [
                    'cliente_id' => $cliente->id,
                    'cliente_nombre' => $cliente->nombre,
                    'cliente_documento' => $cliente->documento,
                    'almacen_id' => $data['almacen_id'],
                    'fecha' => $data['fecha'],
                    'numero_comprobante' => $data['numero_comprobante'] ?: null,
                    'metodo_pago' => $data['metodo_pago'],
                    'monto_recibido' => $data['metodo_pago'] === Venta::EFECTIVO ? ($data['monto_recibido'] ?: null) : null,
                ],
                $data['lineas'],
                auth()->user(),
            );
        } catch (StockInsuficienteException $e) {
            $this->addError('lineas', $e->getMessage());

            return;
        }

        $this->reset(['cliente_id', 'buscarCliente', 'listaClientes', 'numero_comprobante', 'monto_recibido', 'montoSigueTotal']);
        $this->metodo_pago = Venta::EFECTIVO;
        $this->almacen_id = Almacen::unicoActivo()?->id;
        $this->lineas = [$this->lineaVacia()];
        $this->busquedaProducto = [];
        $this->listaProducto = [];
        $this->fecha = now()->toDateString();
        $this->ventaId = $venta->id;
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.venta-form');
    }
}
