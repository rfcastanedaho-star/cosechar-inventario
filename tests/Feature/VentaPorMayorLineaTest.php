<?php

namespace Tests\Feature;

use App\Livewire\ProductoForm;
use App\Livewire\VentaForm;
use App\Livewire\VentaLista;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class VentaPorMayorLineaTest extends TestCase
{
    use RefreshDatabase;

    private function venta(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(VentaForm::class);
    }

    private function producto(float $precio = 20): Producto
    {
        return Producto::factory()->create(['precio' => $precio, 'maneja_vencimiento' => false]);
    }

    // ---------- El producto ya no guarda precio por mayor ----------

    public function test_el_formulario_de_productos_no_tiene_precio_por_mayor(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ProductoForm::class)
            ->assertDontSee('Precio por mayor')
            ->assertDontSee('Desde cuántas unidades');
    }

    public function test_las_columnas_del_producto_se_eliminaron_y_la_venta_guarda_la_marca(): void
    {
        $this->assertFalse(Schema::hasColumn('productos', 'precio_mayor'));
        $this->assertFalse(Schema::hasColumn('productos', 'cantidad_mayor'));
        $this->assertTrue(Schema::hasColumn('venta_detalles', 'por_mayor'));
    }

    // ---------- La casilla en cada línea de la venta ----------

    public function test_una_linea_nueva_no_es_por_mayor_y_propone_el_precio_normal(): void
    {
        $this->venta()
            ->assertSet('lineas.0.por_mayor', false)
            ->call('elegirProductoLinea', 0, $this->producto()->id)
            ->assertSet('lineas.0.precio_unitario', '20.00')
            ->assertSee('Precio unitario (S/)')
            ->assertSee('Venta por mayor');
    }

    public function test_la_cantidad_ya_no_cambia_el_precio_sola(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->producto()->id)
            ->set('lineas.0.cantidad', 500)
            ->assertSet('lineas.0.precio_unitario', '20.00');
    }

    public function test_al_marcar_por_mayor_se_pide_escribir_el_precio_por_mayor(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->producto()->id)
            ->set('lineas.0.por_mayor', true)
            ->assertSet('lineas.0.precio_unitario', '')
            ->assertSee('Precio por mayor (S/)');
    }

    public function test_al_desmarcar_vuelve_el_precio_normal(): void
    {
        $this->venta()
            ->call('elegirProductoLinea', 0, $this->producto()->id)
            ->set('lineas.0.por_mayor', true)
            ->set('lineas.0.precio_unitario', '15')
            ->set('lineas.0.por_mayor', false)
            ->assertSet('lineas.0.precio_unitario', '20.00');
    }

    public function test_marcar_por_mayor_antes_de_elegir_el_producto_no_rompe_y_deja_el_precio_vacio(): void
    {
        $this->venta()
            ->set('lineas.0.por_mayor', true)
            ->call('elegirProductoLinea', 0, $this->producto()->id)
            ->assertSet('lineas.0.precio_unitario', '')
            ->assertHasNoErrors();
    }

    public function test_el_precio_por_mayor_no_puede_superar_el_precio_normal(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = $this->producto(20);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 100]);

        $this->venta()
            ->set('cliente_nombre', 'Agricola San Isidro')
            ->set('almacen_id', $almacen->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.por_mayor', true)
            ->set('lineas.0.precio_unitario', '25')
            ->call('guardar')
            ->assertHasErrors(['lineas.0.precio_unitario']);

        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_el_precio_por_mayor_es_obligatorio_si_se_marca_la_casilla(): void
    {
        $this->venta()
            ->set('cliente_nombre', 'Agricola San Isidro')
            ->call('elegirProductoLinea', 0, $this->producto()->id)
            ->set('lineas.0.por_mayor', true)
            ->call('guardar')
            ->assertHasErrors(['lineas.0.precio_unitario' => 'required']);
    }

    public function test_la_venta_se_guarda_con_la_linea_marcada_por_mayor_y_la_otra_normal(): void
    {
        $almacen = Almacen::factory()->create();
        $mayor = $this->producto(20);
        $normal = $this->producto(10);
        foreach ([$mayor, $normal] as $producto) {
            Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 100]);
        }

        $this->venta()
            ->set('cliente_nombre', 'Agricola San Isidro')
            ->set('almacen_id', $almacen->id)
            ->call('agregarLinea')
            ->call('elegirProductoLinea', 0, $mayor->id)
            ->set('lineas.0.cantidad', 12)
            ->set('lineas.0.por_mayor', true)
            ->set('lineas.0.precio_unitario', '15')
            ->call('elegirProductoLinea', 1, $normal->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $detalleMayor = $venta->detalles->firstWhere('producto_id', $mayor->id);
        $detalleNormal = $venta->detalles->firstWhere('producto_id', $normal->id);

        $this->assertTrue($detalleMayor->por_mayor);
        $this->assertSame('15.00', $detalleMayor->precio_unitario);
        $this->assertFalse($detalleNormal->por_mayor);
        $this->assertSame('190.00', $venta->total);
    }

    public function test_el_total_usa_el_precio_por_mayor_escrito(): void
    {
        $componente = $this->venta()
            ->call('elegirProductoLinea', 0, $this->producto()->id)
            ->set('lineas.0.cantidad', 12)
            ->set('lineas.0.por_mayor', true)
            ->set('lineas.0.precio_unitario', '15');

        $this->assertEqualsWithDelta(180.0, $componente->instance()->total, 0.001);
    }

    // ---------- Dónde se ve ----------

    public function test_el_comprobante_y_el_historial_marcan_la_linea_por_mayor(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = $this->producto(20);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 100]);

        $this->venta()
            ->set('cliente_nombre', 'Agricola San Isidro')
            ->set('almacen_id', $almacen->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.por_mayor', true)
            ->set('lineas.0.precio_unitario', '15')
            ->call('guardar');

        $venta = Venta::latest('id')->firstOrFail();

        $html = view('comprobantes.venta', ['venta' => $venta->load('almacen', 'responsable', 'detalles.producto')])->render();
        $this->assertStringContainsString('por mayor', $html);

        Livewire::actingAs(User::factory()->create())
            ->test(VentaLista::class)
            ->assertSee('por mayor');
    }
}
