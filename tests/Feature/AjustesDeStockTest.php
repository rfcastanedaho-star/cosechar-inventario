<?php

namespace Tests\Feature;

use App\Livewire\MovimientoForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AjustesDeStockTest extends TestCase
{
    use RefreshDatabase;

    private function formulario(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(MovimientoForm::class);
    }

    // ---------- Menú y pantalla ----------

    public function test_el_menu_se_llama_ajustes_de_stock(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ajustes de stock');
    }

    public function test_la_pantalla_solo_ofrece_entrada_y_salida(): void
    {
        $this->formulario()
            ->assertSee('Registrar ajuste de stock')
            ->assertSee('Entrada')
            ->assertSee('Salida')
            ->assertDontSee('Transferencia')
            ->assertDontSee('Almacén de destino');
    }

    public function test_un_tipo_transferencia_enviado_a_mano_se_ignora(): void
    {
        $this->formulario()
            ->set('tipo', 'transferencia')
            ->assertSet('tipo', 'entrada');
    }

    // ---------- Un solo almacén ----------

    public function test_con_un_solo_almacen_se_elige_solo_y_no_se_pregunta(): void
    {
        $almacen = Almacen::first();
        $this->assertSame(1, Almacen::where('activo', true)->count());

        $this->formulario()
            ->assertSet('almacen_id', $almacen->id)
            ->assertDontSee('Seleccione un almacén')
            ->assertSee($almacen->nombre);
    }

    public function test_con_un_solo_almacen_la_entrada_se_registra_sin_elegirlo(): void
    {
        $almacen = Almacen::first();
        $producto = Producto::factory()->create();
        $lote = Lote::factory()->create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'cantidad' => 10]);

        $this->formulario()
            ->call('elegirProducto', $producto->id)
            ->call('elegirLote', $lote->id)
            ->set('cantidad', 4)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(14, $lote->fresh()->cantidad);
    }

    public function test_con_un_solo_almacen_la_salida_se_registra_sin_elegirlo(): void
    {
        $almacen = Almacen::first();
        $producto = Producto::factory()->create();
        $lote = Lote::factory()->create(['producto_id' => $producto->id, 'almacen_id' => $almacen->id, 'cantidad' => 10]);

        $this->formulario()
            ->set('tipo', 'salida')
            ->call('elegirProducto', $producto->id)
            ->set('motivo', 'merma')
            ->set('cantidad', 3)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(7, $lote->fresh()->cantidad);
    }

    public function test_con_varios_almacenes_se_sigue_pidiendo_elegir(): void
    {
        Almacen::factory()->create(['activo' => true]);

        $this->formulario()
            ->assertSet('almacen_id', null)
            ->assertSee('Seleccione un almacén');
    }

    public function test_un_almacen_desactivado_no_cuenta_para_decidir(): void
    {
        Almacen::factory()->create(['activo' => false]);

        $this->formulario()->assertSet('almacen_id', Almacen::first()->id);
    }
}
