<?php

namespace App\Livewire;

use App\Models\Almacen;
use App\Models\StockMovimiento;
use App\Repositories\StockMovimientoRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MovimientoLista extends Component
{
    use WithPagination;

    #[Url]
    public string $buscar = '';

    #[Url]
    public string $tipo = '';

    #[Url]
    public ?int $almacen_id = null;

    #[Url]
    public string $motivo = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Computed]
    public function almacenes(): Collection
    {
        return Almacen::orderBy('nombre')->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function motivos(): array
    {
        return StockMovimiento::MOTIVOS;
    }

    #[Computed]
    public function movimientos(): LengthAwarePaginator
    {
        return app(StockMovimientoRepository::class)->historial([
            'buscar' => $this->buscar,
            'tipo' => in_array($this->tipo, ['entrada', 'salida'], true) ? $this->tipo : null,
            'almacen_id' => $this->almacen_id,
            'motivo' => array_key_exists($this->motivo, StockMovimiento::MOTIVOS) ? $this->motivo : null,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ]);
    }

    /** Al cambiar cualquier filtro se vuelve a la primera página. */
    public function updated(string $property): void
    {
        if (in_array($property, ['buscar', 'tipo', 'almacen_id', 'motivo', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'tipo', 'almacen_id', 'motivo', 'desde', 'hasta']);
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.movimiento-lista');
    }
}
