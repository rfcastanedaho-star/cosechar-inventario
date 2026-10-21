<?php

namespace App\Support;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Convierte lo que guarda la auditoría (clases, columnas y valores crudos)
 * en texto que entiende quien administra el negocio.
 */
class AuditoriaEtiquetas
{
    /** Clave corta del filtro => clase auditada. */
    public const ENTIDADES = [
        'producto' => Producto::class,
        'almacen' => Almacen::class,
        'proveedor' => Proveedor::class,
        'usuario' => User::class,
        'cliente' => Cliente::class,
    ];

    private const NOMBRES_ENTIDAD = [
        Producto::class => 'Producto',
        Almacen::class => 'Almacén',
        Proveedor::class => 'Proveedor',
        User::class => 'Usuario',
        Cliente::class => 'Cliente',
    ];

    private const CAMPOS = [
        'codigo' => 'Código',
        'documento' => 'Documento',
        'nombre' => 'Nombre',
        'name' => 'Nombre',
        'email' => 'Correo',
        'rol' => 'Rol',
        'activo' => 'Estado',
        'categoria_id' => 'Categoría',
        'unidad_medida' => 'Unidad de medida',
        'stock_minimo' => 'Stock mínimo',
        'precio' => 'Precio',
        'maneja_vencimiento' => 'Maneja vencimiento',
        'direccion' => 'Dirección',
        'telefono' => 'Teléfono',
        'password' => 'Contraseña',
    ];

    private const UNIDADES = [
        'saco' => 'Saco',
        'kg' => 'Kilogramo',
        'litro' => 'Litro',
        'galon' => 'Galón',
        'unidad' => 'Unidad',
    ];

    public static function entidad(string $clase): string
    {
        return self::NOMBRES_ENTIDAD[$clase] ?? class_basename($clase);
    }

    public static function campo(string $campo): string
    {
        return self::CAMPOS[$campo] ?? Str::headline($campo);
    }

    /**
     * @param  array<int, string>  $categorias  id => nombre, para resolver categoria_id
     */
    public static function valor(string $campo, ?string $valor, array $categorias = []): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($campo) {
            'activo' => $valor === '1' ? 'Activo' : 'Inactivo',
            'maneja_vencimiento' => $valor === '1' ? 'Sí' : 'No',
            'precio' => is_numeric($valor) ? 'S/ '.number_format((float) $valor, 2, '.', '') : $valor,
            'rol' => User::ROLES[$valor] ?? $valor,
            'unidad_medida' => self::UNIDADES[$valor] ?? $valor,
            'categoria_id' => $categorias[(int) $valor] ?? '#'.$valor,
            default => $valor,
        };
    }

    /** Cómo se nombra el registro afectado; null si ya fue eliminado. */
    public static function registro(?Model $modelo): string
    {
        if ($modelo === null) {
            return '(registro eliminado)';
        }

        return match (true) {
            $modelo instanceof Producto => $modelo->codigo.' — '.$modelo->nombre,
            $modelo instanceof User => $modelo->name,
            default => (string) ($modelo->nombre ?? $modelo->name ?? '#'.$modelo->getKey()),
        };
    }
}
