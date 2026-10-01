<?php

namespace App\Repositories;

use App\Models\Venta;
use Illuminate\Database\Eloquent\Collection;

class VentaRepository
{
    public function all(): Collection
    {
        return Venta::with(['almacen', 'detalles.producto'])
            ->orderByDesc('fecha')
            ->get();
    }
}
