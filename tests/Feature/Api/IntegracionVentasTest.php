<?php

namespace Tests\Feature\Api;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El módulo de Ventas (responsabilidad de Zully) no existe en este repositorio,
 * por lo que no es posible ejecutar una prueba de integración real entre los
 * dos módulos (T17 de TASKS.md). Esta prueba cubre el lado que sí controla
 * Inventario: simula exactamente la secuencia de llamadas que Ventas haría
 * a la API para confirmar una venta (consultar stock, registrar la salida,
 * volver a consultar el stock) y verifica que el contrato se cumple.
 */
class IntegracionVentasTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_venta_de_prueba_genera_el_movimiento_y_descuento_esperado(): void
    {
        $usuarioVentas = User::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 50]);

        // 1. Ventas consulta el stock disponible antes de confirmar la venta.
        $stockInicial = $this->actingAs($usuarioVentas, 'sanctum')
            ->getJson("/api/productos/{$producto->id}/stock")
            ->assertOk()
            ->json('data.stock_actual');

        $this->assertSame(50, $stockInicial);

        // 2. Ventas confirma la venta registrando la salida.
        $this->actingAs($usuarioVentas, 'sanctum')
            ->postJson('/api/movimientos', [
                'producto_id' => $producto->id,
                'cantidad' => 12,
                'motivo' => 'venta',
            ])
            ->assertCreated();

        // 3. El descuento debe reflejarse de inmediato en Inventario.
        $stockFinal = $this->actingAs($usuarioVentas, 'sanctum')
            ->getJson("/api/productos/{$producto->id}/stock")
            ->assertOk()
            ->json('data.stock_actual');

        $this->assertSame(38, $stockFinal);
        $this->assertDatabaseHas('stock_movimientos', [
            'tipo' => 'salida',
            'cantidad' => 12,
            'motivo' => 'venta',
        ]);
    }

    public function test_ventas_recibe_un_error_claro_si_no_hay_stock_y_no_se_descuenta_nada(): void
    {
        $usuarioVentas = User::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 3]);

        $this->actingAs($usuarioVentas, 'sanctum')
            ->postJson('/api/movimientos', [
                'producto_id' => $producto->id,
                'cantidad' => 5,
                'motivo' => 'venta',
            ])
            ->assertStatus(422);

        $this->assertSame(0, StockMovimiento::count());
    }
}
