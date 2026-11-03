<?php

namespace Tests\Feature;

use App\Livewire\VentaForm;
use App\Livewire\VentaLista;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ventas pide el método de pago (efectivo, tarjeta o transferencia; sin crédito). En efectivo se
 * escribe el monto recibido y el vuelto sale solo; con tarjeta o transferencia se cobra el total exacto.
 */
class VentaPagoTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = Almacen::unicoActivo();
        $this->producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 10]);
        Lote::factory()->for($this->producto, 'producto')->create(['almacen_id' => $this->almacen->id, 'cantidad' => 100]);
    }

    /** Venta de `$cantidad` unidades a S/ 10 (total = 10 × cantidad), lista para elegir el pago. */
    private function venta(int $cantidad = 1): Testable
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(VentaForm::class)
            ->call('elegirCliente', Cliente::factory()->create()->id)
            ->call('elegirProductoLinea', 0, $this->producto->id)
            ->set('lineas.0.cantidad', $cantidad);
    }

    // ---------- Pantalla ----------

    public function test_la_pantalla_ofrece_efectivo_tarjeta_y_transferencia_sin_credito(): void
    {
        $this->venta()
            ->assertSee('Método de pago')
            ->assertSee('Efectivo')
            ->assertSee('Tarjeta')
            ->assertSee('Transferencia')
            ->assertDontSee('Crédito')
            ->assertDontSee('Fiado')
            ->assertSet('metodo_pago', 'efectivo');
    }

    public function test_solo_el_efectivo_pide_monto_recibido(): void
    {
        $this->venta()
            ->assertSee('Monto recibido')
            ->assertSee('Vuelto a entregar')
            ->set('metodo_pago', 'tarjeta')
            ->assertDontSee('Monto recibido')
            ->assertSee('Se cobra el total exacto')
            ->set('metodo_pago', 'transferencia')
            ->assertDontSee('Vuelto a entregar');
    }

    // ---------- Vuelto en vivo ----------

    public function test_el_vuelto_sale_solo_con_el_monto_recibido(): void
    {
        // Producto de S/ 10 pagado con S/ 20: vuelto S/ 10.
        $c = $this->venta()->set('monto_recibido', '20');

        $this->assertSame(10.0, $c->instance()->vuelto);
        $this->assertNull($c->instance()->faltante);
        $c->assertSee('Vuelto a entregar')->assertSee('S/ 10.00');
    }

    public function test_el_vuelto_se_recalcula_al_cambiar_la_cantidad(): void
    {
        $c = $this->venta()->set('monto_recibido', '50');
        $this->assertSame(40.0, $c->instance()->vuelto);

        $c->set('lineas.0.cantidad', 3);
        $this->assertSame(20.0, $c->instance()->vuelto);
    }

    public function test_sin_monto_recibido_el_vuelto_es_cero(): void
    {
        $c = $this->venta();

        $this->assertNull($c->instance()->vuelto);
        $c->assertSee('S/ 0.00');
    }

    public function test_si_el_monto_no_alcanza_muestra_cuanto_falta(): void
    {
        $c = $this->venta(2)->set('monto_recibido', '15');

        $this->assertSame(5.0, $c->instance()->faltante);
        $this->assertNull($c->instance()->vuelto);
        $c->assertSee('Falta dinero')->assertSee('S/ 5.00');
    }

    public function test_el_boton_exacto_pone_el_total_como_monto_recibido(): void
    {
        $c = $this->venta(3)->call('montoExacto')->assertSet('monto_recibido', '30.00');

        $this->assertSame(0.0, $c->instance()->vuelto);
    }

    public function test_al_cambiar_de_metodo_se_borra_el_monto_recibido(): void
    {
        $this->venta()
            ->set('monto_recibido', '20')
            ->set('metodo_pago', 'tarjeta')
            ->assertSet('monto_recibido', '');
    }

    // ---------- Total y monto en vivo ----------

    public function test_cantidad_y_precio_se_actualizan_mientras_se_escribe(): void
    {
        $html = $this->venta()->html();

        $this->assertStringContainsString('wire:model.live.debounce.300ms="lineas.0.cantidad"', $html);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="lineas.0.precio_unitario"', $html);
    }

    public function test_el_total_sigue_a_la_cantidad_en_una_venta_por_mayor(): void
    {
        $c = $this->venta(30)
            ->set('lineas.0.por_mayor', true)
            ->set('lineas.0.precio_unitario', '9');

        $this->assertSame(270.0, $c->instance()->total);

        $c->set('lineas.0.cantidad', 20);

        $this->assertSame(180.0, $c->instance()->total);
        $c->assertSee('S/ 180.00');
    }

    public function test_el_vuelto_se_recalcula_al_cambiar_la_cantidad_con_un_monto_ya_escrito(): void
    {
        $c = $this->venta(30)->set('monto_recibido', '300');
        $this->assertSame(0.0, $c->instance()->vuelto);

        $c->set('lineas.0.cantidad', 20);

        $this->assertSame(100.0, $c->instance()->vuelto);
    }

    public function test_el_monto_exacto_acompana_al_total_cuando_cambian_cantidad_o_precio(): void
    {
        $c = $this->venta(3)
            ->call('montoExacto')
            ->assertSet('monto_recibido', '30.00')
            ->set('lineas.0.cantidad', 5)
            ->assertSet('monto_recibido', '50.00')
            ->set('lineas.0.precio_unitario', '8')
            ->assertSet('monto_recibido', '40.00');

        $this->assertSame(0.0, $c->instance()->vuelto);
    }

    public function test_si_se_escribe_el_monto_a_mano_deja_de_seguir_al_total(): void
    {
        $this->venta(3)
            ->call('montoExacto')
            ->set('monto_recibido', '100')
            ->set('lineas.0.cantidad', 5)
            ->assertSet('monto_recibido', '100');
    }
    // ---------- Guardado ----------

    public function test_en_efectivo_se_guardan_monto_recibido_y_vuelto(): void
    {
        $this->venta(1)->set('monto_recibido', '20')->call('guardar')->assertHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame('efectivo', $venta->metodo_pago);
        $this->assertSame('20.00', $venta->monto_recibido);
        $this->assertSame('10.00', $venta->vuelto);
    }

    public function test_en_efectivo_sin_monto_se_toma_el_pago_exacto(): void
    {
        $this->venta(2)->call('guardar')->assertHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame('20.00', $venta->monto_recibido);
        $this->assertSame('0.00', $venta->vuelto);
    }

    public function test_con_tarjeta_o_transferencia_se_cobra_el_total_exacto(): void
    {
        foreach (['tarjeta', 'transferencia'] as $metodo) {
            $this->venta(2)->set('metodo_pago', $metodo)->call('guardar')->assertHasNoErrors();

            $venta = Venta::latest('id')->firstOrFail();
            $this->assertSame($metodo, $venta->metodo_pago);
            $this->assertSame('20.00', $venta->monto_recibido);
            $this->assertSame('0.00', $venta->vuelto);
        }
    }

    public function test_un_monto_menor_que_el_total_no_deja_guardar_y_no_descuenta_stock(): void
    {
        $this->venta(2)
            ->set('monto_recibido', '15')
            ->call('guardar')
            ->assertHasErrors(['monto_recibido'])
            ->assertSee('faltan S/ 5.00');

        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(100, (int) Lote::where('producto_id', $this->producto->id)->sum('cantidad'));
    }

    public function test_un_monto_no_numerico_o_un_metodo_inventado_se_rechazan(): void
    {
        $this->venta()->set('monto_recibido', 'abc')->call('guardar')->assertHasErrors(['monto_recibido']);
        $this->venta()->set('metodo_pago', 'credito')->call('guardar')->assertHasErrors(['metodo_pago']);

        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_tras_guardar_el_pago_vuelve_a_efectivo_y_sin_monto(): void
    {
        $this->venta()
            ->set('metodo_pago', 'tarjeta')
            ->call('guardar')
            ->assertSet('metodo_pago', 'efectivo')
            ->assertSet('monto_recibido', '');
    }

    // ---------- Servicio (API y formulario comparten la misma regla) ----------

    public function test_el_servicio_rechaza_un_monto_menor_al_total_y_no_deja_nada_guardado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            app(VentaService::class)->registrar(
                ['cliente_nombre' => 'Ana', 'cliente_documento' => null, 'almacen_id' => $this->almacen->id, 'fecha' => '2026-11-02', 'numero_comprobante' => null, 'metodo_pago' => 'efectivo', 'monto_recibido' => 5],
                [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => 10]],
                User::factory()->create(),
            );
        } finally {
            $this->assertDatabaseCount('ventas', 0);
            $this->assertSame(100, (int) Lote::where('producto_id', $this->producto->id)->sum('cantidad'));
        }
    }

    public function test_sin_metodo_de_pago_la_venta_se_guarda_sin_datos_de_pago(): void
    {
        $venta = app(VentaService::class)->registrar(
            ['cliente_nombre' => 'Ana', 'cliente_documento' => null, 'almacen_id' => $this->almacen->id, 'fecha' => '2026-11-02', 'numero_comprobante' => null],
            [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => 10]],
            User::factory()->create(),
        );

        $this->assertNull($venta->metodo_pago);
        $this->assertNull($venta->monto_recibido);
        $this->assertSame('—', $venta->etiquetaMetodoPago());
    }

    // ---------- Comprobante, historial y reportes ----------

    public function test_el_comprobante_muestra_metodo_monto_recibido_y_vuelto(): void
    {
        $venta = Venta::factory()->create(['metodo_pago' => 'efectivo', 'monto_recibido' => 50, 'vuelto' => 12.5]);

        $this->actingAs(User::factory()->create())
            ->get(route('ventas.comprobante', $venta))
            ->assertOk();

        $html = view('comprobantes.venta', ['venta' => $venta->load('detalles.producto', 'almacen', 'responsable')])->render();

        $this->assertStringContainsString('Método de pago', $html);
        $this->assertStringContainsString('Efectivo', $html);
        $this->assertStringContainsString('S/ 50.00', $html);
        $this->assertStringContainsString('S/ 12.50', $html);
    }

    public function test_el_comprobante_de_tarjeta_no_muestra_vuelto(): void
    {
        $venta = Venta::factory()->create(['metodo_pago' => 'tarjeta', 'monto_recibido' => 30, 'vuelto' => 0]);

        $html = view('comprobantes.venta', ['venta' => $venta->load('detalles.producto', 'almacen', 'responsable')])->render();

        $this->assertStringContainsString('Tarjeta', $html);
        $this->assertStringNotContainsString('Vuelto', $html);
    }

    public function test_el_historial_muestra_el_metodo_de_pago_y_el_vuelto(): void
    {
        Venta::factory()->create(['metodo_pago' => 'efectivo', 'monto_recibido' => 50, 'vuelto' => 12.5]);
        Venta::factory()->create(['metodo_pago' => 'transferencia', 'monto_recibido' => 8, 'vuelto' => 0]);
        Venta::factory()->create(['metodo_pago' => null]);

        Livewire::actingAs(User::factory()->create())
            ->test(VentaLista::class)
            ->assertSee('Pago')
            ->assertSee('Efectivo')
            ->assertSee('Recibido S/ 50.00 · Vuelto S/ 12.50')
            ->assertSee('Transferencia');
    }
}
