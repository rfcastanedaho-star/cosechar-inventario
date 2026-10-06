<?php

namespace App\Exceptions;

use Exception;

class AnulacionNoPermitidaException extends Exception
{
    public static function yaAnulada(): self
    {
        return new self('Este registro ya fue anulado.');
    }

    public static function stockYaUtilizado(string $numeroLote, int $comprado, int $disponible): self
    {
        return new self(
            "No se puede anular: el lote {$numeroLote} tenía {$comprado} unidades compradas pero hoy quedan {$disponible}. Parte de ese stock ya salió."
        );
    }

    public static function sinTrazabilidad(): self
    {
        return new self('Esta venta es anterior al control de trazabilidad de stock y no se puede anular automáticamente.');
    }
}
