<?php

namespace Tests\Feature;

use App\Livewire\DashboardGraficos;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardGraficosTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_las_tarjetas_de_resumen(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        $productoBajo = Producto::factory()->create(['stock_minimo' => 20]);
        Lote::factory()->for($productoBajo, 'producto')->create(['cantidad' => 5]);

        $productoOk = Producto::factory()->create(['stock_minimo' => 5]);
        $lote = Lote::factory()->for($productoOk, 'producto')->create(['cantidad' => 50]);

        StockMovimiento::create([
            'lote_id' => $lote->id,
            'tipo' => 'entrada',
            'cantidad' => 10,
            'fecha' => now(),
            'responsable_id' => $admin->id,
            'motivo' => null,
        ]);

        $resumen = Livewire::actingAs($admin)
            ->test(DashboardGraficos::class)
            ->instance()
            ->resumen;

        $this->assertSame(2, $resumen['totalProductos']);
        $this->assertSame(1, $resumen['stockBajo']);
        $this->assertSame(0, $resumen['porVencer']);
        $this->assertSame(1, $resumen['movimientosHoy']);
    }

    public function test_calcula_la_salud_del_stock(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        $productoBajo = Producto::factory()->create(['stock_minimo' => 20]);
        Lote::factory()->for($productoBajo, 'producto')->create(['cantidad' => 5]);

        $productoOk = Producto::factory()->create(['stock_minimo' => 5]);
        Lote::factory()->for($productoOk, 'producto')->create(['cantidad' => 50]);

        $salud = Livewire::actingAs($admin)
            ->test(DashboardGraficos::class)
            ->instance()
            ->saludStock;

        $this->assertSame(50, $salud['porcentaje']);
        $this->assertSame(2, $salud['lotesActivos']);
        $this->assertSame('Sin movimientos aún', $salud['ultimoMovimientoTexto']);
    }

    public function test_sin_productos_la_salud_del_stock_es_completa(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        $salud = Livewire::actingAs($admin)
            ->test(DashboardGraficos::class)
            ->instance()
            ->saludStock;

        $this->assertSame(100, $salud['porcentaje']);
    }

    public function test_lista_los_proximos_vencimientos_ordenados_por_fecha(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        $producto = Producto::factory()->create(['maneja_vencimiento' => true, 'stock_minimo' => 0]);

        Lote::factory()->for($producto, 'producto')->create([
            'numero_lote' => 'LOTE-LEJOS',
            'fecha_vencimiento' => now()->addDays(20)->toDateString(),
            'cantidad' => 10,
        ]);

        Lote::factory()->for($producto, 'producto')->create([
            'numero_lote' => 'LOTE-CERCA',
            'fecha_vencimiento' => now()->addDays(5)->toDateString(),
            'cantidad' => 10,
        ]);

        $proximos = Livewire::actingAs($admin)
            ->test(DashboardGraficos::class)
            ->instance()
            ->proximosVencimientos;

        $this->assertCount(2, $proximos);
        $this->assertSame('LOTE-CERCA', $proximos->first()['numeroLote']);
    }
}
