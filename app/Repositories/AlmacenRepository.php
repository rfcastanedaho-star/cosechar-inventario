<?php

namespace App\Repositories;

use App\Models\Almacen;
use Illuminate\Database\Eloquent\Collection;

class AlmacenRepository
{
    public function all(): Collection
    {
        return Almacen::withCount('lotes')->orderBy('nombre')->get();
    }

    public function conStock(): Collection
    {
        return Almacen::withSum('lotes as unidades', 'cantidad')->orderBy('nombre')->get();
    }

    public function find(int $id): ?Almacen
    {
        return Almacen::find($id);
    }

    public function create(array $data): Almacen
    {
        return Almacen::create($data);
    }
}
