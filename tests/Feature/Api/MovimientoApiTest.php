<?php

namespace Tests\Feature\Api;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovimientoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_la_peticion_sin_token(): void
    {
        $this->postJson('/api/movimientos', [])->assertStatus(401);
    }

    public function test_registra_una_salida_y_descuenta_el_stock(): void
    {
        $user = User::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['cantidad' => 20]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', [
                'producto_id' => $producto->id,
                'cantidad' => 5,
            ])
            ->assertCreated();

        $this->assertSame(15, $lote->fresh()->cantidad);
        $this->assertSame('venta', StockMovimiento::first()->motivo);
    }

    public function test_devuelve_422_cuando_el_stock_es_insuficiente(): void
    {
        $user = User::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 3]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', [
                'producto_id' => $producto->id,
                'cantidad' => 100,
            ])
            ->assertStatus(422);
    }

    public function test_valida_los_campos_requeridos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/movimientos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['producto_id', 'cantidad']);
    }
}
