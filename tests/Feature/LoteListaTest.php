<?php

namespace Tests\Feature;

use App\Livewire\LoteLista;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoteListaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function operador(): User
    {
        return User::factory()->create(['rol' => 'operador_almacen']);
    }

    private function lote(array $datos = [], array $producto = []): Lote
    {
        return Lote::factory()
            ->for(Producto::factory()->create(['maneja_vencimiento' => false, ...$producto]), 'producto')
            ->create(['almacen_id' => Almacen::first()->id, 'cantidad' => 10, ...$datos]);
    }

    // ---------- Acceso y menú ----------

    public function test_la_pantalla_pide_iniciar_sesion_y_aparece_en_el_menu(): void
    {
        $this->get(route('lotes.index'))->assertRedirect(route('login'));

        $this->actingAs($this->admin())->get(route('dashboard'))->assertSee(route('lotes.index'), false)->assertSee('Lotes');
    }

    // ---------- Estado ----------

    public function test_el_estado_sale_de_la_cantidad_y_del_vencimiento(): void
    {
        $this->assertSame('agotado', $this->lote(['cantidad' => 0, 'fecha_vencimiento' => now()->subDay()])->estado);
        $this->assertSame('vencido', $this->lote(['fecha_vencimiento' => now()->subDay()])->estado);
        $this->assertSame('por_vencer', $this->lote(['fecha_vencimiento' => now()->addDays(30)])->estado);
        $this->assertSame('por_vencer', $this->lote(['fecha_vencimiento' => now()])->estado);
        $this->assertSame('vigente', $this->lote(['fecha_vencimiento' => now()->addDays(31)])->estado);
        $this->assertSame('vigente', $this->lote(['fecha_vencimiento' => null])->estado);
    }

    // ---------- Tabla ----------

    public function test_la_tabla_muestra_producto_lote_vencimiento_cantidad_y_fecha_de_creacion(): void
    {
        $lote = $this->lote(['numero_lote' => 'L-2026-001', 'cantidad' => 40, 'fecha_vencimiento' => '2027-06-30'], ['nombre' => 'Antracol 70 WP']);
        $lote->forceFill(['created_at' => '2026-10-05 14:30:00'])->save();

        Livewire::actingAs($this->admin())
            ->test(LoteLista::class)
            ->assertSeeInOrder(['Producto', 'N.º de lote', 'Vencimiento', 'Cantidad', 'Costo', 'Creado', 'Estado'])
            ->assertSee('Antracol 70 WP')
            ->assertSee('L-2026-001')
            ->assertSee('30/06/2027')
            ->assertSee('40')
            ->assertSee('05/10/2026');
    }

    public function test_un_lote_sin_vencimiento_muestra_no_aplica(): void
    {
        $this->lote(['fecha_vencimiento' => null]);

        Livewire::actingAs($this->admin())->test(LoteLista::class)->assertSee('No aplica');
    }

    public function test_el_costo_lo_ve_el_administrador_y_no_el_operador(): void
    {
        $this->lote(['costo_unitario' => 7.25]);

        Livewire::actingAs($this->admin())->test(LoteLista::class)->assertSee('S/ 7.25');
        Livewire::actingAs($this->operador())->test(LoteLista::class)->assertDontSee('S/ 7.25')->assertDontSee('Costo');
    }

    public function test_muestra_el_estado_de_cada_lote(): void
    {
        $this->lote(['numero_lote' => 'L-OK', 'fecha_vencimiento' => now()->addYear()]);
        $this->lote(['numero_lote' => 'L-PRONTO', 'fecha_vencimiento' => now()->addDays(10)]);
        $this->lote(['numero_lote' => 'L-VENC', 'fecha_vencimiento' => now()->subDays(3)]);
        $this->lote(['numero_lote' => 'L-CERO', 'cantidad' => 0]);

        Livewire::actingAs($this->admin())->test(LoteLista::class)
            ->assertSee('Vigente')->assertSee('Por vencer')->assertSee('Vencido')->assertSee('Agotado');
    }

    // ---------- Resumen ----------

    public function test_las_tarjetas_cuentan_lotes_con_stock_por_vencer_vencidos_y_agotados(): void
    {
        $this->lote(['fecha_vencimiento' => now()->addYear()]);
        $this->lote(['fecha_vencimiento' => now()->addYear()]);
        $this->lote(['fecha_vencimiento' => now()->addDays(10)]);
        $this->lote(['fecha_vencimiento' => now()->subDay()]);
        $this->lote(['cantidad' => 0]);

        $resumen = Livewire::actingAs($this->admin())->test(LoteLista::class)->instance()->resumen;

        $this->assertSame(['con_stock' => 4, 'por_vencer' => 1, 'vencidos' => 1, 'agotados' => 1], $resumen);
    }

    // ---------- Filtros ----------

    public function test_filtra_por_estado(): void
    {
        $this->lote(['numero_lote' => 'L-OK', 'fecha_vencimiento' => now()->addYear()]);
        $this->lote(['numero_lote' => 'L-VENC', 'fecha_vencimiento' => now()->subDays(3)]);
        $this->lote(['numero_lote' => 'L-CERO', 'cantidad' => 0]);

        Livewire::actingAs($this->admin())->test(LoteLista::class)
            ->set('estado', 'vencido')->assertSee('L-VENC')->assertDontSee('L-OK')->assertDontSee('L-CERO')
            ->set('estado', 'agotado')->assertSee('L-CERO')->assertDontSee('L-VENC')
            ->set('estado', 'vigente')->assertSee('L-OK')->assertDontSee('L-CERO')
            ->set('estado', 'inventado')->assertSee('L-OK')->assertSee('L-VENC');
    }

    public function test_busca_por_producto_codigo_marca_o_numero_de_lote(): void
    {
        $this->lote(['numero_lote' => 'L-AAA'], ['nombre' => 'Fungicida Cobre', 'codigo' => 'FUN-9', 'marca' => 'Bayer']);
        $this->lote(['numero_lote' => 'L-BBB'], ['nombre' => 'Urea Agricola']);

        foreach (['cobre', 'FUN-9', 'bayer', 'L-AAA'] as $termino) {
            Livewire::actingAs($this->admin())->test(LoteLista::class)
                ->set('buscar', $termino)->assertSee('L-AAA')->assertDontSee('L-BBB');
        }
    }

    public function test_los_comodines_de_like_se_buscan_como_texto(): void
    {
        $this->lote(['numero_lote' => 'L-AAA']);

        Livewire::actingAs($this->admin())->test(LoteLista::class)->set('buscar', '%')->assertDontSee('L-AAA');
    }

    // ---------- Ajustar ----------

    private function ajustar(Lote $lote, string $tipo, int|float|string $cantidad, string $motivo, ?User $usuario = null)
    {
        return Livewire::actingAs($usuario ?? $this->operador())
            ->test(LoteLista::class)
            ->call('iniciarAjuste', $lote->id, $tipo)
            ->set('ajusteCantidad', $cantidad)
            ->set('ajusteMotivo', $motivo)
            ->call('guardarAjuste');
    }

    public function test_reducir_descuenta_y_deja_el_movimiento_con_su_razon_y_responsable(): void
    {
        $lote = $this->lote(['cantidad' => 10]);
        $usuario = $this->operador();

        $this->ajustar($lote, 'salida', 4, 'producto_vencido', $usuario)->assertHasNoErrors()->assertSet('ajustandoId', null);

        $this->assertSame(6, $lote->fresh()->cantidad);
        $this->assertDatabaseHas('stock_movimientos', [
            'lote_id' => $lote->id, 'tipo' => 'salida', 'cantidad' => 4, 'motivo' => 'producto_vencido', 'responsable_id' => $usuario->id,
        ]);
    }

    public function test_aumentar_suma_al_lote(): void
    {
        $lote = $this->lote(['cantidad' => 10]);

        $this->ajustar($lote, 'entrada', 3, 'devolucion')->assertHasNoErrors();

        $this->assertSame(13, $lote->fresh()->cantidad);
        $this->assertDatabaseHas('stock_movimientos', ['lote_id' => $lote->id, 'tipo' => 'entrada', 'motivo' => 'devolucion']);
    }

    public function test_no_se_puede_reducir_mas_de_lo_disponible(): void
    {
        $lote = $this->lote(['cantidad' => 3]);

        $this->ajustar($lote, 'salida', 5, 'merma')->assertHasErrors(['ajusteCantidad']);

        $this->assertSame(3, $lote->fresh()->cantidad);
        $this->assertSame(0, StockMovimiento::count());
    }

    public function test_cantidad_y_razon_son_obligatorias_y_la_razon_debe_ser_del_tipo(): void
    {
        $lote = $this->lote(['cantidad' => 10]);

        $this->ajustar($lote, 'salida', '', 'merma')->assertHasErrors(['ajusteCantidad' => 'required']);
        $this->ajustar($lote, 'salida', 0, 'merma')->assertHasErrors(['ajusteCantidad' => 'min']);
        $this->ajustar($lote, 'salida', 1.5, 'merma')->assertHasErrors(['ajusteCantidad' => 'integer']);
        $this->ajustar($lote, 'salida', 1, '')->assertHasErrors(['ajusteMotivo' => 'required']);
        $this->ajustar($lote, 'entrada', 1, 'merma')->assertHasErrors(['ajusteMotivo']);
        $this->ajustar($lote, 'salida', 1, 'venta')->assertHasErrors(['ajusteMotivo']);

        $this->assertSame(10, $lote->fresh()->cantidad);
    }

    public function test_el_panel_ofrece_solo_las_razones_de_cada_tipo(): void
    {
        $lote = $this->lote();

        $reducir = Livewire::actingAs($this->operador())->test(LoteLista::class)->call('iniciarAjuste', $lote->id, 'salida');
        foreach (['Merma', 'Producto dañado', 'Producto vencido', 'Error de conteo', 'Devolución', 'Pérdida', 'Otro'] as $razon) {
            $reducir->assertSee($razon);
        }
        $reducir->assertDontSee('Compra')->assertDontSee('Saldo inicial')->assertDontSee('Anulación')->assertSee('Disponible');

        $aumentar = Livewire::actingAs($this->operador())->test(LoteLista::class)->call('iniciarAjuste', $lote->id, 'entrada');
        $aumentar->assertSee('Error de conteo')->assertSee('Devolución')->assertSee('Otro')
            ->assertDontSee('Merma')->assertDontSee('Producto vencido')->assertDontSee('Pérdida');
    }

    public function test_cancelar_cierra_el_panel_sin_cambios(): void
    {
        $lote = $this->lote();

        Livewire::actingAs($this->operador())->test(LoteLista::class)
            ->call('iniciarAjuste', $lote->id, 'salida')
            ->set('ajusteCantidad', 2)
            ->call('cancelarAjuste')
            ->assertSet('ajustandoId', null)
            ->assertSet('ajusteCantidad', '');

        $this->assertSame(10, $lote->fresh()->cantidad);
    }

    public function test_un_tipo_de_ajuste_inventado_no_abre_el_panel(): void
    {
        Livewire::actingAs($this->operador())->test(LoteLista::class)
            ->call('iniciarAjuste', $this->lote()->id, 'transferencia')
            ->assertSet('ajustandoId', null);
    }

    public function test_tras_ajustar_a_cero_el_lote_pasa_a_agotado(): void
    {
        $lote = $this->lote(['cantidad' => 2, 'numero_lote' => 'L-FIN']);

        $this->ajustar($lote, 'salida', 2, 'perdida')->assertSee('Agotado');
    }

    // ---------- Detalle del lote ----------

    public function test_el_detalle_muestra_los_movimientos_y_las_ventas_del_lote(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => Almacen::first()->id, 'cantidad' => 20, 'numero_lote' => 'L-DET']);
        $usuario = $this->operador();

        app(VentaService::class)->registrar(
            ['cliente_nombre' => 'Agricola San Isidro', 'cliente_documento' => null, 'almacen_id' => $lote->almacen_id, 'fecha' => '2026-10-30', 'numero_comprobante' => null],
            [['producto_id' => $producto->id, 'cantidad' => 6, 'precio_unitario' => '10']],
            $usuario,
        );
        $this->ajustar($lote->fresh(), 'salida', 1, 'merma', $usuario);

        Livewire::actingAs($usuario)->test(LoteLista::class)
            ->call('verDetalle', $lote->id)
            ->assertSet('detalleId', $lote->id)
            ->assertSee('Movimientos del lote')
            ->assertSee('Venta')
            ->assertSee('Merma')
            ->assertSee('Ventas que salieron de este lote')
            ->assertSee('Agricola San Isidro')
            ->call('verDetalle', $lote->id)
            ->assertSet('detalleId', null);
    }
}
