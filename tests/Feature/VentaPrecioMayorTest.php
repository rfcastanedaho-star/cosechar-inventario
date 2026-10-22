<?php

namespace Tests\Feature;

use App\Livewire\VentaForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class VentaPrecioMayorTest extends TestCase
{
    use RefreshDatabase;

    private function venta(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(VentaForm::class);
    }

    private function conMayor(): Producto
    {
        return Producto::factory()->create(['precio' => 20, 'precio_mayor' => 15, 'cantidad_mayor' => 12, 'maneja_vencimiento' => false]);
    }

    public function test_con_poca_cantidad_se_propone_el_precio_normal(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->assertSet('lineas.0.precio_unitario', '20.00');
    }

    public function test_al_llegar_a_la_cantidad_minima_pasa_al_precio_por_mayor(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->set('lineas.0.cantidad', 11)
            ->assertSet('lineas.0.precio_unitario', '20.00')
            ->set('lineas.0.cantidad', 12)
            ->assertSet('lineas.0.precio_unitario', '15.00')
            ->set('lineas.0.cantidad', 30)
            ->assertSet('lineas.0.precio_unitario', '15.00');
    }

    public function test_si_baja_de_la_cantidad_minima_vuelve_al_precio_normal(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->set('lineas.0.cantidad', 20)
            ->assertSet('lineas.0.precio_unitario', '15.00')
            ->set('lineas.0.cantidad', 3)
            ->assertSet('lineas.0.precio_unitario', '20.00');
    }

    public function test_elegir_el_producto_con_la_cantidad_ya_alta_usa_el_precio_por_mayor(): void
    {
        $this->venta()
            ->set('lineas.0.cantidad', 24)
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->assertSet('lineas.0.precio_unitario', '15.00');
    }

    public function test_un_producto_sin_precio_por_mayor_siempre_usa_el_normal(): void
    {
        $normal = Producto::factory()->create(['precio' => 10, 'precio_mayor' => null, 'cantidad_mayor' => null]);

        $this->venta()
            ->call('elegirProductoLinea', 0, $normal->id)
            ->set('lineas.0.cantidad', 500)
            ->assertSet('lineas.0.precio_unitario', '10.00');
    }

    public function test_un_precio_escrito_a_mano_no_se_pisa_al_cambiar_la_cantidad(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->set('lineas.0.precio_unitario', '18')
            ->set('lineas.0.cantidad', 50)
            ->assertSet('lineas.0.precio_unitario', '18')
            ->set('lineas.0.cantidad', 2)
            ->assertSet('lineas.0.precio_unitario', '18');
    }

    public function test_si_se_borra_el_precio_escrito_vuelve_el_automatico(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->set('lineas.0.precio_unitario', '18')
            ->set('lineas.0.precio_unitario', '')
            ->set('lineas.0.cantidad', 12)
            ->assertSet('lineas.0.precio_unitario', '15.00');
    }

    public function test_cambiar_de_producto_recalcula_el_precio_si_no_se_habia_escrito_a_mano(): void
    {
        $otro = Producto::factory()->create(['precio' => 8, 'precio_mayor' => null, 'cantidad_mayor' => null]);

        $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->call('limpiarProductoLinea', 0)
            ->call('elegirProductoLinea', 0, $otro->id)
            ->assertSet('lineas.0.precio_unitario', '8.00');
    }

    public function test_cada_linea_calcula_su_propio_precio(): void
    {
        $this->venta()
            ->call('agregarLinea')
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->call('elegirProductoLinea', 1, $this->conMayor()->id)
            ->set('lineas.1.cantidad', 12)
            ->assertSet('lineas.0.precio_unitario', '20.00')
            ->assertSet('lineas.1.precio_unitario', '15.00');
    }

    public function test_cambiar_la_cantidad_sin_producto_elegido_no_rompe(): void
    {
        $this->venta()
            ->set('lineas.0.cantidad', 12)
            ->assertSet('lineas.0.precio_unitario', '')
            ->set('lineas.0.cantidad', '')
            ->assertHasNoErrors();
    }

    public function test_el_total_usa_el_precio_aplicado(): void
    {
        $componente = $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->set('lineas.0.cantidad', 12);

        $this->assertEqualsWithDelta(180.0, $componente->instance()->total, 0.001);
    }

    public function test_la_pantalla_explica_el_precio_por_mayor_y_avisa_cuando_se_aplica(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->assertSee('Por mayor: S/ 15.00 desde 12 unidades')
            ->assertDontSee('Precio por mayor aplicado')
            ->set('lineas.0.cantidad', 12)
            ->assertSee('Precio por mayor aplicado');
    }

    public function test_no_dice_que_se_aplico_si_el_vendedor_puso_su_propio_precio(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->conMayor()->id)
            ->set('lineas.0.cantidad', 12)
            ->set('lineas.0.precio_unitario', '17')
            ->assertDontSee('Precio por mayor aplicado');
    }

    public function test_un_producto_sin_precio_por_mayor_no_muestra_esa_leyenda(): void
    {
        $normal = Producto::factory()->create(['precio' => 10, 'precio_mayor' => null, 'cantidad_mayor' => null]);

        $this->venta()
            ->call('elegirProductoLinea', 0, $normal->id)
            ->assertDontSee('Por mayor:');
    }

    public function test_la_venta_se_guarda_con_el_precio_por_mayor_aplicado(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = $this->conMayor();
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 100]);

        $this->venta()
            ->set('cliente_nombre', 'Agricola San Isidro')
            ->set('almacen_id', $almacen->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 12)
            ->call('guardar')
            ->assertHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame('15.00', $venta->detalles->first()->precio_unitario);
        $this->assertSame('180.00', $venta->total);
    }
}
