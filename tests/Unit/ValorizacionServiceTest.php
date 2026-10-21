<?php

namespace Tests\Unit;

use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Services\ValorizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValorizacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ValorizacionService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(ValorizacionService::class);
    }

    private function lote(Producto $producto, Almacen $almacen, int $cantidad, ?float $costo): Lote
    {
        return Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $almacen->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo,
        ]);
    }

    public function test_valoriza_un_producto_a_costo_y_a_precio_de_venta(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['precio' => 10]);
        $this->lote($producto, $almacen, 20, 6);

        $resultado = $this->servicio->calcular();
        $fila = $resultado['filas']->first();

        $this->assertCount(1, $resultado['filas']);
        $this->assertSame(20, $fila->unidades);
        $this->assertSame(6.0, $fila->costoPromedio);
        $this->assertSame(120.0, $fila->valorCosto);
        $this->assertSame(200.0, $fila->valorVenta);
        $this->assertSame(80.0, $fila->utilidad);
        $this->assertSame(0, $fila->unidadesSinCosto);
    }

    public function test_el_costo_promedio_se_pondera_por_la_cantidad_de_cada_lote(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['precio' => 10]);
        $this->lote($producto, $almacen, 10, 4);
        $this->lote($producto, $almacen, 30, 6);

        $fila = $this->servicio->calcular()['filas']->first();

        $this->assertSame(40, $fila->unidades);
        $this->assertSame(5.5, $fila->costoPromedio);
        $this->assertSame(220.0, $fila->valorCosto);
    }

    public function test_un_mismo_producto_en_dos_almacenes_da_dos_filas(): void
    {
        $producto = Producto::factory()->create(['precio' => 10]);
        $this->lote($producto, Almacen::factory()->create(['nombre' => 'Central']), 10, 5);
        $this->lote($producto, Almacen::factory()->create(['nombre' => 'Sur']), 4, 5);

        $filas = $this->servicio->calcular()['filas'];

        $this->assertSame(['Central', 'Sur'], $filas->map(fn ($f) => $f->almacen->nombre)->all());
        $this->assertSame([10, 4], $filas->pluck('unidades')->all());
    }

    public function test_ignora_los_lotes_agotados(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $this->lote($producto, $almacen, 0, 5);

        $resultado = $this->servicio->calcular();

        $this->assertCount(0, $resultado['filas']);
        $this->assertSame(0.0, $resultado['totales']['valorCosto']);
    }

    public function test_los_lotes_sin_costo_se_cuentan_aparte_y_no_inventan_valor(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['precio' => 10]);
        $this->lote($producto, $almacen, 10, 4);
        $this->lote($producto, $almacen, 5, null);

        $resultado = $this->servicio->calcular();
        $fila = $resultado['filas']->first();

        $this->assertSame(15, $fila->unidades);
        $this->assertSame(5, $fila->unidadesSinCosto);
        $this->assertSame(4.0, $fila->costoPromedio);
        $this->assertSame(40.0, $fila->valorCosto);
        $this->assertSame(150.0, $fila->valorVenta);
        // la utilidad solo considera las 10 unidades que sí tienen costo: 10 × 10 − 40
        $this->assertSame(60.0, $fila->utilidad);
        $this->assertFalse($resultado['totales']['completo']);
        $this->assertSame(5, $resultado['totales']['unidadesSinCosto']);
    }

    public function test_si_ningun_lote_tiene_costo_el_costo_promedio_es_nulo(): void
    {
        $producto = Producto::factory()->create(['precio' => 10]);
        $this->lote($producto, Almacen::factory()->create(), 8, null);

        $fila = $this->servicio->calcular()['filas']->first();

        $this->assertNull($fila->costoPromedio);
        $this->assertSame(0.0, $fila->valorCosto);
        $this->assertSame(0.0, $fila->utilidad);
        $this->assertSame(80.0, $fila->valorVenta);
    }

    public function test_filtra_por_almacen(): void
    {
        $producto = Producto::factory()->create(['precio' => 10]);
        $sur = Almacen::factory()->create(['nombre' => 'Sur']);
        $this->lote($producto, Almacen::factory()->create(['nombre' => 'Central']), 10, 5);
        $this->lote($producto, $sur, 4, 5);

        $resultado = $this->servicio->calcular($sur->id);

        $this->assertCount(1, $resultado['filas']);
        $this->assertSame(4, $resultado['totales']['unidades']);
    }

    public function test_calcula_los_totales_generales(): void
    {
        $almacen = Almacen::factory()->create();
        $a = Producto::factory()->create(['nombre' => 'A', 'precio' => 10]);
        $b = Producto::factory()->create(['nombre' => 'B', 'precio' => 20]);
        $this->lote($a, $almacen, 10, 4);
        $this->lote($b, $almacen, 5, 8);

        $totales = $this->servicio->calcular()['totales'];

        $this->assertSame(2, $totales['productos']);
        $this->assertSame(15, $totales['unidades']);
        $this->assertSame(80.0, $totales['valorCosto']);   // 40 + 40
        $this->assertSame(200.0, $totales['valorVenta']);  // 100 + 100
        $this->assertSame(120.0, $totales['utilidad']);    // 60 + 60
        $this->assertTrue($totales['completo']);
    }

    public function test_ordena_por_nombre_de_producto_y_luego_por_almacen(): void
    {
        $zeta = Producto::factory()->create(['nombre' => 'Zeta']);
        $alfa = Producto::factory()->create(['nombre' => 'Alfa']);
        $almacen = Almacen::factory()->create(['nombre' => 'Central']);
        $this->lote($zeta, $almacen, 1, 1);
        $this->lote($alfa, $almacen, 1, 1);

        $nombres = $this->servicio->calcular()['filas']->map(fn ($f) => $f->producto->nombre)->all();

        $this->assertSame(['Alfa', 'Zeta'], $nombres);
    }

    public function test_el_costo_promedio_se_redondea_a_dos_decimales_sin_perder_el_valor_exacto(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['precio' => 3.33]);
        $this->lote($producto, $almacen, 1, 1.00);
        $this->lote($producto, $almacen, 2, 1.50);

        $fila = $this->servicio->calcular()['filas']->first();

        // (1 × 1,00 + 2 × 1,50) / 3 = 1,3333… se muestra 1,33, pero el valor total es exacto
        $this->assertSame(1.33, $fila->costoPromedio);
        $this->assertSame(4.0, $fila->valorCosto);
        $this->assertSame(9.99, $fila->valorVenta);
    }

    public function test_sin_stock_devuelve_ceros(): void
    {
        $resultado = $this->servicio->calcular();

        $this->assertCount(0, $resultado['filas']);
        $this->assertSame(0, $resultado['totales']['productos']);
        $this->assertSame(0, $resultado['totales']['unidades']);
        $this->assertSame(0.0, $resultado['totales']['valorCosto']);
        $this->assertTrue($resultado['totales']['completo']);
    }
}
