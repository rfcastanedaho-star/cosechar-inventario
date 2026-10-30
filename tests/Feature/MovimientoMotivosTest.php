<?php

namespace Tests\Feature;

use App\Livewire\MovimientoForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MovimientoMotivosTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function motivosDeSalidaValidos(): array
    {
        return [
            'merma' => ['merma'],
            'producto dañado' => ['producto_danado'],
            'ajuste por conteo' => ['ajuste_inventario'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function motivosDeSalidaInvalidos(): array
    {
        return [
            'venta (se registra en Ventas)' => ['venta'],
            'transferencia (tiene su propia pestaña)' => ['transferencia'],
            'inventado' => ['robo'],
        ];
    }

    #[DataProvider('motivosDeSalidaValidos')]
    public function test_la_salida_acepta_los_motivos_manuales(string $motivo): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 20]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'salida')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('cantidad', 3)
            ->set('motivo', $motivo)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stock_movimientos', ['tipo' => 'salida', 'motivo' => $motivo, 'cantidad' => 3]);
    }

    #[DataProvider('motivosDeSalidaInvalidos')]
    public function test_la_salida_manual_rechaza_venta_transferencia_y_motivos_inventados(string $motivo): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 20]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'salida')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('cantidad', 3)
            ->set('motivo', $motivo)
            ->call('guardar')
            ->assertHasErrors(['motivo']);

        $this->assertSame(20, $lote->fresh()->cantidad);
    }

    public function test_el_desplegable_de_salida_no_ofrece_venta_ni_transferencia(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'salida')
            ->assertSee('Merma')
            ->assertSee('Producto dañado')
            ->assertSee('Ajuste por conteo')
            ->assertDontSee('value="venta"', false)
            ->assertDontSee('value="transferencia"', false);
    }

    public function test_la_entrada_suma_al_lote_existente_con_motivo_ajuste_por_conteo(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 10]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('lote_id', $lote->id)
            ->set('cantidad', 4)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('creado', true);

        $this->assertSame(14, $lote->fresh()->cantidad);
        $this->assertDatabaseHas('stock_movimientos', [
            'lote_id' => $lote->id, 'tipo' => 'entrada', 'cantidad' => 4, 'motivo' => 'ajuste_inventario',
        ]);
    }

    public function test_la_entrada_manual_no_crea_lotes_nuevos(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 10]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('lote_id', $lote->id)
            ->set('cantidad', 4)
            ->call('guardar');

        $this->assertSame(1, Lote::count());
    }

    public function test_la_entrada_exige_elegir_un_lote(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('cantidad', 4)
            ->call('guardar')
            ->assertHasErrors(['lote_id' => 'required']);

        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_la_entrada_rechaza_un_lote_de_otro_producto_o_de_otro_almacen(): void
    {
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $otroProducto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $loteEnSur = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $sur->id, 'cantidad' => 10]);
        $loteAjeno = Lote::factory()->for($otroProducto, 'producto')->create(['almacen_id' => $norte->id, 'cantidad' => 10]);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $norte->id)
            ->set('cantidad', 4);

        $componente->set('lote_id', $loteEnSur->id)->call('guardar')->assertHasErrors(['lote_id']);
        $componente->set('lote_id', $loteAjeno->id)->call('guardar')->assertHasErrors(['lote_id']);

        $this->assertSame(10, $loteEnSur->fresh()->cantidad);
        $this->assertSame(10, $loteAjeno->fresh()->cantidad);
    }

    public function test_al_cambiar_de_producto_o_almacen_se_limpia_el_lote_elegido(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $otro = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 10]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('lote_id', $lote->id)
            ->set('producto_id', $otro->id)
            ->assertSet('lote_id', null);
    }

    public function test_la_pantalla_de_entrada_lista_los_lotes_y_avisa_si_no_hay(): void
    {
        $almacen = Almacen::factory()->create();
        $conLotes = Producto::factory()->create(['maneja_vencimiento' => false]);
        $sinLotes = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($conLotes, 'producto')->create(['almacen_id' => $almacen->id, 'numero_lote' => 'L-VISIBLE', 'cantidad' => 7]);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('almacen_id', $almacen->id)
            ->set('producto_id', $conLotes->id)
            ->set('buscarLote', 'L-VIS')
            ->assertSee('L-VISIBLE')
            ->assertSee('Ajuste por conteo')
            ->assertDontSee('Saldo inicial')
            ->assertDontSee('Fecha de vencimiento');

        $componente->set('producto_id', $sinLotes->id)
            ->assertSee('no tiene lotes en este almacén')
            ->assertSee('Compras');
    }
}
