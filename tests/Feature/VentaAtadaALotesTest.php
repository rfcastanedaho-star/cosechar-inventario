<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalleLote;
use App\Services\AnulacionService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VentaAtadaALotesTest extends TestCase
{
    use RefreshDatabase;

    private function lote(Producto $producto, string $numero, int $cantidad, string $vence): Lote
    {
        return Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => Almacen::first()->id, 'numero_lote' => $numero, 'cantidad' => $cantidad, 'fecha_vencimiento' => $vence,
        ]);
    }

    /** @param  array<int, array{producto_id: int, cantidad: int, precio_unitario: string}>  $lineas */
    private function vender(array $lineas): Venta
    {
        return app(VentaService::class)->registrar(
            ['cliente_nombre' => 'Ana Pérez', 'cliente_documento' => null, 'almacen_id' => Almacen::first()->id, 'fecha' => '2026-10-30', 'numero_comprobante' => null],
            $lineas,
            User::factory()->create(),
        );
    }

    public function test_existe_la_tabla_que_une_cada_linea_de_venta_con_sus_lotes(): void
    {
        $this->assertTrue(Schema::hasTable('venta_detalle_lotes'));
        $this->assertTrue(Schema::hasColumns('venta_detalle_lotes', ['venta_detalle_id', 'lote_id', 'cantidad']));
    }

    public function test_una_venta_guarda_de_que_lote_salio_cada_linea(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $antiguo = $this->lote($producto, 'L-A', 4, '2026-12-01');
        $nuevo = $this->lote($producto, 'L-B', 10, '2027-06-01');

        $venta = $this->vender([['producto_id' => $producto->id, 'cantidad' => 7, 'precio_unitario' => '10']]);

        $detalle = $venta->detalles->first();
        $asignaciones = $detalle->lotes()->orderBy('lote_id')->get();

        $this->assertCount(2, $asignaciones);
        $this->assertSame(4, $asignaciones->firstWhere('lote_id', $antiguo->id)->cantidad);
        $this->assertSame(3, $asignaciones->firstWhere('lote_id', $nuevo->id)->cantidad);
        $this->assertSame(7, $asignaciones->sum('cantidad'));
    }

    public function test_cada_linea_tiene_sus_propios_lotes_aunque_repita_producto(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $primero = $this->lote($producto, 'L-A', 5, '2026-12-01');
        $segundo = $this->lote($producto, 'L-B', 5, '2027-06-01');

        $venta = $this->vender([
            ['producto_id' => $producto->id, 'cantidad' => 5, 'precio_unitario' => '10'],
            ['producto_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => '10'],
        ]);

        [$linea1, $linea2] = $venta->detalles->all();

        $this->assertSame([$primero->id => 5], $linea1->lotes->pluck('cantidad', 'lote_id')->all());
        $this->assertSame([$segundo->id => 2], $linea2->lotes->pluck('cantidad', 'lote_id')->all());
    }

    public function test_desde_el_lote_se_ven_las_ventas_que_lo_consumieron(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = $this->lote($producto, 'L-A', 20, '2027-06-01');

        $venta = $this->vender([['producto_id' => $producto->id, 'cantidad' => 6, 'precio_unitario' => '10']]);

        $consumo = $lote->consumosEnVentas()->with('detalle.venta')->get();

        $this->assertCount(1, $consumo);
        $this->assertSame(6, $consumo->first()->cantidad);
        $this->assertSame($venta->id, $consumo->first()->detalle->venta->id);
    }

    public function test_la_suma_por_lote_coincide_con_las_salidas_del_kardex(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $this->lote($producto, 'L-A', 4, '2026-12-01');
        $this->lote($producto, 'L-B', 10, '2027-06-01');

        $venta = $this->vender([['producto_id' => $producto->id, 'cantidad' => 9, 'precio_unitario' => '10']]);

        $kardex = StockMovimiento::where('venta_id', $venta->id)->orderBy('lote_id')->pluck('cantidad', 'lote_id')->all();
        $asignado = VentaDetalleLote::orderBy('lote_id')->pluck('cantidad', 'lote_id')->all();

        $this->assertSame($kardex, $asignado);
    }

    public function test_si_falta_stock_no_queda_ninguna_asignacion(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $this->lote($producto, 'L-A', 2, '2027-06-01');

        try {
            $this->vender([['producto_id' => $producto->id, 'cantidad' => 5, 'precio_unitario' => '10']]);
            $this->fail('Debió fallar por falta de stock');
        } catch (\Throwable) {
            $this->assertDatabaseCount('venta_detalle_lotes', 0);
            $this->assertDatabaseCount('ventas', 0);
        }
    }

    public function test_anular_la_venta_conserva_el_registro_de_sus_lotes(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = $this->lote($producto, 'L-A', 10, '2027-06-01');
        $venta = $this->vender([['producto_id' => $producto->id, 'cantidad' => 4, 'precio_unitario' => '10']]);

        app(AnulacionService::class)->anularVenta($venta, User::factory()->create(['rol' => 'administrador']), 'Error de digitación');

        $this->assertSame(10, $lote->fresh()->cantidad);
        $this->assertDatabaseCount('venta_detalle_lotes', 1);
    }

    // ---------- Ventas anteriores ----------

    public function test_la_migracion_reconstruye_los_lotes_de_las_ventas_antiguas(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $a = $this->lote($producto, 'L-A', 0, '2026-12-01');
        $b = $this->lote($producto, 'L-B', 0, '2027-06-01');
        $usuario = User::factory()->create();

        $venta = Venta::factory()->create(['almacen_id' => $a->almacen_id]);
        $detalle = $venta->detalles()->create(['producto_id' => $producto->id, 'cantidad' => 7, 'precio_unitario' => 10]);

        foreach ([[$a, 4], [$b, 3]] as [$lote, $cantidad]) {
            StockMovimiento::create([
                'lote_id' => $lote->id, 'tipo' => 'salida', 'cantidad' => $cantidad, 'fecha' => now(),
                'responsable_id' => $usuario->id, 'motivo' => 'venta', 'venta_id' => $venta->id,
            ]);
        }

        (require database_path('migrations/2026_10_30_160100_backfill_venta_detalle_lotes.php'))->up();

        $this->assertSame([$a->id => 4, $b->id => 3], $detalle->lotes()->pluck('cantidad', 'lote_id')->all());
    }

    public function test_la_migracion_no_duplica_si_se_corre_dos_veces(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = $this->lote($producto, 'L-A', 10, '2027-06-01');
        $venta = $this->vender([['producto_id' => $producto->id, 'cantidad' => 4, 'precio_unitario' => '10']]);

        (require database_path('migrations/2026_10_30_160100_backfill_venta_detalle_lotes.php'))->up();

        $this->assertDatabaseCount('venta_detalle_lotes', 1);
        $this->assertSame(4, $venta->detalles->first()->lotes()->where('lote_id', $lote->id)->sum('cantidad'));
    }
}
