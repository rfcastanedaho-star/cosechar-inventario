<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\LoteLista;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los productos que usan lotes (📦) piden en la compra su «Información del lote»:
 * N.º de lote, fecha de vencimiento y una nota opcional.
 */
class CompraConLoteTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;

    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = Almacen::unicoActivo();
        $this->proveedor = Proveedor::factory()->create(['nombre' => 'Agro Sur']);
    }

    private function producto(bool $usaLotes = true): Producto
    {
        return Producto::factory()->create(['maneja_vencimiento' => $usaLotes, 'nombre' => 'Fungicida Cobre']);
    }

    private function compra(Producto $producto, int $cantidad = 10, string $costo = '5'): Testable
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(CompraForm::class)
            ->call('elegirProveedor', $this->proveedor->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', $cantidad)
            ->set('lineas.0.costo_unitario', $costo);
    }

    private function conLote(Testable $c, string $numero, ?string $vence = null, string $nota = ''): Testable
    {
        return $c
            ->set('lineas.0.numero_lote', $numero)
            ->set('lineas.0.fecha_vencimiento', $vence ?? now()->addYear()->toDateString())
            ->set('lineas.0.nota', $nota);
    }

    // ---------- Cajita y bloque ----------

    public function test_el_producto_con_lotes_lleva_la_cajita_en_el_selector_y_el_sin_lotes_no(): void
    {
        Producto::factory()->create(['nombre' => 'Con Lote Uno', 'maneja_vencimiento' => true]);
        Producto::factory()->create(['nombre' => 'Sin Lote Dos', 'maneja_vencimiento' => false]);

        $html = Livewire::actingAs(User::factory()->create())->test(CompraForm::class)
            ->call('alternarListaProducto', 0)
            ->html();

        $this->assertMatchesRegularExpression('/Con Lote Uno.*📦/su', $html);
        $this->assertDoesNotMatchRegularExpression('/Sin Lote Dos[^<]*(<span[^>]*>[^<]*<\/span>)?[^<]*<\/span>\s*📦/su', $html);
        $this->assertSame(1, substr_count($html, 'aria-label="Usa lotes">📦'));
    }

    public function test_al_elegir_un_producto_con_lotes_aparece_la_informacion_del_lote(): void
    {
        $this->compra($this->producto())
            ->assertSee('Información del lote')
            ->assertSee('N.º de lote')
            ->assertSee('Fecha de vencimiento')
            ->assertSee('Nota');
    }

    public function test_un_producto_sin_lotes_no_muestra_el_bloque(): void
    {
        $this->compra($this->producto(false))->assertDontSee('Información del lote');
    }

    public function test_al_cambiar_de_producto_se_borra_la_informacion_del_lote(): void
    {
        $otro = Producto::factory()->create(['maneja_vencimiento' => true]);

        $this->conLote($this->compra($this->producto()), 'L-1', null, 'caja golpeada')
            ->call('limpiarProductoLinea', 0)
            ->call('elegirProductoLinea', 0, $otro->id)
            ->assertSet('lineas.0.numero_lote', '')
            ->assertSet('lineas.0.fecha_vencimiento', '')
            ->assertSet('lineas.0.nota', '');
    }

    // ---------- Validación ----------

    public function test_numero_y_vencimiento_son_obligatorios_y_la_nota_no(): void
    {
        $this->compra($this->producto())
            ->call('revisar')
            ->assertHasErrors(['lineas.0.numero_lote' => 'required', 'lineas.0.fecha_vencimiento' => 'required'])
            ->assertHasNoErrors('lineas.0.nota');

        $this->assertDatabaseCount('compras', 0);
    }

    public function test_el_vencimiento_no_puede_ser_anterior_a_hoy_y_el_numero_sin_lote_esta_reservado(): void
    {
        $this->conLote($this->compra($this->producto()), 'L-1', now()->subDay()->toDateString())
            ->call('revisar')
            ->assertHasErrors(['lineas.0.fecha_vencimiento' => 'after_or_equal']);

        $this->conLote($this->compra($this->producto()), Lote::SIN_LOTE)
            ->call('revisar')
            ->assertHasErrors(['lineas.0.numero_lote' => 'not_in']);
    }

    public function test_el_numero_de_lote_y_la_nota_tienen_largo_maximo(): void
    {
        $this->conLote($this->compra($this->producto()), str_repeat('a', 51), null, str_repeat('n', 256))
            ->call('revisar')
            ->assertHasErrors(['lineas.0.numero_lote' => 'max', 'lineas.0.nota' => 'max']);
    }

    // ---------- Guardado ----------

    public function test_la_compra_crea_el_lote_con_numero_vencimiento_y_nota(): void
    {
        $vence = now()->addMonths(8)->toDateString();
        $producto = $this->producto();

        $this->conLote($this->compra($producto, 20, '4.50'), 'L-2026-07', $vence, 'llegó con la caja golpeada')
            ->call('revisar')
            ->assertHasNoErrors()
            ->assertSee('Lote L-2026-07')
            ->call('guardar')
            ->assertHasNoErrors();

        $lote = Lote::where('producto_id', $producto->id)->firstOrFail();
        $this->assertSame('L-2026-07', $lote->numero_lote);
        $this->assertSame($vence, $lote->fecha_vencimiento->toDateString());
        $this->assertSame('llegó con la caja golpeada', $lote->nota);
        $this->assertSame(20, $lote->cantidad);
        $this->assertSame('4.50', $lote->costo_unitario);
        $this->assertSame($lote->id, Compra::latest('id')->firstOrFail()->detalles->first()->lote_id);
    }

    public function test_el_mismo_producto_con_otro_numero_de_lote_crea_otro_lote(): void
    {
        $producto = $this->producto();

        $this->conLote($this->compra($producto, 10), 'L-1')->call('guardar')->assertHasNoErrors();
        $this->conLote($this->compra($producto, 15), 'L-2')->call('guardar')->assertHasNoErrors();

        $this->assertSame(['L-1' => 10, 'L-2' => 15], Lote::where('producto_id', $producto->id)->pluck('cantidad', 'numero_lote')->all());
    }

    public function test_el_mismo_numero_de_lote_suma_al_lote_existente_con_costo_promedio(): void
    {
        $producto = $this->producto();
        $vence = now()->addYear()->toDateString();

        $this->conLote($this->compra($producto, 10, '2'), 'L-1', $vence)->call('guardar')->assertHasNoErrors();
        $this->conLote($this->compra($producto, 30, '4'), 'L-1', $vence)
            ->assertSee('Este lote ya existe (10 unidades)')
            ->call('guardar')
            ->assertHasNoErrors();

        $lote = Lote::where('producto_id', $producto->id)->sole();
        $this->assertSame(40, $lote->cantidad);
        $this->assertSame('3.50', $lote->costo_unitario);
    }

    public function test_un_lote_existente_no_cambia_de_vencimiento(): void
    {
        $producto = $this->producto();

        $this->conLote($this->compra($producto, 10), 'L-1', now()->addYear()->toDateString())->call('guardar');

        $otra = now()->addMonths(3)->toDateString();
        $this->conLote($this->compra($producto, 5), 'L-1', $otra)
            ->call('revisar')
            ->assertSet('confirmando', false)
            ->assertHasErrors('lineas.0.fecha_vencimiento');

        $this->assertSame(10, Lote::where('producto_id', $producto->id)->sole()->cantidad);
    }

    public function test_el_mismo_lote_dos_veces_en_la_compra_exige_el_mismo_vencimiento(): void
    {
        $producto = $this->producto();

        $componente = $this->conLote($this->compra($producto, 5), 'L-1', now()->addYear()->toDateString())
            ->call('agregarLinea')
            ->call('elegirProductoLinea', 1, $producto->id)
            ->set('lineas.1.cantidad', 5)
            ->set('lineas.1.costo_unitario', '5')
            ->set('lineas.1.numero_lote', 'L-1')
            ->set('lineas.1.fecha_vencimiento', now()->addMonths(2)->toDateString());

        $componente->call('revisar')->assertHasErrors('lineas.1.fecha_vencimiento');

        $componente->set('lineas.1.fecha_vencimiento', now()->addYear()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(10, Lote::where('producto_id', $producto->id)->sole()->cantidad);
    }

    public function test_la_nota_del_lote_se_ve_en_el_detalle_del_lote(): void
    {
        $producto = $this->producto();
        $this->conLote($this->compra($producto, 10), 'L-1', null, 'revisar etiquetas')->call('guardar');

        $lote = Lote::where('producto_id', $producto->id)->sole();

        Livewire::actingAs(User::factory()->create())
            ->test(LoteLista::class)
            ->call('verDetalle', $lote->id)
            ->assertSee('revisar etiquetas');
    }
}
