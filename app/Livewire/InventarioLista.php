<?php

namespace App\Livewire;

use App\Models\Producto;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Inventario: el stock de cada producto (suma de sus lotes, que se abren para verlos) y, en la
 * otra pestaña, el kardex con todos los movimientos. Compras, ventas y los ajustes de Lotes
 * escriben aquí; no se registra nada desde esta pantalla.
 */
#[Layout('layouts.app')]
class InventarioLista extends Component
{
    public const PESTANAS = ['stock', 'kardex'];

    public const ESTADOS = ['sin_stock' => 'Sin stock', 'bajo' => 'Stock bajo', 'normal' => 'Normal'];

    #[Url]
    public string $tab = 'stock';

    #[Url(as: 'q')]
    public string $buscar = '';

    #[Url]
    public string $estado = '';

    public ?int $abierto = null;

    public function updatedTab(): void
    {
        $this->tab = $this->pestanaValida();
    }

    private function pestanaValida(): string
    {
        return in_array($this->tab, self::PESTANAS, true) ? $this->tab : 'stock';
    }

    public function abrir(int $productoId): void
    {
        $this->abierto = $this->abierto === $productoId ? null : $productoId;
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'estado']);
    }

    public function hayFiltros(): bool
    {
        return trim($this->buscar) !== '' || $this->estado !== '';
    }

    /** sin_stock, bajo o normal, según las unidades que suman sus lotes y el stock mínimo. */
    public static function estadoDe(Producto $producto): string
    {
        $stock = (int) ($producto->stock_actual ?? 0);

        return match (true) {
            $stock <= 0 => 'sin_stock',
            $stock < $producto->stock_minimo => 'bajo',
            default => 'normal',
        };
    }

    /** Todos los productos activos con su stock; los lotes que se cargan son solo los que tienen unidades. */
    private function todos(): Collection
    {
        return Producto::with(['categoria', 'lotes' => fn ($q) => $q->where('cantidad', '>', 0)->orderBy('fecha_vencimiento')->orderBy('id')])
            ->withSum('lotes as stock_actual', 'cantidad')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function productos(): Collection
    {
        $buscar = mb_strtolower(trim($this->buscar));
        $estado = array_key_exists($this->estado, self::ESTADOS) ? $this->estado : null;

        return $this->todos()
            ->when($buscar !== '', fn (Collection $productos) => $productos->filter(
                fn (Producto $p) => str_contains(mb_strtolower($p->nombre), $buscar)
                    || str_contains(mb_strtolower($p->codigo), $buscar)
                    || str_contains(mb_strtolower((string) $p->marca), $buscar)
            ))
            ->when($estado !== null, fn (Collection $productos) => $productos->filter(fn (Producto $p) => self::estadoDe($p) === $estado))
            ->values();
    }

    /** @return array{productos: int, unidades: int, valor: float, bajo: int} */
    #[Computed]
    public function resumen(): array
    {
        $todos = $this->todos();

        return [
            'productos' => $todos->count(),
            'unidades' => (int) $todos->sum('stock_actual'),
            'valor' => (float) $todos->sum(fn (Producto $p) => $p->lotes->sum(fn ($lote) => $lote->cantidad * (float) $lote->costo_unitario)),
            'bajo' => $todos->filter(fn (Producto $p) => self::estadoDe($p) === 'bajo')->count(),
        ];
    }

    public function render()
    {
        $this->tab = $this->pestanaValida();

        return view('livewire.inventario-lista');
    }
}
