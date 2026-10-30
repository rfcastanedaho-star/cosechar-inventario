<?php

namespace Tests\Feature;

use App\Livewire\DashboardGraficos;
use App\Livewire\InventarioLista;
use App\Livewire\LoteLista;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Repositories\StockMovimientoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Una merma, un producto dañado o un error de conteo NO es una venta: se cuenta aparte como ajuste.
 */
class VendidoVsAjustesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    private function mover(Lote $lote, string $tipo, ?string $motivo, int $cantidad, ?string $fecha = null): void
    {
        StockMovimiento::create([
            'lote_id' => $lote->id, 'tipo' => $tipo, 'cantidad' => $cantidad, 'motivo' => $motivo,
            'fecha' => $fecha ?? now(), 'responsable_id' => $this->admin->id,
        ]);
    }

    private function loteConHistoria(): Lote
    {
        $lote = Lote::factory()->for(Producto::factory()->create(['nombre' => 'Fungicida Cobre']), 'producto')->create(['cantidad' => 50]);

        $this->mover($lote, 'entrada', 'compra', 100);
        $this->mover($lote, 'salida', 'venta', 30);
        $this->mover($lote, 'entrada', 'anulacion', 5);     // se anuló una venta de 5
        $this->mover($lote, 'salida', 'merma', 4);
        $this->mover($lote, 'salida', 'producto_danado', 3);
        $this->mover($lote, 'entrada', 'error_conteo', 2);

        return $lote;
    }

    public function test_el_vendido_no_incluye_mermas_ni_danados_y_descuenta_las_anulaciones(): void
    {
        $lote = $this->loteConHistoria();

        $totales = (new StockMovimientoRepository)->totalesPorLote([$lote->id])[$lote->id];

        $this->assertSame(25, $totales['vendido']);   // 30 − 5 anulados
        $this->assertSame(7, $totales['ajustes_menos']); // merma 4 + dañado 3
        $this->assertSame(2, $totales['ajustes_mas']);
    }

    public function test_un_lote_sin_movimientos_aparece_en_ceros(): void
    {
        $lote = Lote::factory()->create();

        $this->assertSame(
            ['vendido' => 0, 'ajustes_menos' => 0, 'ajustes_mas' => 0],
            (new StockMovimientoRepository)->totalesPorLote([$lote->id])[$lote->id],
        );
    }

    public function test_los_totales_por_producto_suman_todos_sus_lotes(): void
    {
        $producto = Producto::factory()->create();
        $a = Lote::factory()->for($producto, 'producto')->create();
        $b = Lote::factory()->for($producto, 'producto')->create();
        $this->mover($a, 'salida', 'venta', 10);
        $this->mover($b, 'salida', 'venta', 5);
        $this->mover($b, 'salida', 'merma', 2);

        $totales = (new StockMovimientoRepository)->totalesPorProducto([$producto->id])[$producto->id];

        $this->assertSame(15, $totales['vendido']);
        $this->assertSame(2, $totales['ajustes_menos']);
    }

    public function test_la_transferencia_y_el_saldo_inicial_no_son_ventas_ni_ajustes(): void
    {
        $lote = Lote::factory()->create();
        $this->mover($lote, 'salida', 'transferencia', 6);
        $this->mover($lote, 'entrada', 'saldo_inicial', 20);

        $this->assertSame(
            ['vendido' => 0, 'ajustes_menos' => 0, 'ajustes_mas' => 0],
            (new StockMovimientoRepository)->totalesPorLote([$lote->id])[$lote->id],
        );
    }

    public function test_el_dashboard_separa_entradas_ventas_y_ajustes_por_dia(): void
    {
        $lote = Lote::factory()->create();
        $this->mover($lote, 'entrada', 'compra', 100);
        $this->mover($lote, 'salida', 'venta', 30);
        $this->mover($lote, 'salida', 'merma', 4);
        $this->mover($lote, 'salida', 'producto_danado', 3);

        $movimientos = Livewire::actingAs($this->admin)->test(DashboardGraficos::class)->viewData('movimientos');
        $hoy = array_key_last($movimientos['labels']);

        $this->assertSame(100, $movimientos['entradas'][$hoy]);
        $this->assertSame(30, $movimientos['ventas'][$hoy]);
        $this->assertSame(7, $movimientos['ajustes'][$hoy]);
        $this->assertArrayNotHasKey('salidas', $movimientos);
    }

    public function test_el_dashboard_no_cuenta_una_venta_anulada_como_entrada_y_la_resta_de_ventas(): void
    {
        $lote = Lote::factory()->create();
        $this->mover($lote, 'salida', 'venta', 30);
        $this->mover($lote, 'entrada', 'anulacion', 30);

        $movimientos = Livewire::actingAs($this->admin)->test(DashboardGraficos::class)->viewData('movimientos');
        $hoy = array_key_last($movimientos['labels']);

        $this->assertSame(0, $movimientos['entradas'][$hoy]);
        $this->assertSame(0, $movimientos['ventas'][$hoy]);
    }

    public function test_el_detalle_del_lote_muestra_vendido_y_ajustes_por_separado(): void
    {
        $lote = $this->loteConHistoria();

        $html = Livewire::actingAs($this->admin)
            ->test(LoteLista::class)
            ->call('verDetalle', $lote->id)
            ->html();

        $texto = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));

        $this->assertStringContainsString('Vendido 25 unidades en ventas', $texto);
        $this->assertStringContainsString('Ajustes (merma, dañado, conteo) −7 +2 no cuentan como venta', $texto);
    }

    public function test_inventario_muestra_las_columnas_vendido_y_ajustes(): void
    {
        $lote = $this->loteConHistoria();

        Livewire::actingAs($this->admin)
            ->test(InventarioLista::class)
            ->assertSeeInOrder(['Vendido', 'Ajustes'])
            ->assertSeeInOrder(['Fungicida Cobre', '25', '−7', '+2']);
    }
}
