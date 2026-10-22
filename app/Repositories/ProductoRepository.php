<?php

namespace App\Repositories;

use App\Models\Producto;
use App\Repositories\Concerns\EscapaComodinesLike;
use Illuminate\Database\Eloquent\Collection;

class ProductoRepository
{
    use EscapaComodinesLike;

    public function all(): Collection
    {
        return Producto::with('categoria')->get();
    }

    public function find(int $id): ?Producto
    {
        return Producto::with('categoria', 'lotes')->find($id);
    }

    /** Siguiente código libre con el formato PRD-0001; ignora los códigos con otro formato. */
    public function siguienteCodigo(): string
    {
        $maximo = Producto::where('codigo', 'like', 'PRD-%')
            ->pluck('codigo')
            ->map(fn (string $codigo) => preg_match('/^PRD-(\d+)$/', $codigo, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'PRD-'.str_pad((string) ($maximo + 1), 4, '0', STR_PAD_LEFT);
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
     * Productos cuyo nombre, código, código de barras o marca contienen el texto escrito, para el
     * buscador por iniciales: primero los que empiezan igual, luego el resto.
     * Los comodines de LIKE (% y _) se buscan como texto literal.
     */
    public function buscar(string $termino, int $limite = 8, bool $soloActivos = true): Collection
    {
        $termino = trim($termino);

        if ($termino === '') {
            return new Collection;
        }

        $literal = $this->textoLiteral($termino);
        $contiene = '%'.$literal.'%';
        $empieza = $literal.'%';

        return Producto::query()
            ->when($soloActivos, fn ($q) => $q->where('activo', true))
            ->where(fn ($q) => $q
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$contiene])
                ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$contiene])
                ->orWhereRaw("codigo_barras LIKE ? ESCAPE '!'", [$contiene])
                ->orWhereRaw("marca LIKE ? ESCAPE '!'", [$contiene]))
            ->orderByRaw(
                "CASE WHEN codigo_barras = ? THEN 0 WHEN nombre LIKE ? ESCAPE '!' THEN 1 WHEN codigo LIKE ? ESCAPE '!' OR codigo_barras LIKE ? ESCAPE '!' THEN 2 ELSE 3 END",
                [$termino, $empieza, $empieza, $empieza],
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
