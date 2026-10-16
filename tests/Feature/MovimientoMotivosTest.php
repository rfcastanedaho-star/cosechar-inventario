<?php

namespace Tests\Feature;

use App\Livewire\MovimientoForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
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

    public function test_la_entrada_exige_un_motivo(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('numero_lote', 'L-1')
            ->set('cantidad', 5)
            ->call('guardar')
            ->assertHasErrors(['motivo' => 'required']);

        $this->assertDatabaseCount('lotes', 0);
    }

    #[DataProvider('motivosDeEntradaValidos')]
    public function test_la_entrada_acepta_saldo_inicial_y_ajuste_por_conteo(string $motivo): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('numero_lote', 'L-2')
            ->set('cantidad', 5)
            ->set('motivo', $motivo)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stock_movimientos', ['tipo' => 'entrada', 'motivo' => $motivo, 'cantidad' => 5]);
    }

    /** @return array<string, array{string}> */
    public static function motivosDeEntradaValidos(): array
    {
        return [
            'saldo inicial' => ['saldo_inicial'],
            'ajuste por conteo' => ['ajuste_inventario'],
        ];
    }

    #[DataProvider('motivosDeEntradaInvalidos')]
    public function test_la_entrada_rechaza_otros_motivos(string $motivo): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('numero_lote', 'L-3')
            ->set('cantidad', 5)
            ->set('motivo', $motivo)
            ->call('guardar')
            ->assertHasErrors(['motivo']);
    }

    /** @return array<string, array{string}> */
    public static function motivosDeEntradaInvalidos(): array
    {
        return [
            'donacion' => ['donacion'],
            'devolucion' => ['devolucion'],
            'merma' => ['merma'],
        ];
    }

    public function test_la_entrada_permite_el_mismo_numero_de_lote_en_otro_almacen_pero_no_en_el_mismo(): void
    {
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $norte->id, 'numero_lote' => 'L-MISMO']);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('numero_lote', 'L-MISMO')
            ->set('cantidad', 5)
            ->set('motivo', 'saldo_inicial');

        $componente->set('almacen_id', $norte->id)->call('guardar')->assertHasErrors(['numero_lote' => 'unique']);

        $componente->set('almacen_id', $sur->id)->call('guardar')->assertHasNoErrors();

        $this->assertSame(2, Lote::where('numero_lote', 'L-MISMO')->count());
    }

    public function test_la_pestana_transferencia_mueve_stock_entre_almacenes(): void
    {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $loteOrigen = Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $origen->id, 'numero_lote' => 'L-MOV', 'cantidad' => 30,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'transferencia')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $origen->id)
            ->set('almacen_destino_id', $destino->id)
            ->set('cantidad', 12)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('creado', true);

        $this->assertSame(18, $loteOrigen->fresh()->cantidad);
        $this->assertDatabaseHas('lotes', ['almacen_id' => $destino->id, 'numero_lote' => 'L-MOV', 'cantidad' => 12]);
        $this->assertSame(2, StockMovimiento::where('motivo', 'transferencia')->count());
    }

    public function test_la_transferencia_exige_un_destino_distinto_del_origen(): void
    {
        $origen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $origen->id, 'cantidad' => 30]);

        $componente = Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'transferencia')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $origen->id)
            ->set('cantidad', 5);

        $componente->call('guardar')->assertHasErrors(['almacen_destino_id' => 'required']);

        $componente->set('almacen_destino_id', $origen->id)->call('guardar')->assertHasErrors(['almacen_destino_id' => 'different']);

        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_la_transferencia_no_acepta_un_destino_desactivado(): void
    {
        $origen = Almacen::factory()->create();
        $cerrado = Almacen::factory()->create(['activo' => false]);
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $origen->id, 'cantidad' => 30]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'transferencia')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $origen->id)
            ->set('almacen_destino_id', $cerrado->id)
            ->set('cantidad', 5)
            ->call('guardar')
            ->assertHasErrors(['almacen_destino_id']);
    }

    public function test_la_transferencia_no_puede_mover_mas_de_lo_que_hay_en_el_origen(): void
    {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $origen->id, 'cantidad' => 4]);

        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->set('tipo', 'transferencia')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $origen->id)
            ->set('almacen_destino_id', $destino->id)
            ->set('cantidad', 10)
            ->call('guardar')
            ->assertHasErrors(['cantidad']);

        $this->assertSame(4, $lote->fresh()->cantidad);
        $this->assertDatabaseCount('stock_movimientos', 0);
    }

    public function test_la_pantalla_ofrece_las_tres_pestanas(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(MovimientoForm::class)
            ->assertSee('Entrada')
            ->assertSee('Salida')
            ->assertSee('Transferencia');
    }
}
