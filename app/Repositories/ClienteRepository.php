<?php

namespace App\Repositories;

use App\Models\Cliente;
use App\Repositories\Concerns\EscapaComodinesLike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ClienteRepository
{
    use EscapaComodinesLike;

    /**
     * Clientes cuyo nombre, documento o teléfono contienen el texto escrito, para
     * el buscador de ventas: primero los que empiezan igual, luego el resto.
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

        return Cliente::query()
            ->when($soloActivos, fn ($q) => $q->where('activo', true))
            ->where(fn ($q) => $q
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$contiene])
                ->orWhereRaw("documento LIKE ? ESCAPE '!'", [$contiene])
                ->orWhereRaw("telefono LIKE ? ESCAPE '!'", [$contiene]))
            ->orderByRaw(
                "CASE WHEN nombre LIKE ? ESCAPE '!' THEN 0 WHEN documento LIKE ? ESCAPE '!' THEN 1 ELSE 2 END",
                [$empieza, $empieza],
            )
            ->orderBy('nombre')
            ->limit($limite)
            ->get();
    }

    /**
     * Listado paginado en orden alfabético.
     *
     * @param  array{buscar?: ?string, estado?: ?string}  $filtros  estado: activo | inactivo
     */
    public function historial(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $patron = '%'.$this->textoLiteral($buscar).'%';

        return Cliente::query()
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("documento LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("telefono LIKE ? ESCAPE '!'", [$patron])))
            ->when(($filtros['estado'] ?? null) === 'activo', fn ($q) => $q->where('activo', true))
            ->when(($filtros['estado'] ?? null) === 'inactivo', fn ($q) => $q->where('activo', false))
            ->orderBy('nombre')
            ->paginate($porPagina);
    }

    /**
     * Cliente con ese documento: el que ya existe (aunque esté desactivado) o uno
     * nuevo con el nombre dado. No pisa los datos de uno existente.
     */
    public function registrarOReutilizar(string $nombre, string $documento): Cliente
    {
        return Cliente::firstOrCreate(
            ['documento' => $documento],
            ['nombre' => $nombre, 'activo' => true],
        );
    }

    public function create(array $data): Cliente
    {
        return Cliente::create($data);
    }
}
