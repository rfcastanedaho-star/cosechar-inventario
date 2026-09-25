<?php

namespace App\Livewire;

use App\Models\Producto;
use App\Models\StockMovimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DashboardGraficos extends Component
{
    public function actualizar(): void
    {
        $this->dispatch(
            'dashboard-actualizado',
            stockPorCategoria: $this->calcularStockPorCategoria(),
            movimientos: $this->calcularMovimientos30Dias(),
        );
    }

    public function with(): array
    {
        return [
            'stockPorCategoria' => $this->calcularStockPorCategoria(),
            'movimientos' => $this->calcularMovimientos30Dias(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, valores: array<int, int>}
     */
    private function calcularStockPorCategoria(): array
    {
        $grupos = Producto::with('categoria')
            ->withSum('lotes as stock_actual', 'cantidad')
            ->get()
            ->groupBy(fn (Producto $producto) => $producto->categoria->nombre)
            ->map(fn (Collection $productos) => (int) $productos->sum(fn (Producto $p) => $p->stock_actual ?? 0))
            ->sortDesc();

        return [
            'labels' => $grupos->keys()->values()->all(),
            'valores' => $grupos->values()->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, entradas: array<int, int>, salidas: array<int, int>}
     */
    private function calcularMovimientos30Dias(): array
    {
        $desde = now()->subDays(29)->startOfDay();

        $movimientos = StockMovimiento::where('fecha', '>=', $desde)->get();

        $dias = collect(range(0, 29))->map(fn (int $i) => now()->subDays(29 - $i)->format('Y-m-d'));

        $entradas = $dias->map(
            fn (string $dia) => (int) $movimientos
                ->where('tipo', 'entrada')
                ->filter(fn ($m) => $m->fecha->format('Y-m-d') === $dia)
                ->sum('cantidad')
        );

        $salidas = $dias->map(
            fn (string $dia) => (int) $movimientos
                ->where('tipo', 'salida')
                ->filter(fn ($m) => $m->fecha->format('Y-m-d') === $dia)
                ->sum('cantidad')
        );

        return [
            'labels' => $dias->map(fn (string $d) => Carbon::parse($d)->format('d/m'))->all(),
            'entradas' => $entradas->all(),
            'salidas' => $salidas->all(),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard-graficos');
    }
}
