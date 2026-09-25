<?php

namespace App\Repositories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Collection;

class ProductoRepository
{
    public function all(): Collection
    {
        return Producto::with('categoria')->get();
    }

    public function find(int $id): ?Producto
    {
        return Producto::with('categoria', 'lotes')->find($id);
    }

    public function findByCodigo(string $codigo): ?Producto
    {
        return Producto::where('codigo', $codigo)->first();
    }

    public function create(array $data): Producto
    {
        return Producto::create($data);
    }

    public function update(Producto $producto, array $data): Producto
    {
        $producto->update($data);

        return $producto;
    }

    public function conStockActual(): Collection
    {
        return Producto::with('categoria')->withSum('lotes as stock_actual', 'cantidad')->get();
    }

    public function conStockActualPorId(int $id): ?Producto
    {
        return Producto::withSum('lotes as stock_actual', 'cantidad')->find($id);
    }
}
