<?php

namespace App\Livewire;

use App\Models\Almacen;
use App\Models\Producto;
use App\Repositories\CompraRepository;
use App\Repositories\LoteRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\StockMovimientoRepository;
use App\Repositories\VentaRepository;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ReporteFiltro extends Component
{
    public string $tab = 'stock';

    public string $fecha = '';

    public ?int $producto_id = null;

    /** Texto del buscador de producto (nombre o código). */
    public string $buscarProducto = '';

    public string $tipo = '';

    public string $desde = '';

    public string $hasta = '';

    public ?int $almacen_id = null;

    #[Computed]
    public function productoSeleccionado(): ?Producto
    {
        return $this->producto_id ? Producto::find($this->producto_id) : null;
    }

    /** En reportes también se buscan los productos inactivos: tienen historial. */
    #[Computed]
    public function productosSugeridos(): Collection
    {
        return $this->producto_id
            ? new Collection
            : app(ProductoRepository::class)->buscar($this->buscarProducto, soloActivos: false);
    }

    public function elegirProducto(int $id): void
    {
        if (! Producto::whereKey($id)->exists()) {
            return;
        }

        $this->producto_id = $id;
        $this->buscarProducto = '';
    }

    public function limpiarProducto(): void
    {
        $this->reset(['producto_id', 'buscarProducto']);
    }

    #[Computed]
    public function almacenes(): Collection
    {
        return Almacen::orderBy('nombre')->get();
    }

    /**
     * Stock actual desagregado por producto y lote (RF-08).
     */
    #[Computed]
    public function stock(): Collection
    {
        return (new LoteRepository)->conStock($this->almacen_id);
    }

    #[Computed]
    public function movimientos(): Collection
    {
        return (new StockMovimientoRepository)->porFiltros(
            $this->fecha ?: null,
            $this->producto_id,
            $this->tipo ?: null,
        );
    }

    #[Computed]
    public function compras(): Collection
    {
        return (new CompraRepository)->porFiltros($this->desde ?: null, $this->hasta ?: null, $this->almacen_id);
    }

    #[Computed]
    public function ventas(): Collection
    {
        return (new VentaRepository)->porFiltros($this->desde ?: null, $this->hasta ?: null, $this->almacen_id);
    }

    /** Las anuladas se listan pero no suman. */
    #[Computed]
    public function totalCompras(): float
    {
        return (float) $this->compras->whereNull('anulada_at')->sum('total');
    }

    #[Computed]
    public function totalVentas(): float
    {
        return (float) $this->ventas->whereNull('anulada_at')->sum('total');
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['fecha', 'producto_id', 'buscarProducto', 'tipo', 'desde', 'hasta', 'almacen_id']);
    }

    public function render()
    {
        return view('livewire.reporte-filtro');
    }
}
