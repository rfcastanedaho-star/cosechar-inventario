<?php

namespace Tests\Feature;

use App\Exceptions\StockInsuficienteException;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Services\StockMovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AjusteDeLoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function lote(int $cantidad = 10): Lote
    {
        return Lote::factory()->for(Producto::factory(), 'producto')->create(['cantidad' => $cantidad]);
    }

    private function servicio(): StockMovimientoService
    {
        return app(StockMovimientoService::class);
    }

    // ---------- Motivos ----------

    public function test_las_razones_de_reducir_y_aumentar_son_las_acordadas(): void
    {
        $this->assertSame(
            ['merma', 'producto_danado', 'producto_vencido', 'error_conteo', 'devolucion', 'perdida', 'otro'],
            StockMovimiento::MOTIVOS_REDUCIR,
        );
        $this->assertSame(['error_conteo', 'devolucion', 'otro'], StockMovimiento::MOTIVOS_AUMENTAR);
    }

    public function test_cada_razon_tiene_su_texto_y_las_antiguas_se_conservan(): void
    {
        $this->assertSame('Producto vencido', StockMovimiento::MOTIVOS['producto_vencido']);
        $this->assertSame('Error de conteo', StockMovimiento::MOTIVOS['error_conteo']);
        $this->assertSame('Devolución', StockMovimiento::MOTIVOS['devolucion']);
        $this->assertSame('Pérdida', StockMovimiento::MOTIVOS['perdida']);
        $this->assertSame('Otro', StockMovimiento::MOTIVOS['otro']);
        $this->assertSame('Ajuste por conteo', StockMovimiento::MOTIVOS['ajuste_inventario']);
        $this->assertSame('Transferencia', StockMovimiento::MOTIVOS['transferencia']);
    }

    public function test_la_base_acepta_las_razones_nuevas(): void
    {
        $lote = $this->lote();

        foreach (['producto_vencido', 'error_conteo', 'devolucion', 'perdida', 'otro'] as $motivo) {
            $movimiento = StockMovimiento::create([
                'lote_id' => $lote->id, 'tipo' => 'salida', 'cantidad' => 1, 'fecha' => now(),
                'responsable_id' => User::factory()->create()->id, 'motivo' => $motivo,
            ]);

            $this->assertSame($motivo, $movimiento->fresh()->motivo);
        }
    }

    // ---------- Reducir ----------

    public function test_reducir_descuenta_del_lote_y_deja_el_movimiento_en_el_kardex(): void
    {
        $lote = $this->lote(10);
        $usuario = User::factory()->create();

        $movimiento = $this->servicio()->ajustarLote($lote, 'salida', 4, 'producto_vencido', $usuario);

        $this->assertSame(6, $lote->fresh()->cantidad);
        $this->assertSame('salida', $movimiento->tipo);
        $this->assertSame(4, $movimiento->cantidad);
        $this->assertSame('producto_vencido', $movimiento->motivo);
        $this->assertSame($usuario->id, $movimiento->responsable_id);
        $this->assertSame($lote->id, $movimiento->lote_id);
        $this->assertNull($movimiento->venta_id);
    }

    public function test_no_se_puede_reducir_mas_de_lo_que_hay_en_el_lote(): void
    {
        $lote = $this->lote(3);

        $this->expectException(StockInsuficienteException::class);

        try {
            $this->servicio()->ajustarLote($lote, 'salida', 4, 'merma', User::factory()->create());
        } finally {
            $this->assertSame(3, $lote->fresh()->cantidad);
            $this->assertDatabaseCount('stock_movimientos', 0);
        }
    }

    public function test_se_puede_dejar_el_lote_en_cero(): void
    {
        $lote = $this->lote(3);

        $this->servicio()->ajustarLote($lote, 'salida', 3, 'perdida', User::factory()->create());

        $this->assertSame(0, $lote->fresh()->cantidad);
    }

    // ---------- Aumentar ----------

    public function test_aumentar_suma_al_lote_con_su_razon(): void
    {
        $lote = $this->lote(10);

        $movimiento = $this->servicio()->ajustarLote($lote, 'entrada', 5, 'devolucion', User::factory()->create());

        $this->assertSame(15, $lote->fresh()->cantidad);
        $this->assertSame('entrada', $movimiento->tipo);
        $this->assertSame('devolucion', $movimiento->motivo);
    }

    // ---------- Validaciones ----------

    public function test_una_razon_de_reducir_no_sirve_para_aumentar_ni_al_reves(): void
    {
        $lote = $this->lote(10);

        foreach ([['entrada', 'merma'], ['entrada', 'producto_vencido'], ['salida', 'ajuste_inventario'], ['salida', 'venta'], ['entrada', 'compra']] as [$tipo, $motivo]) {
            try {
                $this->servicio()->ajustarLote($lote, $tipo, 1, $motivo, User::factory()->create());
                $this->fail("Aceptó {$motivo} para {$tipo}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(10, $lote->fresh()->cantidad);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_la_cantidad_debe_ser_mayor_a_cero_y_el_tipo_valido(): void
    {
        $lote = $this->lote(10);

        $this->expectException(InvalidArgumentException::class);
        $this->servicio()->ajustarLote($lote, 'salida', 0, 'merma', User::factory()->create());
    }

    public function test_un_tipo_desconocido_se_rechaza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio()->ajustarLote($this->lote(), 'transferencia', 1, 'otro', User::factory()->create());
    }
}
