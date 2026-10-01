<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompraFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_registrar_una_compra_con_una_linea(): void
    {
        $usuario = User::factory()->create();
        $proveedor = Proveedor::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs($usuario)
            ->test(CompraForm::class)
            ->set('proveedor_id', $proveedor->id)
            ->set('almacen_id', $almacen->id)
            ->set('fecha', '2026-09-20')
            ->set('lineas.0.producto_id', $producto->id)
            ->set('lineas.0.numero_lote', 'LOTE-X1')
            ->set('lineas.0.cantidad', 20)
            ->set('lineas.0.costo_unitario', 10)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('compras', [
            'proveedor_id' => $proveedor->id,
            'almacen_id' => $almacen->id,
            'total' => 200,
        ]);

        $this->assertDatabaseHas('lotes', [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'numero_lote' => 'LOTE-X1',
            'cantidad' => 20,
        ]);
    }

    public function test_el_proveedor_y_almacen_son_obligatorios(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(CompraForm::class)
            ->set('proveedor_id', null)
            ->set('almacen_id', null)
            ->call('guardar')
            ->assertHasErrors(['proveedor_id' => 'required', 'almacen_id' => 'required']);
    }

    public function test_puede_agregar_y_quitar_lineas(): void
    {
        $usuario = User::factory()->create();

        $component = Livewire::actingAs($usuario)->test(CompraForm::class);

        $component->call('agregarLinea');
        $this->assertCount(2, $component->get('lineas'));

        $component->call('quitarLinea', 1);
        $this->assertCount(1, $component->get('lineas'));
    }

    public function test_no_quita_la_ultima_linea(): void
    {
        $usuario = User::factory()->create();

        $component = Livewire::actingAs($usuario)->test(CompraForm::class);

        $component->call('quitarLinea', 0);

        $this->assertCount(1, $component->get('lineas'));
    }

    public function test_la_fecha_de_vencimiento_es_obligatoria_si_el_producto_la_maneja(): void
    {
        $usuario = User::factory()->create();
        $proveedor = Proveedor::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);

        Livewire::actingAs($usuario)
            ->test(CompraForm::class)
            ->set('proveedor_id', $proveedor->id)
            ->set('almacen_id', $almacen->id)
            ->set('lineas.0.producto_id', $producto->id)
            ->set('lineas.0.numero_lote', 'LOTE-X1')
            ->set('lineas.0.cantidad', 10)
            ->set('lineas.0.costo_unitario', 5)
            ->call('guardar')
            ->assertHasErrors(['lineas.0.fecha_vencimiento' => 'required']);
    }

    public function test_el_total_suma_varias_lineas(): void
    {
        $usuario = User::factory()->create();
        $proveedor = Proveedor::factory()->create();
        $almacen = Almacen::factory()->create();
        $productoA = Producto::factory()->create();
        $productoB = Producto::factory()->create();

        Livewire::actingAs($usuario)
            ->test(CompraForm::class)
            ->set('proveedor_id', $proveedor->id)
            ->set('almacen_id', $almacen->id)
            ->set('lineas.0.producto_id', $productoA->id)
            ->set('lineas.0.numero_lote', 'A1')
            ->set('lineas.0.cantidad', 10)
            ->set('lineas.0.costo_unitario', 5)
            ->call('agregarLinea')
            ->set('lineas.1.producto_id', $productoB->id)
            ->set('lineas.1.numero_lote', 'B1')
            ->set('lineas.1.cantidad', 4)
            ->set('lineas.1.costo_unitario', 25)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('compras', ['total' => 150]);
    }
}
