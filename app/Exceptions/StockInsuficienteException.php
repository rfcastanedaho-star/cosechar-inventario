<?php

namespace App\Exceptions;

use App\Models\Lote;
use App\Models\Producto;
use Exception;

class StockInsuficienteException extends Exception
{
    public static function paraLote(Lote $lote, int $solicitado, int $disponible): self
    {
        return new self(
            "El lote {$lote->numero_lote} solo tiene {$disponible} unidades; no se pueden descontar {$solicitado}."
        );
    }

    /** No alcanza con lo vigente: hay unidades, pero en lotes vencidos, que no se venden. */
    public static function conVencidos(Producto $producto, int $solicitado, int $disponible, int $vencidas): self
    {
        return new self(
            "Stock vigente insuficiente para el producto \"{$producto->nombre}\": se solicitaron {$solicitado} unidades y solo hay {$disponible} vigentes. "
            ."Hay {$vencidas} unidades en lotes vencidos, que no se pueden vender: regístrelas en Lotes como «Producto vencido»."
        );
    }

    public static function paraProducto(Producto $producto, int $solicitado, int $disponible): self
    {
        return new self(
            "Stock insuficiente para el producto \"{$producto->nombre}\": se solicitaron {$solicitado} unidades y solo hay {$disponible} disponibles."
        );
    }
}
