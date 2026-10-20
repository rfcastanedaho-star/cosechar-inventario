<?php

namespace App\Livewire\Concerns;

use App\Models\Producto;
use App\Repositories\ProductoRepository;
use Illuminate\Support\Collection;

/**
 * Buscador de producto por línea (nombre o código) para los formularios que
 * arman una lista de productos: cada línea tiene su propio texto de búsqueda
 * y, al elegir una sugerencia, queda fijada en `lineas.{i}.producto_id`.
 */
trait BuscaProductoPorLinea
{
    /** @var array<int, string> */
    public array $busquedaProducto = [];

    /** @return Collection<int, Producto> */
    public function productosSugeridos(int $indice): Collection
    {
        return app(ProductoRepository::class)->buscar($this->busquedaProducto[$indice] ?? '');
    }

    public function productoDeLinea(int $indice): ?Producto
    {
        $id = $this->lineas[$indice]['producto_id'] ?? null;

        return $id ? Producto::find($id) : null;
    }

    public function elegirProductoLinea(int $indice, int $productoId): void
    {
        if (! isset($this->lineas[$indice])) {
            return;
        }

        $producto = Producto::where('activo', true)->find($productoId);

        if (! $producto) {
            return;
        }

        $this->lineas[$indice]['producto_id'] = $producto->id;
        $this->busquedaProducto[$indice] = '';
        $this->resetErrorBag("lineas.{$indice}.producto_id");
        $this->alElegirProducto($indice, $producto);
    }

    /** Enter en el buscador: elige la primera sugerencia de esa línea. */
    public function elegirPrimeroLinea(int $indice): void
    {
        $primero = $this->productosSugeridos($indice)->first();

        if ($primero) {
            $this->elegirProductoLinea($indice, $primero->id);
        }
    }

    public function limpiarProductoLinea(int $indice): void
    {
        if (isset($this->lineas[$indice])) {
            $this->lineas[$indice]['producto_id'] = null;
        }

        $this->busquedaProducto[$indice] = '';
    }

    /** Mantiene los textos de búsqueda alineados con las líneas cuando se quita una. */
    protected function quitarBusquedaDeLinea(int $indice): void
    {
        unset($this->busquedaProducto[$indice]);
        $this->busquedaProducto = array_values($this->busquedaProducto);
    }

    /** Gancho para que cada formulario complete datos al elegir el producto. */
    protected function alElegirProducto(int $indice, Producto $producto): void {}
}
