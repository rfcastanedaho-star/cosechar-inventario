<?php

namespace App\Repositories;

use App\Models\Almacen;
use App\Repositories\Concerns\EscapaComodinesLike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AlmacenRepository
{
    use EscapaComodinesLike;

    public function all(): Collection
    {
        return Almacen::withCount('lotes')->orderBy('nombre')->get();
    }

    /**
     * Listado paginado en orden alfabético, con la cantidad de lotes de cada almacén.
     *
     * @param  array{buscar?: ?string, estado?: ?string}  $filtros  estado: activo | inactivo
     */
    public function historial(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $patron = '%'.$this->textoLiteral($buscar).'%';

        return Almacen::withCount('lotes')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("direccion LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("encargado LIKE ? ESCAPE '!'", [$patron])))
            ->when(($filtros['estado'] ?? null) === 'activo', fn ($q) => $q->where('activo', true))
            ->when(($filtros['estado'] ?? null) === 'inactivo', fn ($q) => $q->where('activo', false))
            ->orderBy('nombre')
            ->paginate($porPagina);
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
