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

    /** Líneas cuya lista desplegable de productos está abierta (flechita o foco en el campo). @var array<int, bool> */
    public array $listaProducto = [];

    /** @return Collection<int, Producto> */
    public function productosSugeridos(int $indice): Collection
    {
        $texto = $this->busquedaProducto[$indice] ?? '';

        // Sin texto, la lista solo se muestra si se abrió con la flechita: entonces ofrece todos, por orden alfabético.
        if (trim($texto) === '' && ! ($this->listaProducto[$indice] ?? false)) {
            return new Collection;
        }

        return app(ProductoRepository::class)->sugerir($texto);
    }

    public function alternarListaProducto(int $indice): void
    {
        $this->listaProducto[$indice] = ! ($this->listaProducto[$indice] ?? false);
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
        $this->listaProducto[$indice] = false;
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
        $this->listaProducto[$indice] = false;
    }

    /** Mantiene los textos de búsqueda alineados con las líneas cuando se quita una. */
    protected function quitarBusquedaDeLinea(int $indice): void
    {
        unset($this->busquedaProducto[$indice]);
        $this->busquedaProducto = array_values($this->busquedaProducto);
        $this->listaProducto = [];
    }

    /** Gancho para que cada formulario complete datos al elegir el producto. */
    protected function alElegirProducto(int $indice, Producto $producto): void {}
}
