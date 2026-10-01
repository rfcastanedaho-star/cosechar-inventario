<?php

namespace Tests\Feature;

use App\Livewire\NotificacionesBell;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificacionesBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_cuenta_productos_con_stock_bajo_y_lotes_por_vencer(): void
    {
        $usuario = User::factory()->create();

        $productoBajo = Producto::factory()->create(['stock_minimo' => 20]);
        Lote::factory()->for($productoBajo, 'producto')->create(['cantidad' => 5]);

        $productoPorVencer = Producto::factory()->create(['maneja_vencimiento' => true, 'stock_minimo' => 0]);
        Lote::factory()->for($productoPorVencer, 'producto')->create([
            'fecha_vencimiento' => now()->addDays(5)->toDateString(),
            'cantidad' => 10,
        ]);

        $total = Livewire::actingAs($usuario)
            ->test(NotificacionesBell::class)
            ->instance()
            ->total;

        $this->assertSame(2, $total);
    }

    public function test_sin_alertas_el_total_es_cero(): void
    {
        $usuario = User::factory()->create();

        Producto::factory()->create(['stock_minimo' => 0]);

        $total = Livewire::actingAs($usuario)
            ->test(NotificacionesBell::class)
            ->instance()
            ->total;

        $this->assertSame(0, $total);
    }

    public function test_el_operador_tambien_ve_las_alertas(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        $producto = Producto::factory()->create(['stock_minimo' => 20]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 5]);

        Livewire::actingAs($operador)
            ->test(NotificacionesBell::class)
            ->assertSee('1');
    }
}
