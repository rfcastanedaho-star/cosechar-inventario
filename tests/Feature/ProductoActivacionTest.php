<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\MovimientoForm;
use App\Livewire\ProductoLista;
use App\Livewire\VentaForm;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Repositories\ProductoRepository;
use App\Services\AlertaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoActivacionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    public function test_los_productos_nuevos_nacen_activos(): void
    {
        $this->assertTrue(Producto::factory()->create()->fresh()->activo);
    }

    public function test_el_administrador_desactiva_un_producto_sin_stock_y_queda_en_el_historial(): void
    {
        $admin = $this->admin();
        $producto = Producto::factory()->create();

        Livewire::actingAs($admin)
            ->test(ProductoLista::class)
            ->call('cambiarEstado', $producto->id)
            ->assertSet('errorEstado', null);

        $this->assertFalse($producto->fresh()->activo);
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Producto::class,
            'auditable_id' => $producto->id,
            'user_id' => $admin->id,
            'campo' => 'activo',
        ]);
    }

    public function test_no_se_desactiva_un_producto_con_stock(): void
    {
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 4]);

        Livewire::actingAs($this->admin())
            ->test(ProductoLista::class)
            ->call('cambiarEstado', $producto->id)
            ->assertSet('errorEstado', 'No se puede desactivar: el producto todavía tiene stock.');

        $this->assertTrue($producto->fresh()->activo);
    }

    public function test_un_producto_con_lotes_agotados_si_se_puede_desactivar(): void
    {
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 0]);

        Livewire::actingAs($this->admin())
            ->test(ProductoLista::class)
            ->call('cambiarEstado', $producto->id);

        $this->assertFalse($producto->fresh()->activo);
    }

    public function test_el_administrador_puede_reactivar_un_producto(): void
    {
        $producto = Producto::factory()->create(['activo' => false]);

        Livewire::actingAs($this->admin())
            ->test(ProductoLista::class)
            ->call('cambiarEstado', $producto->id);

        $this->assertTrue($producto->fresh()->activo);
    }

    public function test_el_operador_no_puede_cambiar_el_estado(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs(User::factory()->create(['rol' => 'operador_almacen']))
            ->test(ProductoLista::class)
            ->call('cambiarEstado', $producto->id)
            ->assertForbidden();

        $this->assertTrue($producto->fresh()->activo);
    }

    public function test_la_lista_oculta_los_inactivos_salvo_que_se_pida_verlos(): void
    {
        Producto::factory()->create(['nombre' => 'Producto Vigente']);
        Producto::factory()->create(['nombre' => 'Producto Retirado', 'activo' => false]);

        Livewire::actingAs($this->admin())
            ->test(ProductoLista::class)
            ->assertSee('Producto Vigente')
            ->assertDontSee('Producto Retirado')
            ->set('mostrarInactivos', true)
            ->assertSee('Producto Retirado')
            ->assertSee('Inactivo');
    }

    public function test_el_buscador_de_productos_ignora_los_inactivos(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Activo']);
        Producto::factory()->create(['nombre' => 'Fungicida Retirado', 'activo' => false]);

        $nombres = (new ProductoRepository)->buscar('fungicida')->pluck('nombre')->all();

        $this->assertSame(['Fungicida Activo'], $nombres);
    }

    public function test_movimientos_no_deja_elegir_un_producto_inactivo(): void
    {
        $inactivo = Producto::factory()->create(['activo' => false]);

        Livewire::actingAs($this->admin())
            ->test(MovimientoForm::class)
            ->call('elegirProducto', $inactivo->id)
            ->assertSet('producto_id', null);
    }

    public function test_compras_y_ventas_solo_ofrecen_productos_activos(): void
    {
        Producto::factory()->create(['nombre' => 'Producto Vigente']);
        Producto::factory()->create(['nombre' => 'Producto Retirado', 'activo' => false]);

        foreach ([CompraForm::class, VentaForm::class] as $componente) {
            Livewire::actingAs($this->admin())
                ->test($componente)
                ->set('busquedaProducto.0', 'Producto')
                ->assertSee('Producto Vigente')
                ->assertDontSee('Producto Retirado');
        }
    }

    public function test_las_alertas_de_stock_minimo_ignoran_los_productos_inactivos(): void
    {
        Producto::factory()->create(['nombre' => 'Vigente', 'stock_minimo' => 10]);
        Producto::factory()->create(['nombre' => 'Retirado', 'stock_minimo' => 10, 'activo' => false]);

        $nombres = app(AlertaService::class)->productosConStockMinimo()->pluck('nombre')->all();

        $this->assertSame(['Vigente'], $nombres);
    }
}
