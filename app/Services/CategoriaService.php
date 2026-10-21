<?php

namespace App\Services;

use App\Exceptions\CategoriaNoPermitidaException;
use App\Models\AuditoriaCambio;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cuida el catálogo de categorías: solo hay dos niveles (grupo y subcategoría),
 * un producto solo se asigna a una categoría sin hijas, los nombres no se
 * repiten dentro de un mismo grupo y no se borra nada que esté en uso.
 */
class CategoriaService
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function crear(string $nombre, ?int $padreId, User $actor): Categoria
    {
        $nombre = trim($nombre);

        if ($padreId !== null) {
            $padre = Categoria::find($padreId);

            if ($padre === null || $padre->categoria_padre_id !== null) {
                throw CategoriaNoPermitidaException::padreInvalido();
            }

            if ($padre->productos()->exists()) {
                throw CategoriaNoPermitidaException::padreConProductos();
            }
        }

        $this->asegurarNombreLibre($nombre, $padreId, null);

        return Categoria::create(['nombre' => $nombre, 'categoria_padre_id' => $padreId]);
    }

    public function renombrar(Categoria $categoria, string $nombre, User $actor): Categoria
    {
        $nombre = trim($nombre);

        $this->asegurarNombreLibre($nombre, $categoria->categoria_padre_id, $categoria->id);

        $this->auditoria->actualizar($categoria, ['nombre' => $nombre], $actor);

        return $categoria->refresh();
    }

    public function eliminar(Categoria $categoria, User $actor): void
    {
        if ($categoria->subcategorias()->exists()) {
            throw CategoriaNoPermitidaException::conSubcategorias();
        }

        if ($categoria->productos()->exists()) {
            throw CategoriaNoPermitidaException::conProductos();
        }

        DB::transaction(function () use ($categoria, $actor) {
            AuditoriaCambio::create([
                'auditable_type' => Categoria::class,
                'auditable_id' => $categoria->id,
                'user_id' => $actor->id,
                'campo' => 'nombre',
                'valor_anterior' => $categoria->nombre,
                'valor_nuevo' => null,
            ]);

            $categoria->delete();
        });
    }

    private function asegurarNombreLibre(string $nombre, ?int $padreId, ?int $ignorarId): void
    {
        $repetido = Categoria::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->when($padreId === null, fn ($q) => $q->whereNull('categoria_padre_id'), fn ($q) => $q->where('categoria_padre_id', $padreId))
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->exists();

        if ($repetido) {
            throw CategoriaNoPermitidaException::nombreRepetido();
        }
    }
}
