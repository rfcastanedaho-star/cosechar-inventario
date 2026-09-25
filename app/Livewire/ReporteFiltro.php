<?php

namespace App\Livewire;

use App\Models\Producto;
use App\Repositories\ProductoRepository;
use App\Repositories\StockMovimientoRepository;
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

    public string $tipo = '';

    #[Computed]
    public function productos(): Collection
    {
        return Producto::orderBy('nombre')->get();
    }

    #[Computed]
    public function stock(): Collection
    {
        return (new ProductoRepository)->conStockActual()->sortBy('nombre')->values();
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

    public function limpiarFiltros(): void
    {
        $this->reset(['fecha', 'producto_id', 'tipo']);
    }

    public function render()
    {
        return view('livewire.reporte-filtro');
    }
}
