<?php

namespace Tests\Feature;

use App\Livewire\ProductoForm;
use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Support\AuditoriaEtiquetas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Usa lotes» (opcional) reemplaza a «Maneja fecha de vencimiento»: al marcarlo se abre una pantallita
 * con N.º de lote, vencimiento y stock inicial. Los productos que no usan lotes tienen un lote automático.
 */
class ProductoUsaLotesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function nuevo(): Testable
    {
        return Livewire::actingAs($this->admin())
            ->test(ProductoForm::class)
            ->set('nombre', 'Hintecx')
            ->set('categoria_id', Categoria::factory()->create()->id)
            ->set('unidad_medida', 'litro')
            ->set('precio', '95')
            ->set('precio_costo', '70')
            ->set('stock_minimo', 5);
    }

    private function texto(Testable $componente): string
    {
        return preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($componente->html()), ENT_QUOTES | ENT_HTML5));
    }

    public function test_la_casilla_se_llama_usa_lotes_y_ya_no_hay_maneja_fecha_de_vencimiento(): void
    {
        $texto = $this->texto(Livewire::actingAs($this->admin())->test(ProductoForm::class));

        $this->assertStringContainsString('Usa lotes', $texto);
        $this->assertStringContainsString('(opcional)', $texto);
        $this->assertStringNotContainsString('Maneja fecha de vencimiento', $texto);
        $this->assertStringNotContainsString('no se puede cambiar', $texto);
    }

    public function test_sin_marcar_la_casilla_no_aparece_la_pantallita(): void
    {
        $texto = $this->texto($this->nuevo());

        $this->assertStringNotContainsString('Fecha de vencimiento', $texto);
        $this->assertStringContainsString('Stock inicial (opcional)', $texto);
    }

    public function test_al_marcar_usa_lotes_se_abre_la_pantallita_con_lote_vencimiento_y_stock_inicial(): void
    {
        $componente = $this->nuevo()->set('maneja_vencimiento', true)->assertSet('mostrarLotes', true);

        $texto = $this->texto($componente);

        $this->assertStringContainsString('N.º de lote', $texto);
        $this->assertStringContainsString('Fecha de vencimiento', $texto);
        $this->assertStringContainsString('Stock inicial', $texto);
        $this->assertStringContainsString('Actualizar', $texto);
    }

    public function test_cancelar_desmarca_la_casilla_y_borra_lo_escrito_en_la_pantallita(): void
    {
        $this->nuevo()
            ->set('maneja_vencimiento', true)
            ->set('numero_lote_inicial', 'L-1')
            ->set('stock_inicial', '10')
            ->call('cancelarLotes')
            ->assertSet('maneja_vencimiento', false)
            ->assertSet('mostrarLotes', false)
            ->assertSet('numero_lote_inicial', '')
            ->assertSet('stock_inicial', '');
    }

    public function test_desmarcar_la_casilla_cierra_la_pantallita(): void
    {
        $this->nuevo()->set('maneja_vencimiento', true)->set('maneja_vencimiento', false)->assertSet('mostrarLotes', false);
    }

    public function test_actualizar_con_stock_exige_numero_de_lote_y_vencimiento(): void
    {
        $this->nuevo()
            ->set('maneja_vencimiento', true)
            ->set('stock_inicial', '30')
            ->call('aplicarLotes')
            ->assertHasErrors(['numero_lote_inicial' => 'required', 'vencimiento_inicial' => 'required'])
            ->assertSet('mostrarLotes', true);
    }

    public function test_actualizar_con_los_datos_completos_cierra_la_pantallita_y_muestra_el_resumen(): void
    {
        $componente = $this->nuevo()
            ->set('maneja_vencimiento', true)
            ->set('numero_lote_inicial', 'L-2026-01')
            ->set('vencimiento_inicial', '2027-06-30')
            ->set('stock_inicial', '30')
            ->call('aplicarLotes')
            ->assertHasNoErrors()
            ->assertSet('mostrarLotes', false);

        $texto = $this->texto($componente);

        $this->assertStringContainsString('L-2026-01', $texto);
        $this->assertStringContainsString('30/06/2027', $texto);
        $this->assertStringContainsString('30 unidades', $texto);
        $this->assertStringNotContainsString('Stock inicial (opcional)', $texto);
    }

    public function test_un_producto_con_lotes_guarda_su_lote_con_numero_vencimiento_y_stock(): void
    {
        $this->nuevo()
            ->set('maneja_vencimiento', true)
            ->set('numero_lote_inicial', 'L-2026-01')
            ->set('vencimiento_inicial', '2027-06-30')
            ->set('stock_inicial', '30')
            ->call('guardar')
            ->assertHasNoErrors();

        $producto = Producto::where('nombre', 'Hintecx')->firstOrFail();
        $lote = Lote::where('producto_id', $producto->id)->firstOrFail();

        $this->assertTrue($producto->maneja_vencimiento);
        $this->assertSame('L-2026-01', $lote->numero_lote);
        $this->assertSame('2027-06-30', $lote->fecha_vencimiento->toDateString());
        $this->assertSame(30, $lote->cantidad);
        $this->assertSame('70.00', $lote->costo_unitario);
        $this->assertSame('saldo_inicial', StockMovimiento::where('lote_id', $lote->id)->firstOrFail()->motivo);
    }

    public function test_un_producto_sin_lotes_con_stock_inicial_va_al_lote_automatico(): void
    {
        $this->nuevo()->set('stock_inicial', '20')->call('guardar')->assertHasNoErrors();

        $lote = Lote::firstOrFail();

        $this->assertSame(Lote::SIN_LOTE, $lote->numero_lote);
        $this->assertNull($lote->fecha_vencimiento);
        $this->assertSame(20, $lote->cantidad);
    }

    public function test_usar_lotes_sin_stock_inicial_es_opcional_y_no_crea_lote(): void
    {
        $this->nuevo()->set('maneja_vencimiento', true)->call('guardar')->assertHasNoErrors();

        $this->assertTrue(Producto::where('nombre', 'Hintecx')->firstOrFail()->maneja_vencimiento);
        $this->assertDatabaseCount('lotes', 0);
    }

    public function test_el_nombre_del_lote_automatico_esta_reservado(): void
    {
        $this->nuevo()
            ->set('maneja_vencimiento', true)
            ->set('numero_lote_inicial', 'SIN-LOTE')
            ->set('vencimiento_inicial', '2027-06-30')
            ->set('stock_inicial', '30')
            ->call('guardar')
            ->assertHasErrors(['numero_lote_inicial' => 'not_in']);
    }

    public function test_el_formulario_queda_limpio_despues_de_guardar(): void
    {
        $this->nuevo()
            ->set('maneja_vencimiento', true)
            ->set('numero_lote_inicial', 'L-1')
            ->set('vencimiento_inicial', '2027-06-30')
            ->set('stock_inicial', '30')
            ->call('guardar')
            ->assertSet('numero_lote_inicial', '')
            ->assertSet('maneja_vencimiento', false)
            ->assertSet('mostrarLotes', false);
    }

    public function test_al_editar_el_administrador_puede_activar_usa_lotes_aunque_ya_tenga_lotes(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create();

        $componente = Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('maneja_vencimiento', true)
            ->assertSet('mostrarLotes', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue($producto->fresh()->maneja_vencimiento);
        $this->assertStringNotContainsString('Fecha de vencimiento', $this->texto($componente));
        $this->assertDatabaseHas('auditoria_cambios', ['auditable_id' => $producto->id, 'campo' => 'maneja_vencimiento']);
    }

    public function test_al_editar_se_explica_que_los_lotes_se_completan_en_lotes(): void
    {
        $producto = Producto::factory()->create();

        $texto = $this->texto(Livewire::actingAs($this->admin())->test(ProductoForm::class, ['producto' => $producto]));

        $this->assertStringContainsString('Usa lotes', $texto);
        $this->assertStringContainsString('en Lotes', $texto);
    }

    public function test_la_casilla_explica_para_que_sirve_y_no_ofrece_series(): void
    {
        $texto = $this->texto(Livewire::actingAs($this->admin())->test(ProductoForm::class));

        $this->assertStringContainsString('Permite rastrear productos por lote con fechas de vencimiento y control de inventario por lote', $texto);
        $this->assertStringNotContainsString('Serie', $texto);
    }

    private function productoConStockSinLotes(int $cantidad = 40): Producto
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['numero_lote' => Lote::SIN_LOTE, 'cantidad' => $cantidad, 'fecha_vencimiento' => null]);

        return $producto;
    }

    private function editar(Producto $producto): Testable
    {
        return Livewire::actingAs($this->admin())->test(ProductoForm::class, ['producto' => $producto]);
    }

    public function test_al_activar_lotes_en_un_producto_existente_se_ofrece_el_vencimiento_del_lote_inicial(): void
    {
        $producto = $this->productoConStockSinLotes();

        $this->assertStringNotContainsString('Fecha de vencimiento del lote inicial', $this->texto($this->editar($producto)));

        $texto = $this->texto($this->editar($producto)->set('maneja_vencimiento', true));

        $this->assertStringContainsString('Fecha de vencimiento del lote inicial', $texto);
        $this->assertStringContainsString('se creará un lote inicial con el stock actual', $texto);
        $this->assertStringContainsString('Si no conoce el vencimiento, déjelo vacío', $texto);
    }

    public function test_si_el_producto_ya_usaba_lotes_no_se_ofrece_el_lote_inicial(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);

        $this->assertStringNotContainsString('lote inicial', $this->texto($this->editar($producto)));
    }

    public function test_al_activar_lotes_el_stock_actual_pasa_a_un_lote_inicial_con_el_vencimiento(): void
    {
        $producto = $this->productoConStockSinLotes(40);

        $this->editar($producto)
            ->set('maneja_vencimiento', true)
            ->set('vencimiento_inicial', '2027-03-15')
            ->call('guardar')
            ->assertHasNoErrors();

        $lote = Lote::where('producto_id', $producto->id)->sole();

        $this->assertSame('INICIAL', $lote->numero_lote);
        $this->assertSame('2027-03-15', $lote->fecha_vencimiento->toDateString());
        $this->assertSame(40, $lote->cantidad);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_el_vencimiento_del_lote_inicial_es_opcional(): void
    {
        $producto = $this->productoConStockSinLotes(40);

        $this->editar($producto)->set('maneja_vencimiento', true)->call('guardar')->assertHasNoErrors();

        $lote = Lote::where('producto_id', $producto->id)->sole();

        $this->assertSame('INICIAL', $lote->numero_lote);
        $this->assertNull($lote->fecha_vencimiento);
    }

    public function test_el_vencimiento_del_lote_inicial_debe_ser_una_fecha(): void
    {
        $this->editar($this->productoConStockSinLotes())
            ->set('maneja_vencimiento', true)
            ->set('vencimiento_inicial', 'mañana')
            ->call('guardar')
            ->assertHasErrors(['vencimiento_inicial' => 'date']);
    }

    public function test_activar_lotes_sin_stock_no_crea_nada_y_un_lote_inicial_existente_se_respeta(): void
    {
        $sinStock = Producto::factory()->create(['maneja_vencimiento' => false]);
        $this->editar($sinStock)->set('maneja_vencimiento', true)->call('guardar')->assertHasNoErrors();
        $this->assertSame(0, Lote::where('producto_id', $sinStock->id)->count());

        $producto = $this->productoConStockSinLotes();
        Lote::factory()->for($producto, 'producto')->create(['numero_lote' => 'INICIAL', 'cantidad' => 3]);

        $this->editar($producto)->set('maneja_vencimiento', true)->call('guardar')->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['INICIAL', 'SIN-LOTE'], Lote::where('producto_id', $producto->id)->pluck('numero_lote')->all());
    }

    public function test_desactivar_lotes_no_toca_los_lotes_existentes(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        Lote::factory()->for($producto, 'producto')->create(['numero_lote' => 'L-7', 'cantidad' => 9, 'fecha_vencimiento' => '2027-01-01']);

        $this->editar($producto)->set('maneja_vencimiento', false)->call('guardar')->assertHasNoErrors();

        $lote = Lote::where('producto_id', $producto->id)->sole();
        $this->assertSame('L-7', $lote->numero_lote);
        $this->assertSame('2027-01-01', $lote->fecha_vencimiento->toDateString());
    }

    public function test_el_historial_de_cambios_lo_llama_usa_lotes(): void
    {
        $this->assertSame('Usa lotes', AuditoriaEtiquetas::campo('maneja_vencimiento'));
    }
}
