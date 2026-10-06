<?php

namespace Tests\Feature;

use App\Livewire\MovimientoForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MovimientoFormAlmacenTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_entrada_crea_el_lote_en_el_almacen_elegido(): void
    {
        $usuario = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs($usuario)
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('numero_lote', 'L-ENT-1')
            ->set('cantidad', 25)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lotes', [
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'numero_lote' => 'L-ENT-1',
            'cantidad' => 25,
        ]);
    }

    public function test_la_salida_descuenta_solo_del_almacen_elegido(): void
    {
        $usuario = User::factory()->create();
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        $loteNorte = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $norte->id, 'cantidad' => 30]);
        $loteSur = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $sur->id, 'cantidad' => 30]);

        Livewire::actingAs($usuario)
            ->test(MovimientoForm::class)
            ->set('tipo', 'salida')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $sur->id)
            ->set('cantidad', 12)
            ->set('motivo', 'merma')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(30, $loteNorte->fresh()->cantidad);
        $this->assertSame(18, $loteSur->fresh()->cantidad);
    }

    public function test_no_permite_sacar_mas_de_lo_que_hay_en_ese_almacen_aunque_haya_en_otro(): void
    {
        $usuario = User::factory()->create();
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $norte->id, 'cantidad' => 100]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $sur->id, 'cantidad' => 5]);

        Livewire::actingAs($usuario)
            ->test(MovimientoForm::class)
            ->set('tipo', 'salida')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $sur->id)
            ->set('cantidad', 20)
            ->set('motivo', 'merma')
            ->call('guardar')
            ->assertHasErrors(['cantidad']);
    }

    public function test_el_almacen_es_obligatorio_en_entradas_y_salidas(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();

        foreach (['entrada', 'salida'] as $tipo) {
            Livewire::actingAs($usuario)
                ->test(MovimientoForm::class)
                ->set('tipo', $tipo)
                ->set('producto_id', $producto->id)
                ->set('cantidad', 5)
                ->call('guardar')
                ->assertHasErrors(['almacen_id' => 'required']);
        }
    }

    public function test_no_acepta_un_almacen_desactivado(): void
    {
        $usuario = User::factory()->create();
        $cerrado = Almacen::factory()->create(['activo' => false]);
        $producto = Producto::factory()->create();

        Livewire::actingAs($usuario)
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $cerrado->id)
            ->set('numero_lote', 'L-X')
            ->set('cantidad', 5)
            ->call('guardar')
            ->assertHasErrors(['almacen_id']);
    }

    public function test_el_stock_mostrado_corresponde_al_almacen_elegido(): void
    {
        $usuario = User::factory()->create();
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();
        $producto = Producto::factory()->create();

        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $norte->id, 'cantidad' => 40]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $sur->id, 'cantidad' => 7]);

        $componente = Livewire::actingAs($usuario)
            ->test(MovimientoForm::class)
            ->set('producto_id', $producto->id);

        $this->assertSame(47, (int) $componente->instance()->stockActual);

        $componente->set('almacen_id', $sur->id);
        $this->assertSame(7, (int) $componente->instance()->stockActual);
    }
}
