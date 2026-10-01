<?php

namespace App\Repositories;

use App\Models\StockMovimiento;
use Illuminate\Database\Eloquent\Collection;

class StockMovimientoRepository
{
    public function create(array $data): StockMovimiento
    {
        return StockMovimiento::create($data);
    }

    public function porFiltros(?string $fecha, ?int $productoId, ?string $tipo): Collection
    {
        return StockMovimiento::with('lote.producto')
            ->when($fecha, fn ($q) => $q->whereDate('fecha', $fecha))
            ->when($productoId, fn ($q) => $q->whereHas('lote', fn ($q2) => $q2->where('producto_id', $productoId)))
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function contarHoy(): int
    {
        return StockMovimiento::whereDate('fecha', today())->count();
    }

    public function ultimo(): ?StockMovimiento
    {
        return StockMovimiento::latest('fecha')->first();
    }
}
