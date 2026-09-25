<?php

namespace App\Livewire;

use App\Models\Producto;
use App\Repositories\ProductoRepository;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProductoLista extends Component
{
    /** @var array<int, int> */
    public array $stockMinimoEdit = [];

    public function mount(): void
    {
        $this->stockMinimoEdit = Producto::pluck('stock_minimo', 'id')->all();
    }

    #[Computed]
    public function productos(): Collection
    {
        return (new ProductoRepository)->conStockActual()->sortBy('nombre')->values();
    }

    public function guardarStockMinimo(int $productoId): void
    {
        $this->authorize('editar-stock-minimo');

        $nuevoValor = $this->stockMinimoEdit[$productoId] ?? null;

        $this->validate([
            'stockMinimoEdit.'.$productoId => ['required', 'integer', 'min:0'],
        ], [
            'stockMinimoEdit.*.required' => 'El stock mínimo es obligatorio.',
            'stockMinimoEdit.*.integer' => 'Debe ser un número entero.',
            'stockMinimoEdit.*.min' => 'No puede ser negativo.',
        ]);

        $producto = Producto::findOrFail($productoId);
        (new ProductoRepository)->update($producto, ['stock_minimo' => $nuevoValor]);

        unset($this->productos);
    }

    public function render()
    {
        return view('livewire.producto-lista');
    }
}
