<?php

namespace App\Exceptions;

use Exception;

class CategoriaNoPermitidaException extends Exception
{
    /** Campo del formulario al que se asocia el error. */
    public string $campo = 'nombre';

    public static function nombreRepetido(): self
    {
        return new self('Ya existe una categoría con ese nombre en este grupo.');
    }

    public static function padreInvalido(): self
    {
        return self::enCampo('categoria_padre_id', 'El grupo elegido no existe o no es un grupo principal.');
    }

    public static function padreConProductos(): self
    {
        return self::enCampo('categoria_padre_id', 'No se puede crear una subcategoría ahí: esa categoría ya tiene productos asignados.');
    }

    public static function conProductos(): self
    {
        return new self('No se puede eliminar: la categoría tiene productos asignados.');
    }

    public static function conSubcategorias(): self
    {
        return new self('No se puede eliminar: el grupo tiene subcategorías.');
    }

    private static function enCampo(string $campo, string $mensaje): self
    {
        $excepcion = new self($mensaje);
        $excepcion->campo = $campo;

        return $excepcion;
    }
}
