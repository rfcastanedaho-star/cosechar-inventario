<?php

namespace App\Livewire;

use App\Models\Producto;
use App\Repositories\ProductoRepository;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ProductoLista extends Component
{
    /** @var array<int, int> */
    public array $stockMinimoEdit = [];

    #[Url(as: 'buscar')]
    public string $buscar = '';

    public function mount(): void
    {
        $this->stockMinimoEdit = Producto::pluck('stock_minimo', 'id')->all();
    }

    #[Computed]
    public function productos(): Collection
    {
        $buscar = trim($this->buscar);

        return (new ProductoRepository)->conStockActual()
            ->when($buscar !== '', fn (Collection $productos) => $productos->filter(
                fn (Producto $producto) => str_contains(mb_strtolower($producto->nombre), mb_strtolower($buscar))
                    || str_contains(mb_strtolower($producto->codigo), mb_strtolower($buscar))
            ))
            ->sortBy('nombre')
            ->values();
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
