<?php

namespace App\Exceptions;

use App\Models\Producto;
use Exception;

class StockInsuficienteException extends Exception
{
    public static function paraProducto(Producto $producto, int $solicitado, int $disponible): self
    {
        return new self(
            "Stock insuficiente para el producto \"{$producto->nombre}\": se solicitaron {$solicitado} unidades y solo hay {$disponible} disponibles."
        );
    }
}
