<?php

namespace Tests\Feature\Api;

use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\AnulacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalidaApiAlmacenTest extends TestCase
{
    use RefreshDatabase;

    private function stockEn(Almacen $almacen, Producto $producto, int $cantidad): Lote
    {
        return Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => $cantidad]);
    }

    public function test_con_almacen_id_descuenta_solo_de_ese_almacen_y_crea_la_venta(): void
    {
        $user = User::factory()->create();
        $norte = Almacen::factory()->create(['nombre' => 'Zona Norte']);
        $sur = Almacen::factory()->create(['nombre' => 'Zona Sur']);
        $producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 10]);
        $loteNorte = $this->stockEn($norte, $producto, 100);
        $loteSur = $this->stockEn($sur, $producto, 30);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', [
                'producto_id' => $producto->id,
                'cantidad' => 12,
                'almacen_id' => $sur->id,
                'motivo' => 'venta',
                'cliente_nombre' => 'Agro Zully SAC',
                'cliente_documento' => '20123456789',
                'numero_comprobante' => 'F001-77',
                'precio_unitario' => 15.5,
            ])
            ->assertCreated()
            ->assertJsonPath('venta.cliente_nombre', 'Agro Zully SAC')
            ->assertJsonPath('venta.almacen_id', $sur->id);

        $this->assertSame(100, $loteNorte->fresh()->cantidad);
        $this->assertSame(18, $loteSur->fresh()->cantidad);

        $venta = Venta::firstOrFail();
        $this->assertSame($sur->id, $venta->almacen_id);
        $this->assertSame('F001-77', $venta->numero_comprobante);
        $this->assertSame($user->id, $venta->responsable_id);
        $this->assertEquals(186.0, (float) $venta->total);
        $this->assertDatabaseHas('venta_detalles', ['venta_id' => $venta->id, 'producto_id' => $producto->id, 'cantidad' => 12]);
    }

    public function test_sin_almacen_id_lo_infiere_cuando_todo_el_stock_esta_en_un_solo_almacen(): void
    {
        $user = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $otro = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 8]);
        $lote = $this->stockEn($almacen, $producto, 40);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', ['producto_id' => $producto->id, 'cantidad' => 5])
            ->assertCreated()
            ->assertJsonPath('venta.almacen_id', $almacen->id);

        $this->assertSame(35, $lote->fresh()->cantidad);
        $this->assertEquals(40.0, (float) Venta::firstOrFail()->total);
        $this->assertNotSame($otro->id, Venta::first()->almacen_id);
    }

    public function test_sin_almacen_id_y_con_stock_en_varios_almacenes_responde_422_y_no_toca_nada(): void
    {
        $user = User::factory()->create();
        $norte = Almacen::factory()->create(['nombre' => 'Zona Norte']);
        $sur = Almacen::factory()->create(['nombre' => 'Zona Sur']);
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $loteNorte = $this->stockEn($norte, $producto, 100);
        $loteSur = $this->stockEn($sur, $producto, 5);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', ['producto_id' => $producto->id, 'cantidad' => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $mensaje) => str_contains($mensaje, 'almacen_id')
                && str_contains($mensaje, 'Zona Norte (100)')
                && str_contains($mensaje, 'Zona Sur (5)'));

        $this->assertSame(100, $loteNorte->fresh()->cantidad);
        $this->assertSame(5, $loteSur->fresh()->cantidad);
        $this->assertSame(0, Venta::count());
    }

    public function test_si_el_almacen_pedido_no_alcanza_responde_422_aunque_otro_tenga_stock(): void
    {
        $user = User::factory()->create();
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $this->stockEn($norte, $producto, 100);
        $this->stockEn($sur, $producto, 5);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', ['producto_id' => $producto->id, 'cantidad' => 20, 'almacen_id' => $sur->id])
            ->assertStatus(422);

        $this->assertSame(0, Venta::count());
    }

    public function test_no_acepta_un_almacen_desactivado_ni_inexistente(): void
    {
        $user = User::factory()->create();
        $cerrado = Almacen::factory()->create(['activo' => false]);
        $producto = Producto::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', ['producto_id' => $producto->id, 'cantidad' => 1, 'almacen_id' => $cerrado->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['almacen_id']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', ['producto_id' => $producto->id, 'cantidad' => 1, 'almacen_id' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['almacen_id']);
    }

    public function test_una_salida_que_no_es_venta_descuenta_del_almacen_pero_no_crea_venta(): void
    {
        $user = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = $this->stockEn($almacen, $producto, 20);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', ['producto_id' => $producto->id, 'cantidad' => 4, 'motivo' => 'merma'])
            ->assertCreated()
            ->assertJsonPath('venta', null);

        $this->assertSame(16, $lote->fresh()->cantidad);
        $this->assertSame(0, Venta::count());
    }

    public function test_sin_datos_del_cliente_usa_un_valor_por_defecto_y_el_precio_del_catalogo(): void
    {
        $user = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 25]);
        $this->stockEn($almacen, $producto, 10);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', ['producto_id' => $producto->id, 'cantidad' => 2])
            ->assertCreated();

        $venta = Venta::firstOrFail();
        $this->assertSame('Cliente no especificado', $venta->cliente_nombre);
        $this->assertEquals(50.0, (float) $venta->total);
    }

    public function test_una_venta_hecha_por_la_api_se_puede_anular_y_devuelve_el_stock(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = $this->stockEn($almacen, $producto, 30);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/movimientos', ['producto_id' => $producto->id, 'cantidad' => 10])
            ->assertCreated();

        $this->assertSame(20, $lote->fresh()->cantidad);

        (new AnulacionService(new LoteRepository, new StockMovimientoRepository))
            ->anularVenta(Venta::firstOrFail(), $admin, 'Zully canceló el pedido');

        $this->assertSame(30, $lote->fresh()->cantidad);
    }

    public function test_el_stock_de_la_api_incluye_el_desglose_por_almacen_y_conserva_el_total(): void
    {
        $user = User::factory()->create();
        $norte = Almacen::factory()->create(['nombre' => 'Zona Norte']);
        $sur = Almacen::factory()->create(['nombre' => 'Zona Sur']);
        $producto = Producto::factory()->create();
        $this->stockEn($norte, $producto, 40);
        $this->stockEn($norte, $producto, 10);
        $this->stockEn($sur, $producto, 7);

        $respuesta = $this->actingAs($user, 'sanctum')
            ->getJson("/api/productos/{$producto->id}/stock")
            ->assertOk()
            ->assertJsonPath('data.stock_actual', 57);

        $porAlmacen = collect($respuesta->json('data.por_almacen'))->keyBy('almacen');

        $this->assertSame(50, $porAlmacen['Zona Norte']['stock']);
        $this->assertSame($norte->id, $porAlmacen['Zona Norte']['almacen_id']);
        $this->assertSame(7, $porAlmacen['Zona Sur']['stock']);
    }
}
