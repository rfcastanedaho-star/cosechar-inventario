<?php

namespace Tests\Feature;

use App\Livewire\VentaForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VentaFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_registrar_una_venta_y_se_descuenta_el_stock(): void
    {
        $usuario = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 30]);

        Livewire::actingAs($usuario)
            ->test(VentaForm::class)
            ->set('cliente_nombre', 'María López')
            ->set('almacen_id', $almacen->id)
            ->set('fecha', '2026-09-20')
            ->set('lineas.0.producto_id', $producto->id)
            ->set('lineas.0.cantidad', 10)
            ->set('lineas.0.precio_unitario', 20)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ventas', [
            'cliente_nombre' => 'María López',
            'almacen_id' => $almacen->id,
            'total' => 200,
        ]);

        $this->assertSame(20, $lote->fresh()->cantidad);
    }

    public function test_el_cliente_y_almacen_son_obligatorios(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(VentaForm::class)
            ->set('cliente_nombre', '')
            ->set('almacen_id', null)
            ->call('guardar')
            ->assertHasErrors(['cliente_nombre' => 'required', 'almacen_id' => 'required']);
    }

    public function test_no_permite_vender_mas_stock_del_disponible_en_el_almacen(): void
    {
        $usuario = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 5]);

        Livewire::actingAs($usuario)
            ->test(VentaForm::class)
            ->set('cliente_nombre', 'Cliente X')
            ->set('almacen_id', $almacen->id)
            ->set('lineas.0.producto_id', $producto->id)
            ->set('lineas.0.cantidad', 50)
            ->set('lineas.0.precio_unitario', 10)
            ->call('guardar')
            ->assertHasErrors(['lineas']);

        $this->assertDatabaseMissing('ventas', ['cliente_nombre' => 'Cliente X']);
    }

    public function test_no_quita_la_ultima_linea(): void
    {
        $usuario = User::factory()->create();

        $component = Livewire::actingAs($usuario)->test(VentaForm::class);
        $component->call('quitarLinea', 0);

        $this->assertCount(1, $component->get('lineas'));
    }
}
