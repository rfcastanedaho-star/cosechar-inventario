<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\Producto;
use App\Repositories\LoteRepository;
use App\Repositories\ProductoRepository;
use Illuminate\Support\Collection;

class AlertaService
{
    public function __construct(
        private ProductoRepository $productos,
        private LoteRepository $lotes,
    ) {}

    /**
     * Productos cuyo stock actual (suma de sus lotes) está por debajo
     * del stock mínimo configurado.
     *
     * @return Collection<int, Producto>
     */
    public function productosConStockMinimo(): Collection
    {
        return $this->productos->conStockActual()
            ->filter(fn (Producto $producto) => ($producto->stock_actual ?? 0) < $producto->stock_minimo)
            ->values();
    }

    /**
     * Lotes con vencimiento próximo (dentro de los próximos $dias, incluye
     * lotes ya vencidos), que aún tienen cantidad disponible.
     *
     * @return Collection<int, Lote>
     */
    public function lotesPorVencer(int $dias = 30): Collection
    {
        return $this->lotes->porVencerEnDias($dias);
    }
}
