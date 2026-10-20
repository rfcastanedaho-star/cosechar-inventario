<?php

namespace App\Repositories\Concerns;

/**
 * Para buscar texto con LIKE tratando % y _ como caracteres normales.
 * Se usa con "ESCAPE '!'" porque es el único escape válido igual en MySQL y SQLite.
 */
trait EscapaComodinesLike
{
    protected function textoLiteral(string $termino): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termino);
    }
}
