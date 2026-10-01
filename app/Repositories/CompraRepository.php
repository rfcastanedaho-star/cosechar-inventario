<?php

namespace App\Repositories;

use App\Models\Compra;
use Illuminate\Database\Eloquent\Collection;

class CompraRepository
{
    public function all(): Collection
    {
        return Compra::with(['proveedor', 'almacen', 'detalles.producto'])
            ->orderByDesc('fecha')
            ->get();
    }
}
