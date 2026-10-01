<?php

namespace App\Repositories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Collection;

class ProveedorRepository
{
    public function all(): Collection
    {
        return Proveedor::orderBy('nombre')->get();
    }

    public function find(int $id): ?Proveedor
    {
        return Proveedor::find($id);
    }

    public function create(array $data): Proveedor
    {
        return Proveedor::create($data);
    }
}
