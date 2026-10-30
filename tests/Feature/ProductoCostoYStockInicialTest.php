<?php

namespace Tests\Feature;

use App\Livewire\ProductoForm;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Services\ValorizacionService;
use App\Support\AuditoriaEtiquetas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoCostoYStockInicialTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    /** Formulario de registro con los datos mínimos válidos. */
    private function nuevo(?User $usuario = null): Testable
    {
        return Livewire::actingAs($usuario ?? $this->admin())
            ->test(ProductoForm::class)
            ->set('nombre', 'Hintecx')
            ->set('categoria_id', Categoria::factory()->create()->id)
            ->set('unidad_medida', 'litro')
            ->set('precio', '95')
            ->set('precio_costo', '70')
            ->set('stock_minimo', 5);
    }

    // ---------- Pantalla ----------

    public function test_el_formulario_pide_precio_costo_y_ofrece_stock_inicial_y_minimo(): void
    {
        $texto = preg_replace('/\s+/u', ' ', strip_tags(Livewire::actingAs($this->admin())->test(ProductoForm::class)->html()));

        $this->assertStringContainsString('Precio (S/) *', $texto);
        $this->assertStringContainsString('Precio costo (S/) *', $texto);
        $this->assertStringContainsString('Stock inicial (opcional)', $texto);
        $this->assertStringContainsString('Stock mínimo *', $texto);

        $this->assertLessThan(strpos($texto, 'Precio costo'), strpos($texto, 'Precio (S/)'));
        $this->assertLessThan(strpos($texto, 'Stock inicial'), strpos($texto, 'Precio costo'));
        $this->assertLessThan(strpos($texto, 'Stock mínimo'), strpos($texto, 'Stock inicial'));
    }

    public function test_al_editar_no_se_ofrece_el_stock_inicial(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => Producto::factory()->create()])
            ->assertSee('Precio costo')
            ->assertDontSee('Stock inicial');
    }

    // ---------- Precio costo ----------

    public function test_el_precio_costo_se_guarda_con_el_producto(): void
    {
        $this->nuevo()->call('guardar')->assertHasNoErrors();

        $this->assertSame('70.00', Producto::where('nombre', 'Hintecx')->firstOrFail()->precio_costo);
    }

    public function test_el_precio_costo_es_obligatorio_y_numerico_sin_negativos(): void
    {
        $this->nuevo()->set('precio_costo', '')->call('guardar')->assertHasErrors(['precio_costo' => 'required']);
        $this->nuevo()->set('precio_costo', 'abc')->call('guardar')->assertHasErrors(['precio_costo' => 'numeric']);
        $this->nuevo()->set('precio_costo', '-1')->call('guardar')->assertHasErrors(['precio_costo' => 'min']);
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_el_administrador_corrige_el_costo_y_queda_en_el_historial(): void
    {
        $producto = Producto::factory()->create(['precio' => 95, 'precio_costo' => 70]);

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->assertSet('precio_costo', '70.00')
            ->set('precio_costo', '72.50')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('72.50', $producto->fresh()->precio_costo);
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Producto::class, 'auditable_id' => $producto->id, 'campo' => 'precio_costo',
        ]);
        $this->assertSame('Precio de costo', AuditoriaEtiquetas::campo('precio_costo'));
        $this->assertSame('S/ 72.50', AuditoriaEtiquetas::valor('precio_costo', '72.5'));
    }

    public function test_el_operador_no_puede_cambiar_el_costo(): void
    {
        $producto = Producto::factory()->create(['precio_costo' => 70]);

        Livewire::actingAs(User::factory()->create(['rol' => 'operador_almacen']))
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('precio_costo', '1')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('70.00', $producto->fresh()->precio_costo);
    }

    // ---------- Stock inicial ----------

    public function test_sin_stock_inicial_el_producto_nace_sin_lotes(): void
    {
        $this->nuevo()->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseCount('lotes', 0);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_con_stock_inicial_se_crea_el_lote_inicial_con_el_costo_y_su_movimiento(): void
    {
        $almacen = Almacen::first();
        $usuario = $this->admin();

        $this->nuevo($usuario)->set('stock_inicial', '50')->call('guardar')->assertHasNoErrors();

        $producto = Producto::where('nombre', 'Hintecx')->firstOrFail();
        $lote = Lote::where('producto_id', $producto->id)->firstOrFail();

        $this->assertSame('SIN-LOTE', $lote->numero_lote);
        $this->assertSame($almacen->id, $lote->almacen_id);
        $this->assertSame(50, $lote->cantidad);
        $this->assertSame('70.00', $lote->costo_unitario);

        $movimiento = StockMovimiento::where('lote_id', $lote->id)->firstOrFail();
        $this->assertSame('entrada', $movimiento->tipo);
        $this->assertSame('saldo_inicial', $movimiento->motivo);
        $this->assertSame(50, $movimiento->cantidad);
        $this->assertSame($usuario->id, $movimiento->responsable_id);
    }

    public function test_el_stock_inicial_vale_a_costo_en_la_valorizacion(): void
    {
        $this->nuevo()->set('stock_inicial', '10')->call('guardar');

        $totales = app(ValorizacionService::class)->calcular()['totales'];

        $this->assertSame(10, $totales['unidades']);
        $this->assertEqualsWithDelta(700.0, $totales['valorCosto'], 0.001);
        $this->assertTrue($totales['completo']);
    }

    public function test_el_stock_inicial_acepta_cero_o_vacio_y_rechaza_negativos_y_decimales(): void
    {
        $this->nuevo()->set('stock_inicial', '0')->call('guardar')->assertHasNoErrors();
        $this->nuevo()->set('stock_inicial', '')->set('nombre', 'Otro')->call('guardar')->assertHasNoErrors();
        $this->nuevo()->set('stock_inicial', '-5')->call('guardar')->assertHasErrors(['stock_inicial' => 'min']);
        $this->nuevo()->set('stock_inicial', '2.5')->call('guardar')->assertHasErrors(['stock_inicial' => 'integer']);
    }

    public function test_un_producto_que_usa_lotes_exige_numero_y_fecha_del_stock_inicial(): void
    {
        $componente = $this->nuevo()->set('maneja_vencimiento', true)->set('stock_inicial', '20');

        $componente->assertSee('Fecha de vencimiento')
            ->call('guardar')
            ->assertHasErrors(['vencimiento_inicial' => 'required', 'numero_lote_inicial' => 'required']);
        $this->assertDatabaseCount('productos', 0);

        $componente->set('numero_lote_inicial', 'L-1')->set('vencimiento_inicial', '2027-06-30')->call('guardar')->assertHasNoErrors();

        $lote = Lote::firstOrFail();
        $this->assertSame('2027-06-30', $lote->fecha_vencimiento->toDateString());
    }

    public function test_si_no_hay_stock_inicial_no_se_pide_vencimiento(): void
    {
        $this->nuevo()->set('maneja_vencimiento', true)->call('guardar')->assertHasNoErrors();
    }

    public function test_un_producto_sin_lotes_no_pide_la_fecha(): void
    {
        $this->nuevo()->set('stock_inicial', '20')->assertDontSee('Fecha de vencimiento');
    }

    public function test_con_un_solo_almacen_no_se_pregunta_cual(): void
    {
        $this->nuevo()->set('stock_inicial', '20')->assertDontSee('Seleccione un almacén');
    }

    public function test_con_varios_almacenes_se_elige_donde_entra_el_stock_inicial(): void
    {
        $otro = Almacen::factory()->create();

        $componente = $this->nuevo()->set('stock_inicial', '20');

        $componente->assertSee('Seleccione un almacén')
            ->call('guardar')
            ->assertHasErrors(['almacen_id' => 'required']);
        $this->assertDatabaseCount('productos', 0);

        $componente->set('almacen_id', $otro->id)->call('guardar')->assertHasNoErrors();

        $this->assertSame($otro->id, Lote::firstOrFail()->almacen_id);
    }

    public function test_tras_guardar_el_formulario_queda_limpio_para_el_siguiente(): void
    {
        $this->nuevo()
            ->set('stock_inicial', '20')
            ->call('guardar')
            ->assertSet('stock_inicial', '')
            ->assertSet('precio_costo', '')
            ->assertSet('vencimiento_inicial', '')
            ->assertSet('nombre', '');
    }
}
