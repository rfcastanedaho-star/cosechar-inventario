<?php

namespace App\Repositories;

use App\Models\Proveedor;
use App\Repositories\Concerns\EscapaComodinesLike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProveedorRepository
{
    use EscapaComodinesLike;

    public function all(): Collection
    {
        return Proveedor::orderBy('nombre')->get();
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

        return Proveedor::query()
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("ruc LIKE ? ESCAPE '!'", [$patron])))
            ->when(($filtros['estado'] ?? null) === 'activo', fn ($q) => $q->where('activo', true))
            ->when(($filtros['estado'] ?? null) === 'inactivo', fn ($q) => $q->where('activo', false))
            ->orderBy('nombre')
            ->paginate($porPagina);
    }

    /**
     * Proveedores cuyo nombre o RUC contienen el texto escrito, para el buscador
     * por iniciales: primero los que empiezan igual, luego el resto. Los
     * comodines de LIKE (% y _) se buscan como texto literal.
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

        return Proveedor::query()
            ->when($soloActivos, fn ($q) => $q->where('activo', true))
            ->where(fn ($q) => $q
                ->whereRaw("nombre LIKE ? ESCAPE '!'", [$contiene])
                ->orWhereRaw("ruc LIKE ? ESCAPE '!'", [$contiene]))
            ->orderByRaw(
                "CASE WHEN nombre LIKE ? ESCAPE '!' THEN 0 WHEN ruc LIKE ? ESCAPE '!' THEN 1 ELSE 2 END",
                [$empieza, $empieza],
            )
            ->orderBy('nombre')
            ->limit($limite)
            ->get();
    }

    /**
     * Proveedor para una compra: el que ya existe (aunque esté desactivado) o uno nuevo.
     * Con RUC se busca por RUC; si no existe y hay un proveedor del mismo nombre sin RUC,
     * se completa ese en vez de duplicarlo. Sin RUC se busca por el mismo nombre.
     * Nunca se pisan los datos de un proveedor ya registrado.
     */
    public function registrarOReutilizar(string $nombre, ?string $ruc = null, ?string $telefono = null, ?string $correo = null, ?string $direccion = null): Proveedor
    {
        $nombre = trim($nombre);
        $ruc = trim((string) $ruc) ?: null;
        $telefono = trim((string) $telefono) ?: null;
        $correo = trim((string) $correo) ?: null;
        $direccion = trim((string) $direccion) ?: null;

        if ($ruc !== null) {
            if ($existente = Proveedor::where('ruc', $ruc)->first()) {
                return $existente;
            }

            $sinRuc = Proveedor::whereNull('ruc')->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();

            if ($sinRuc) {
                $sinRuc->update([
                    'ruc' => $ruc,
                    'telefono' => $sinRuc->telefono ?? $telefono,
                    'correo' => $sinRuc->correo ?? $correo,
                    'direccion' => $sinRuc->direccion ?? $direccion,
                ]);

                return $sinRuc;
            }
        } elseif ($existente = Proveedor::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->orderByDesc('activo')->orderBy('id')->first()) {
            return $existente;
        }

        return Proveedor::create([
            'nombre' => $nombre,
            'ruc' => $ruc,
            'telefono' => $telefono,
            'correo' => $correo,
            'direccion' => $direccion,
            'activo' => true,
        ]);
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
