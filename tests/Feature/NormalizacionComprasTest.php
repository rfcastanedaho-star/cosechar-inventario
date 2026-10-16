<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\CompraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 3FN: el producto de una línea de compra se obtiene del lote
 * (lotes.producto_id), por lo que compra_detalles ya no lo repite.
 */
class NormalizacionComprasTest extends TestCase
{
    use RefreshDatabase;

    public function test_compra_detalles_no_repite_el_producto(): void
    {
        $this->assertFalse(Schema::hasColumn('compra_detalles', 'producto_id'));
        $this->assertTrue(Schema::hasColumn('compra_detalles', 'lote_id'));
    }

    public function test_el_producto_de_una_linea_se_obtiene_a_traves_del_lote(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        $compra = app(CompraService::class)->registrar(
            [
                'proveedor_id' => Proveedor::factory()->create()->id,
                'almacen_id' => Almacen::factory()->create()->id,
                'fecha' => now()->toDateString(),
                'numero_comprobante' => null,
            ],
            [[
                'producto_id' => $producto->id,
                'numero_lote' => 'L-3FN',
                'fecha_vencimiento' => null,
                'cantidad' => 4,
                'costo_unitario' => 2.5,
            ]],
            User::factory()->create(),
        );

        $this->assertTrue($compra->detalles->first()->producto->is($producto));
    }
}
