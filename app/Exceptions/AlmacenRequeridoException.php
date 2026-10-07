<?php

namespace App\Exceptions;

use Exception;

class AlmacenRequeridoException extends Exception
{
    /**
     * @param  array<int, string>  $detalle  Ej.: ['Zona Norte (100)', 'Zona Sur (5)']
     */
    public static function conStockEnVarios(array $detalle): self
    {
        return new self(
            'Indique almacen_id: el producto tiene stock en varios almacenes ('.implode(', ', $detalle).').'
        );
    }
}
