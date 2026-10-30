<?php

namespace Tests\Unit;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\AuditoriaEtiquetas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuditoriaEtiquetasTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function entidades(): array
    {
        return [
            'producto' => [Producto::class, 'Producto'],
            'almacen' => [Almacen::class, 'Almacén'],
            'proveedor' => [Proveedor::class, 'Proveedor'],
            'usuario' => [User::class, 'Usuario'],
            'desconocida' => ['App\\Models\\Cosa', 'Cosa'],
        ];
    }

    #[DataProvider('entidades')]
    public function test_nombre_legible_de_cada_entidad(string $clase, string $esperado): void
    {
        $this->assertSame($esperado, AuditoriaEtiquetas::entidad($clase));
    }

    /** @return array<string, array{string, string}> */
    public static function campos(): array
    {
        return [
            'codigo' => ['codigo', 'Código'],
            'nombre' => ['nombre', 'Nombre'],
            'name' => ['name', 'Nombre'],
            'email' => ['email', 'Correo'],
            'rol' => ['rol', 'Rol'],
            'activo' => ['activo', 'Estado'],
            'categoria' => ['categoria_id', 'Categoría'],
            'unidad' => ['unidad_medida', 'Unidad de medida'],
            'stock minimo' => ['stock_minimo', 'Stock mínimo'],
            'precio' => ['precio', 'Precio'],
            'vencimiento' => ['maneja_vencimiento', 'Usa lotes'],
            'direccion' => ['direccion', 'Dirección'],
            'password' => ['password', 'Contraseña'],
            'desconocido' => ['otro_campo_raro', 'Otro Campo Raro'],
        ];
    }

    #[DataProvider('campos')]
    public function test_nombre_legible_de_cada_campo(string $campo, string $esperado): void
    {
        $this->assertSame($esperado, AuditoriaEtiquetas::campo($campo));
    }

    /** @return array<string, array{string, ?string, string}> */
    public static function valores(): array
    {
        return [
            'nulo' => ['nombre', null, '—'],
            'activo' => ['activo', '1', 'Activo'],
            'inactivo' => ['activo', '0', 'Inactivo'],
            'vence si' => ['maneja_vencimiento', '1', 'Sí'],
            'vence no' => ['maneja_vencimiento', '0', 'No'],
            'precio' => ['precio', '45.5', 'S/ 45.50'],
            'precio entero' => ['precio', '40', 'S/ 40.00'],
            'rol admin' => ['rol', 'administrador', 'Administrador'],
            'rol operador' => ['rol', 'operador_almacen', 'Operador de almacén'],
            'unidad' => ['unidad_medida', 'galon', 'Galón'],
            'unidad kg' => ['unidad_medida', 'kg', 'Kilogramo'],
            'texto' => ['nombre', 'Fungicida Cobre', 'Fungicida Cobre'],
            'desconocido' => ['otro', 'xyz', 'xyz'],
        ];
    }

    #[DataProvider('valores')]
    public function test_formato_legible_de_cada_valor(string $campo, ?string $valor, string $esperado): void
    {
        $this->assertSame($esperado, AuditoriaEtiquetas::valor($campo, $valor));
    }

    public function test_la_categoria_se_muestra_por_su_nombre_y_si_no_existe_por_su_numero(): void
    {
        $this->assertSame('Fungicida', AuditoriaEtiquetas::valor('categoria_id', '3', [3 => 'Fungicida']));
        $this->assertSame('#99', AuditoriaEtiquetas::valor('categoria_id', '99', [3 => 'Fungicida']));
    }
}
