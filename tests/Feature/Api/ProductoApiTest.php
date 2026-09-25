<?php

namespace Tests\Feature\Api;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_la_peticion_sin_token(): void
    {
        $this->getJson('/api/productos')->assertStatus(401);
    }

    public function test_lista_productos_con_stock_actual_para_un_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 30]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/productos')
            ->assertOk()
            ->assertJsonFragment(['id' => $producto->id, 'stock_actual' => 30]);
    }

    public function test_devuelve_el_stock_de_un_producto_especifico(): void
    {
        $user = User::factory()->create();
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 12]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 8]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/productos/{$producto->id}/stock")
            ->assertOk()
            ->assertJson(['data' => ['stock_actual' => 20]]);
    }

    public function test_devuelve_404_si_el_producto_no_existe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/productos/999999/stock')
            ->assertStatus(404);
    }
}
