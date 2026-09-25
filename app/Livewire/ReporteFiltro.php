<?php

namespace App\Livewire;

use App\Models\Lote;
use App\Models\Producto;
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

    /**
     * Stock actual desagregado por producto y lote (RF-08).
     */
    #[Computed]
    public function stock(): Collection
    {
        return Lote::with('producto.categoria')
            ->where('cantidad', '>', 0)
            ->get()
            ->sortBy([['producto.nombre', 'asc'], ['fecha_vencimiento', 'asc']])
            ->values();
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
