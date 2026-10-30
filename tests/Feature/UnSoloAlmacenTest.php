<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\CompraLista;
use App\Livewire\MovimientoLista;
use App\Livewire\ReporteFiltro;
use App\Livewire\VentaForm;
use App\Livewire\VentaLista;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnSoloAlmacenTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    // ---------- Ayudante ----------

    public function test_unico_activo_devuelve_el_almacen_solo_cuando_hay_exactamente_uno(): void
    {
        $this->assertSame(Almacen::first()->id, Almacen::unicoActivo()?->id);

        Almacen::factory()->create(['activo' => false]);
        $this->assertSame(Almacen::first()->id, Almacen::unicoActivo()?->id);

        Almacen::factory()->create(['activo' => true]);
        $this->assertNull(Almacen::unicoActivo());
    }

    // ---------- Menú ----------

    public function test_almacenes_ya_no_esta_en_el_menu(): void
    {
        $this->actingAs($this->usuario())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('almacenes.index'), false);
    }

    // ---------- Compras y Ventas ----------

    public function test_compras_usa_el_almacen_unico_sin_preguntar(): void
    {
        $almacen = Almacen::first();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs($this->usuario())
            ->test(CompraForm::class)
            ->assertSet('almacen_id', $almacen->id)
            ->assertDontSee('Seleccione un almacén')
            ->set('proveedor_nombre', 'Agro Andina SAC')
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.numero_lote', 'L-1')
            ->set('lineas.0.cantidad', 4)
            ->set('lineas.0.costo_unitario', '5')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($almacen->id, Lote::firstOrFail()->almacen_id);
    }

    public function test_ventas_usa_el_almacen_unico_sin_preguntar(): void
    {
        $almacen = Almacen::first();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 10]);

        Livewire::actingAs($this->usuario())
            ->test(VentaForm::class)
            ->assertSet('almacen_id', $almacen->id)
            ->assertDontSee('Seleccione un almacén')
            ->set('cliente_nombre', 'Ana Pérez')
            ->call('elegirProductoLinea', 0, $producto->id)
            ->call('guardar')
            ->assertHasNoErrors();
    }

    public function test_con_varios_almacenes_compras_y_ventas_vuelven_a_preguntar(): void
    {
        Almacen::factory()->create();

        foreach ([CompraForm::class, VentaForm::class] as $clase) {
            Livewire::actingAs($this->usuario())
                ->test($clase)
                ->assertSet('almacen_id', null)
                ->assertSee('Seleccione un almacén');
        }
    }

    public function test_tras_guardar_una_compra_el_almacen_unico_sigue_elegido(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs($this->usuario())
            ->test(CompraForm::class)
            ->set('proveedor_nombre', 'Agro Andina SAC')
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.numero_lote', 'L-1')
            ->set('lineas.0.costo_unitario', '5')
            ->call('guardar')
            ->assertSet('almacen_id', Almacen::first()->id);
    }

    // ---------- Filtros ----------

    public function test_los_historiales_no_filtran_por_almacen_si_hay_uno_solo(): void
    {
        foreach ([CompraLista::class, VentaLista::class, MovimientoLista::class] as $clase) {
            Livewire::actingAs($this->usuario())->test($clase)->assertDontSeeHtml('id="almacen_id"');
        }
    }

    public function test_los_historiales_filtran_por_almacen_si_hay_varios(): void
    {
        Almacen::factory()->create();

        foreach ([CompraLista::class, VentaLista::class, MovimientoLista::class] as $clase) {
            Livewire::actingAs($this->usuario())->test($clase)->assertSeeHtml('id="almacen_id"');
        }
    }

    public function test_reportes_no_ofrece_filtrar_por_almacen_si_hay_uno_solo(): void
    {
        Livewire::actingAs($this->usuario())->test(ReporteFiltro::class)->assertDontSee('Todos los almacenes');

        Almacen::factory()->create();

        Livewire::actingAs($this->usuario())->test(ReporteFiltro::class)->assertSee('Todos los almacenes');
    }
}
