<?php

namespace Tests\Feature;

use App\Livewire\ReporteFiltro;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReporteComprasVentasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_reporte_de_compras_filtra_por_fechas_y_almacen(): void
    {
        $usuario = User::factory()->create();
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();

        Compra::factory()->create(['almacen_id' => $norte->id, 'fecha' => '2026-09-10', 'total' => 100]);
        Compra::factory()->create(['almacen_id' => $norte->id, 'fecha' => '2026-10-05', 'total' => 200]);
        Compra::factory()->create(['almacen_id' => $sur->id, 'fecha' => '2026-10-06', 'total' => 400]);

        $componente = Livewire::actingAs($usuario)
            ->test(ReporteFiltro::class)
            ->set('tab', 'compras')
            ->set('desde', '2026-10-01')
            ->set('hasta', '2026-10-31');

        $this->assertCount(2, $componente->instance()->compras);

        $componente->set('almacen_id', $norte->id);
        $this->assertCount(1, $componente->instance()->compras);
    }

    public function test_los_totales_de_compras_y_ventas_no_cuentan_las_anuladas(): void
    {
        $usuario = User::factory()->create();

        Compra::factory()->create(['fecha' => '2026-10-02', 'total' => 100]);
        Compra::factory()->create(['fecha' => '2026-10-03', 'total' => 300]);
        Compra::factory()->create(['fecha' => '2026-10-04', 'total' => 999, 'anulada_at' => now(), 'anulada_por' => $usuario->id, 'motivo_anulacion' => 'Error']);

        Venta::factory()->create(['fecha' => '2026-10-02', 'total' => 50]);
        Venta::factory()->create(['fecha' => '2026-10-03', 'total' => 888, 'anulada_at' => now(), 'anulada_por' => $usuario->id, 'motivo_anulacion' => 'Error']);

        $componente = Livewire::actingAs($usuario)->test(ReporteFiltro::class);

        $this->assertSame(400.0, (float) $componente->instance()->totalCompras);
        $this->assertSame(50.0, (float) $componente->instance()->totalVentas);
        $this->assertCount(3, $componente->instance()->compras);
    }

    public function test_el_reporte_de_ventas_filtra_por_almacen(): void
    {
        $usuario = User::factory()->create();
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();

        Venta::factory()->create(['almacen_id' => $norte->id, 'fecha' => '2026-10-02']);
        Venta::factory()->create(['almacen_id' => $sur->id, 'fecha' => '2026-10-02']);

        $ventas = Livewire::actingAs($usuario)
            ->test(ReporteFiltro::class)
            ->set('tab', 'ventas')
            ->set('almacen_id', $sur->id)
            ->instance()
            ->ventas;

        $this->assertCount(1, $ventas);
        $this->assertSame($sur->id, $ventas->first()->almacen_id);
    }

    public function test_el_stock_actual_se_puede_filtrar_por_almacen(): void
    {
        $usuario = User::factory()->create();
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $norte->id, 'cantidad' => 10]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $sur->id, 'cantidad' => 20]);

        $componente = Livewire::actingAs($usuario)->test(ReporteFiltro::class);
        $this->assertCount(2, $componente->instance()->stock);

        $componente->set('almacen_id', $sur->id);
        $this->assertCount(1, $componente->instance()->stock);
        $this->assertSame(20, $componente->instance()->stock->first()->cantidad);
    }

    public function test_el_csv_de_compras_respeta_los_filtros(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();

        Compra::factory()->create(['almacen_id' => $norte->id, 'fecha' => '2026-10-02', 'numero_comprobante' => 'F-NORTE']);
        Compra::factory()->create(['almacen_id' => $sur->id, 'fecha' => '2026-10-02', 'numero_comprobante' => 'F-SUR']);

        $contenido = $this->actingAs($admin)
            ->get(route('reportes.compras.csv', ['almacen_id' => $norte->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('F-NORTE', $contenido);
        $this->assertStringNotContainsString('F-SUR', $contenido);
    }

    public function test_el_csv_de_ventas_marca_las_anuladas(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        Venta::factory()->create(['cliente_nombre' => 'Cliente Anulado', 'anulada_at' => now(), 'anulada_por' => $admin->id, 'motivo_anulacion' => 'Error de digitación']);

        $contenido = $this->actingAs($admin)
            ->get(route('reportes.ventas.csv'))
            ->streamedContent();

        $this->assertStringContainsString('Cliente Anulado', $contenido);
        $this->assertStringContainsString('Anulada', $contenido);
    }
}
