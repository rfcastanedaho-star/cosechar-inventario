<?php

namespace App\Repositories;

use App\Models\Lote;
use Illuminate\Database\Eloquent\Collection;

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
        $lote = Lote::create($data);

        $lote->update(['codigo_qr' => route('lotes.consulta', $lote)]);

        return $lote;
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
