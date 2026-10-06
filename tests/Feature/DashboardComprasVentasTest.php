<?php

namespace Tests\Feature;

use App\Livewire\DashboardGraficos;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardComprasVentasTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_compras_y_ventas_del_mes_sin_contar_anuladas_ni_otros_meses(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $admin = User::factory()->create(['rol' => 'administrador']);

        Compra::factory()->create(['fecha' => '2026-10-02', 'total' => 100]);
        Compra::factory()->create(['fecha' => '2026-10-10', 'total' => 50]);
        Compra::factory()->create(['fecha' => '2026-09-30', 'total' => 700]);
        Compra::factory()->create(['fecha' => '2026-10-11', 'total' => 900, 'anulada_at' => now(), 'anulada_por' => $admin->id, 'motivo_anulacion' => 'Error']);

        Venta::factory()->create(['fecha' => '2026-10-03', 'total' => 80]);
        Venta::factory()->create(['fecha' => '2026-08-03', 'total' => 500]);

        $resumen = Livewire::actingAs($admin)->test(DashboardGraficos::class)->instance()->resumen;

        $this->assertSame(150.0, (float) $resumen['comprasMes']);
        $this->assertSame(2, $resumen['comprasMesCantidad']);
        $this->assertSame(80.0, (float) $resumen['ventasMes']);
        $this->assertSame(1, $resumen['ventasMesCantidad']);
    }

    public function test_muestra_el_stock_por_almacen(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $norte = Almacen::factory()->create(['nombre' => 'Zona Norte']);
        $sur = Almacen::factory()->create(['nombre' => 'Zona Sur']);
        $producto = Producto::factory()->create();

        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $norte->id, 'cantidad' => 30]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $norte->id, 'cantidad' => 5]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $sur->id, 'cantidad' => 12]);

        $porAlmacen = Livewire::actingAs($admin)
            ->test(DashboardGraficos::class)
            ->instance()
            ->stockPorAlmacen
            ->keyBy('nombre');

        $this->assertSame(35, (int) $porAlmacen['Zona Norte']->unidades);
        $this->assertSame(12, (int) $porAlmacen['Zona Sur']->unidades);
    }
}
