<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\CompraLista;
use App\Livewire\VentaForm;
use App\Livewire\VentaLista;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComprobantesTest extends TestCase
{
    use RefreshDatabase;

    private function compra(array $atributos = []): Compra
    {
        $compra = Compra::factory()->create($atributos + [
            'proveedor_id' => Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789'])->id,
            'almacen_id' => Almacen::factory()->create(['nombre' => 'Almacén Central'])->id,
            'responsable_id' => User::factory()->create(['name' => 'Rosa Registradora'])->id,
            'fecha' => '2026-10-05',
            'numero_comprobante' => 'F001-000123',
            'total' => 85,
        ]);

        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'FUN-1']);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $compra->almacen_id, 'numero_lote' => 'L-2026-001', 'fecha_vencimiento' => '2027-06-30']);
        CompraDetalle::create(['compra_id' => $compra->id, 'lote_id' => $lote->id, 'cantidad' => 10, 'costo_unitario' => 8.5]);

        return $compra;
    }

    private function venta(array $atributos = []): Venta
    {
        $venta = Venta::factory()->create($atributos + [
            'cliente_nombre' => 'Ana Pérez',
            'cliente_documento' => '12345678',
            'almacen_id' => Almacen::factory()->create(['nombre' => 'Almacén Central'])->id,
            'responsable_id' => User::factory()->create(['name' => 'Luis Vendedor'])->id,
            'fecha' => '2026-10-06',
            'numero_comprobante' => 'B001-000045',
            'total' => 60,
        ]);

        $producto = Producto::factory()->create(['nombre' => 'Herbicida Total', 'codigo' => 'HER-1']);
        VentaDetalle::create(['venta_id' => $venta->id, 'producto_id' => $producto->id, 'cantidad' => 3, 'precio_unitario' => 20]);

        return $venta;
    }

    // ---------- Acceso ----------

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('compras.comprobante', $this->compra()))->assertRedirect(route('login'));
        $this->get(route('ventas.comprobante', $this->venta()))->assertRedirect(route('login'));
    }

    public function test_cualquier_usuario_autenticado_obtiene_el_pdf_de_la_compra_y_de_la_venta(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        foreach ([route('compras.comprobante', $this->compra()), route('ventas.comprobante', $this->venta())] as $url) {
            $respuesta = $this->actingAs($operador)->get($url);

            $respuesta->assertOk();
            $this->assertStringContainsString('application/pdf', $respuesta->headers->get('Content-Type'));
        }
    }

    public function test_un_comprobante_inexistente_da_404(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/compras/99999/comprobante')->assertNotFound();
        $this->actingAs($usuario)->get('/ventas/99999/comprobante')->assertNotFound();
    }

    // ---------- Contenido ----------

    public function test_el_comprobante_de_compra_muestra_proveedor_lote_cantidades_y_total(): void
    {
        $compra = $this->compra();

        $html = view('comprobantes.compra', ['compra' => $compra->load('proveedor', 'almacen', 'responsable', 'detalles.lote.producto')])->render();

        $this->assertStringContainsString('COSECHAR E.I.R.L.', $html);
        $this->assertStringContainsString('Comprobante interno de compra', $html);
        $this->assertStringContainsString(str_pad((string) $compra->id, 6, '0', STR_PAD_LEFT), $html);
        $this->assertStringContainsString('Agro Andina SAC', $html);
        $this->assertStringContainsString('20123456789', $html);
        $this->assertStringContainsString('F001-000123', $html);
        $this->assertStringContainsString('Almacén Central', $html);
        $this->assertStringContainsString('05/10/2026', $html);
        $this->assertStringContainsString('FUN-1', $html);
        $this->assertStringContainsString('Fungicida Cobre', $html);
        $this->assertStringContainsString('L-2026-001', $html);
        $this->assertStringContainsString('30/06/2027', $html);
        $this->assertStringContainsString('S/ 8.50', $html);
        $this->assertStringContainsString('S/ 85.00', $html);
        $this->assertStringContainsString('Rosa Registradora', $html);
    }

    public function test_el_comprobante_de_venta_muestra_cliente_productos_y_total(): void
    {
        $venta = $this->venta();

        $html = view('comprobantes.venta', ['venta' => $venta->load('almacen', 'responsable', 'detalles.producto')])->render();

        $this->assertStringContainsString('Comprobante interno de venta', $html);
        $this->assertStringContainsString(str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT), $html);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('12345678', $html);
        $this->assertStringContainsString('B001-000045', $html);
        $this->assertStringContainsString('HER-1', $html);
        $this->assertStringContainsString('Herbicida Total', $html);
        $this->assertStringContainsString('S/ 20.00', $html);
        $this->assertStringContainsString('S/ 60.00', $html);
        $this->assertStringContainsString('Luis Vendedor', $html);
    }

    public function test_aclara_que_no_es_un_comprobante_de_pago_electronico(): void
    {
        $htmlVenta = view('comprobantes.venta', ['venta' => $this->venta()->load('almacen', 'responsable', 'detalles.producto')])->render();
        $htmlCompra = view('comprobantes.compra', ['compra' => $this->compra()->load('proveedor', 'almacen', 'responsable', 'detalles.lote.producto')])->render();

        foreach ([$htmlVenta, $htmlCompra] as $html) {
            $this->assertStringContainsString('no reemplaza', $html);
            $this->assertStringContainsString('SUNAT', $html);
        }
    }

    public function test_una_venta_sin_documento_ni_comprobante_no_rompe_el_pdf(): void
    {
        $venta = $this->venta(['cliente_documento' => null, 'numero_comprobante' => null]);

        $html = view('comprobantes.venta', ['venta' => $venta->load('almacen', 'responsable', 'detalles.producto')])->render();

        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('—', $html);
    }

    public function test_un_registro_anulado_lo_dice_con_quien_cuando_y_por_que(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Anulador', 'rol' => 'administrador']);
        $venta = $this->venta(['anulada_at' => '2026-10-07 10:00:00', 'anulada_por' => $admin->id, 'motivo_anulacion' => 'Pedido cancelado por el cliente']);
        $compra = $this->compra(['anulada_at' => '2026-10-07 10:00:00', 'anulada_por' => $admin->id, 'motivo_anulacion' => 'Proveedor equivocado']);

        $htmlVenta = view('comprobantes.venta', ['venta' => $venta->load('almacen', 'responsable', 'detalles.producto', 'anuladaPor')])->render();
        $htmlCompra = view('comprobantes.compra', ['compra' => $compra->load('proveedor', 'almacen', 'responsable', 'detalles.lote.producto', 'anuladaPor')])->render();

        $this->assertStringContainsString('ANULADO', $htmlVenta);
        $this->assertStringContainsString('Pedido cancelado por el cliente', $htmlVenta);
        $this->assertStringContainsString('Admin Anulador', $htmlVenta);
        $this->assertStringContainsString('ANULADO', $htmlCompra);
        $this->assertStringContainsString('Proveedor equivocado', $htmlCompra);
    }

    public function test_un_registro_vigente_no_dice_anulado(): void
    {
        $html = view('comprobantes.venta', ['venta' => $this->venta()->load('almacen', 'responsable', 'detalles.producto')])->render();

        $this->assertStringNotContainsString('ANULADO', $html);
    }

    // ---------- Botones ----------

    public function test_los_historiales_ofrecen_el_comprobante_de_cada_registro(): void
    {
        $compra = $this->compra();
        $venta = $this->venta();
        $admin = User::factory()->create(['rol' => 'administrador']);

        Livewire::actingAs($admin)->test(CompraLista::class)->assertSeeHtml(route('compras.comprobante', $compra))->assertSee('Comprobante');
        Livewire::actingAs($admin)->test(VentaLista::class)->assertSeeHtml(route('ventas.comprobante', $venta))->assertSee('Comprobante');
    }

    public function test_el_comprobante_tambien_se_ofrece_para_registros_anulados(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $venta = $this->venta(['anulada_at' => now(), 'anulada_por' => $admin->id, 'motivo_anulacion' => 'Error de digitación']);

        Livewire::actingAs($admin)->test(VentaLista::class)->assertSeeHtml(route('ventas.comprobante', $venta));
    }

    public function test_al_registrar_una_compra_se_ofrece_imprimir_su_comprobante(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test(CompraForm::class)
            ->call('elegirProveedor', Proveedor::factory()->create()->id)
            ->set('almacen_id', Almacen::factory()->create()->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.numero_lote', 'L-IMPRIMIR')
            ->set('lineas.0.cantidad', 4)
            ->set('lineas.0.costo_unitario', '5')
            ->call('guardar')
            ->assertHasNoErrors();

        $compra = Compra::latest('id')->firstOrFail();
        $componente->assertSee('Imprimir comprobante')->assertSeeHtml(route('compras.comprobante', $compra));
    }

    public function test_al_registrar_una_venta_se_ofrece_imprimir_su_comprobante(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 10]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 30]);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test(VentaForm::class)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('cliente_nombre', 'Ana Pérez')
            ->set('almacen_id', $almacen->id)
            ->set('lineas.0.cantidad', 2)
            ->call('guardar')
            ->assertHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $componente->assertSee('Imprimir comprobante')->assertSeeHtml(route('ventas.comprobante', $venta));
    }
}
