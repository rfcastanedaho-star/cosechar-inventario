<?php

namespace App\Repositories;

use App\Models\Venta;
use Illuminate\Database\Eloquent\Collection;

class VentaRepository
{
    public function all(): Collection
    {
        return Venta::with(['almacen', 'detalles.producto', 'anuladaPor'])
            ->orderByDesc('fecha')
            ->get();
    }

    public function porFiltros(?string $desde, ?string $hasta, ?int $almacenId): Collection
    {
        return Venta::with(['almacen'])
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->when($almacenId, fn ($q) => $q->where('almacen_id', $almacenId))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{total: float, cantidad: int}
     */
    public function resumenDelMes(): array
    {
        $vigentes = Venta::whereNull('anulada_at')
            ->whereYear('fecha', now()->year)
            ->whereMonth('fecha', now()->month);

        return ['total' => (float) $vigentes->sum('total'), 'cantidad' => $vigentes->count()];
    }
}
