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
        return Producto::with('categoria')
            ->withSum('lotes as stock_actual', 'cantidad')
            ->withMax('imagen as imagen_version', 'updated_at')
            ->get();
    }

    /**
     * Productos cuyo nombre o código contienen el texto escrito, para el
     * buscador por iniciales: primero los que empiezan igual, luego el resto.
     * Los comodines de LIKE (% y _) se buscan como texto literal.
     */
    public function buscar(string $termino, int $limite = 8): Collection
    {
        $termino = trim($termino);

        if ($termino === '') {
            return new Collection;
        }

        $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino);
        $contiene = '%'.$literal.'%';
        $empieza = $literal.'%';

        return Producto::query()
            ->where(fn ($q) => $q
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$contiene])
                ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$contiene]))
            ->orderByRaw(
                "CASE WHEN nombre LIKE ? ESCAPE '!' THEN 0 WHEN codigo LIKE ? ESCAPE '!' THEN 1 ELSE 2 END",
                [$empieza, $empieza],
            )
            ->orderBy('nombre')
            ->limit($limite)
            ->get();
    }

    public function conStockActualPorId(int $id): ?Producto
    {
        return Producto::withSum('lotes as stock_actual', 'cantidad')->find($id);
    }
}
