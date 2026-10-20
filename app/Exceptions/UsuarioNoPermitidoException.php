<?php

namespace App\Exceptions;

use Exception;

class UsuarioNoPermitidoException extends Exception
{
    public static function autoDesactivacion(): self
    {
        return new self('No puedes desactivar tu propio usuario.');
    }

    public static function autoDegradacion(): self
    {
        return new self('No puedes quitarte a ti mismo el rol de administrador.');
    }

    public static function ultimoAdministrador(): self
    {
        return new self('Debe quedar al menos un administrador activo en el sistema.');
    }

    public static function passwordActualIncorrecta(): self
    {
        return new self('La contraseña actual no es correcta.');
    }
}
