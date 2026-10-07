<?php

namespace App\Repositories;

use App\Models\Lote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LoteRepository
{
    public function porProducto(int $productoId): Collection
    {
        return Lote::where('producto_id', $productoId)->get();
    }

    public function buscarPorVencimiento(int $productoId, ?string $fechaVencimiento): ?Lote
    {
        return Lote::where('producto_id', $productoId)
            ->where('fecha_vencimiento', $fechaVencimiento)
            ->first();
    }

    public function ordenadosParaSalida(int $productoId, bool $manejaVencimiento): Collection
    {
        $query = Lote::where('producto_id', $productoId)->where('cantidad', '>', 0);

        return $manejaVencimiento
            ? $query->orderBy('fecha_vencimiento', 'asc')->get()   // FEFO
            : $query->orderBy('fecha_ingreso', 'asc')->get();       // FIFO
    }

    public function create(array $data): Lote
    {
        return Lote::create($data);
    }

    public function decrementar(Lote $lote, int $cantidad): void
    {
        $lote->decrement('cantidad', $cantidad);
    }

    public function incrementar(Lote $lote, int $cantidad): void
    {
        $lote->increment('cantidad', $cantidad);
    }

    public function porVencerEnDias(int $dias): Collection
    {
        return Lote::whereHas('producto', fn ($q) => $q->where('maneja_vencimiento', true))
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', now()->addDays($dias))
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    /**
     * Lotes con stock disponible, desagregados por producto y lote (RF-08),
     * opcionalmente de un solo almacén.
     */
    public function conStock(?int $almacenId = null): Collection
    {
        return Lote::with('producto.categoria', 'almacen')
            ->where('cantidad', '>', 0)
            ->when($almacenId, fn ($q) => $q->where('almacen_id', $almacenId))
            ->get()
            ->sortBy([['producto.nombre', 'asc'], ['fecha_vencimiento', 'asc']])
            ->values();
    }

    /**
     * Unidades disponibles de un producto agrupadas por almacén.
     *
     * @return Collection<int, Lote> cada elemento trae almacen_id, unidades y la relación almacen
     */
    public function stockPorAlmacen(int $productoId): Collection
    {
        return Lote::with('almacen')
            ->select('almacen_id', DB::raw('SUM(cantidad) as unidades'))
            ->where('producto_id', $productoId)
            ->where('cantidad', '>', 0)
            ->whereNotNull('almacen_id')
            ->groupBy('almacen_id')
            ->orderBy('almacen_id')
            ->get();
    }

    public function contarActivos(): int
    {
        return Lote::where('cantidad', '>', 0)->count();
    }

    public function ordenadosParaSalidaEnAlmacen(int $productoId, int $almacenId, bool $manejaVencimiento): Collection
    {
        $query = Lote::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->where('cantidad', '>', 0);

        return $manejaVencimiento
            ? $query->orderBy('fecha_vencimiento', 'asc')->get()   // FEFO
            : $query->orderBy('fecha_ingreso', 'asc')->get();       // FIFO
    }
}
