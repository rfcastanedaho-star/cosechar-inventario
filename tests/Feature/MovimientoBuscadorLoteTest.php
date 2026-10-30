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

class MovimientoBuscadorLoteTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = Almacen::factory()->create();
        $this->producto = Producto::factory()->create(['maneja_vencimiento' => true]);
    }

    private function lote(string $numero, int $cantidad = 10, ?string $vence = '2027-06-30'): Lote
    {
        return Lote::factory()->for($this->producto, 'producto')->create([
            'almacen_id' => $this->almacen->id,
            'numero_lote' => $numero,
            'fecha_vencimiento' => $vence,
            'cantidad' => $cantidad,
        ]);
    }

    private function formulario(): Testable
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->call('elegirProducto', $this->producto->id)
            ->set('almacen_id', $this->almacen->id);
    }

    public function test_al_escribir_parte_del_numero_sugiere_los_lotes_que_coinciden(): void
    {
        $this->lote('L-2026-001');
        $this->lote('X-777');

        $this->formulario()
            ->set('buscarLote', '2026')
            ->assertSee('L-2026-001')
            ->assertDontSee('X-777');
    }

    public function test_la_sugerencia_muestra_vencimiento_y_stock(): void
    {
        $this->lote('L-001', 20, '2027-06-30');
        $this->lote('L-002', 5, null);

        $this->formulario()
            ->set('buscarLote', 'L-00')
            ->assertSee('vence 30/06/2027')
            ->assertSee('stock 20')
            ->assertSee('sin vencimiento')
            ->assertSee('stock 5');
    }

    public function test_solo_sugiere_lotes_del_producto_y_almacen_elegidos(): void
    {
        $this->lote('L-PROPIO');
        Lote::factory()->for($this->producto, 'producto')->create([
            'almacen_id' => Almacen::factory()->create()->id, 'numero_lote' => 'L-OTRO-ALMACEN',
        ]);
        Lote::factory()->for(Producto::factory()->create(), 'producto')->create([
            'almacen_id' => $this->almacen->id, 'numero_lote' => 'L-OTRO-PRODUCTO',
        ]);

        $this->formulario()
            ->set('buscarLote', 'L-')
            ->assertSee('L-PROPIO')
            ->assertDontSee('L-OTRO-ALMACEN')
            ->assertDontSee('L-OTRO-PRODUCTO');
    }

    public function test_sin_texto_no_muestra_sugerencias(): void
    {
        $this->lote('L-001');

        $this->formulario()
            ->assertDontSee('L-001')
            ->assertSee('Escriba el número de lote');
    }

    public function test_avisa_cuando_ningun_lote_coincide(): void
    {
        $this->lote('L-001');

        $this->formulario()
            ->set('buscarLote', 'zzz')
            ->assertSee('No se encontró ningún lote');
    }

    public function test_avisa_cuando_el_producto_no_tiene_lotes_en_el_almacen(): void
    {
        $this->formulario()
            ->assertSee('no tiene lotes en este almacén')
            ->assertSee('Compras');
    }

    public function test_pide_elegir_producto_antes_de_buscar(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->assertSee('Elija el producto para buscar sus lotes');
    }

    public function test_muestra_como_maximo_ocho_sugerencias(): void
    {
        foreach (range(1, 12) as $n) {
            $this->lote('LT-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT));
        }

        $componente = $this->formulario()->set('buscarLote', 'LT-');

        $this->assertCount(8, $componente->instance()->lotesSugeridos);
    }

    public function test_elegir_una_sugerencia_selecciona_el_lote_y_limpia_la_busqueda(): void
    {
        $lote = $this->lote('L-2026-001', 20, '2027-06-30');

        $this->formulario()
            ->set('buscarLote', '2026')
            ->call('elegirLote', $lote->id)
            ->assertSet('lote_id', $lote->id)
            ->assertSet('buscarLote', '')
            ->assertSee('L-2026-001')
            ->assertSee('Cambiar');
    }

    public function test_enter_elige_el_primer_lote_sugerido(): void
    {
        $this->lote('B-001');
        $primero = $this->lote('001');

        $this->formulario()
            ->set('buscarLote', '001')
            ->call('elegirPrimerLote')
            ->assertSet('lote_id', $primero->id);
    }

    public function test_enter_sin_coincidencias_no_elige_nada(): void
    {
        $this->lote('L-001');

        $this->formulario()
            ->set('buscarLote', 'zzz')
            ->call('elegirPrimerLote')
            ->assertSet('lote_id', null);
    }

    public function test_no_se_puede_elegir_un_lote_de_otro_producto_o_de_otro_almacen(): void
    {
        $ajeno = Lote::factory()->for(Producto::factory()->create(), 'producto')->create([
            'almacen_id' => $this->almacen->id, 'numero_lote' => 'L-AJENO',
        ]);
        $otroAlmacen = Lote::factory()->for($this->producto, 'producto')->create([
            'almacen_id' => Almacen::factory()->create()->id, 'numero_lote' => 'L-OTRO',
        ]);

        $this->formulario()
            ->call('elegirLote', $ajeno->id)
            ->assertSet('lote_id', null)
            ->call('elegirLote', $otroAlmacen->id)
            ->assertSet('lote_id', null)
            ->call('elegirLote', 99999)
            ->assertSet('lote_id', null);
    }

    public function test_cambiar_de_lote_lo_limpia(): void
    {
        $lote = $this->lote('L-001');

        $this->formulario()
            ->call('elegirLote', $lote->id)
            ->call('limpiarLote')
            ->assertSet('lote_id', null)
            ->assertSee('Escriba el número de lote');
    }

    public function test_cambiar_de_producto_o_almacen_limpia_el_lote_y_la_busqueda(): void
    {
        $lote = $this->lote('L-001');
        $otro = Almacen::factory()->create();

        $this->formulario()
            ->call('elegirLote', $lote->id)
            ->set('almacen_id', $otro->id)
            ->assertSet('lote_id', null)
            ->assertSet('buscarLote', '')
            ->set('almacen_id', $this->almacen->id)
            ->set('buscarLote', 'L-0')
            ->call('limpiarProducto')
            ->assertSet('buscarLote', '');
    }

    public function test_el_flujo_completo_suma_stock_eligiendo_el_lote_con_el_buscador(): void
    {
        $lote = $this->lote('L-2026-001', 10);

        $this->formulario()
            ->set('buscarLote', '2026')
            ->call('elegirPrimerLote')
            ->set('cantidad', 6)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('lote_id', null);

        $this->assertSame(16, $lote->fresh()->cantidad);
    }
}
