<?php

namespace Tests\Unit;

use App\Exceptions\StockInsuficienteException;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\StockMovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovimientoServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockMovimientoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StockMovimientoService(
            new LoteRepository,
            new StockMovimientoRepository,
        );
    }

    public function test_fifo_descuenta_del_lote_mas_antiguo_cuando_el_producto_no_maneja_vencimiento(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $responsable = User::factory()->create();

        $loteAntiguo = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-01-01', 'cantidad' => 50]);
        $loteReciente = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-03-01', 'cantidad' => 50]);

        $this->service->registrarSalida($producto, 20, $responsable);

        $this->assertSame(30, $loteAntiguo->fresh()->cantidad);
        $this->assertSame(50, $loteReciente->fresh()->cantidad);
    }

    public function test_fefo_descuenta_del_lote_que_vence_primero_cuando_el_producto_maneja_vencimiento(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $responsable = User::factory()->create();

        $loteVenceLejos = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-01-01', 'fecha_vencimiento' => '2026-12-31', 'cantidad' => 50]);
        $loteVenceCerca = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-02-01', 'fecha_vencimiento' => '2026-04-30', 'cantidad' => 50]);

        $this->service->registrarSalida($producto, 20, $responsable);

        $this->assertSame(30, $loteVenceCerca->fresh()->cantidad);
        $this->assertSame(50, $loteVenceLejos->fresh()->cantidad);
    }

    public function test_divide_la_salida_entre_varios_lotes_cuando_uno_solo_no_alcanza(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $responsable = User::factory()->create();

        $lote1 = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-01-01', 'cantidad' => 10]);
        $lote2 = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-02-01', 'cantidad' => 10]);
        $lote3 = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-03-01', 'cantidad' => 10]);

        $movimientos = $this->service->registrarSalida($producto, 25, $responsable);

        $this->assertSame(0, $lote1->fresh()->cantidad);
        $this->assertSame(0, $lote2->fresh()->cantidad);
        $this->assertSame(5, $lote3->fresh()->cantidad);
        $this->assertCount(3, $movimientos);
        $this->assertSame(25, $movimientos->sum('cantidad'));
        $this->assertTrue($movimientos->every(fn ($m) => $m->tipo === 'salida'));
    }

    public function test_no_descuenta_lotes_que_ya_estan_agotados(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $responsable = User::factory()->create();

        $loteAgotado = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-01-01', 'cantidad' => 0]);
        $loteConStock = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-02-01', 'cantidad' => 30]);

        $this->service->registrarSalida($producto, 10, $responsable);

        $this->assertSame(0, $loteAgotado->fresh()->cantidad);
        $this->assertSame(20, $loteConStock->fresh()->cantidad);
    }

    public function test_lanza_excepcion_si_el_stock_total_disponible_es_insuficiente(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $responsable = User::factory()->create();

        $lote = Lote::factory()->for($producto, 'producto')->create(['cantidad' => 5]);

        $this->expectException(StockInsuficienteException::class);

        $this->service->registrarSalida($producto, 10, $responsable);

        $this->assertSame(5, $lote->fresh()->cantidad);
    }

    public function test_no_modifica_ningun_lote_cuando_el_stock_es_insuficiente(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $responsable = User::factory()->create();

        $lote1 = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-01-01', 'cantidad' => 5]);
        $lote2 = Lote::factory()->for($producto, 'producto')->create(['fecha_ingreso' => '2026-02-01', 'cantidad' => 3]);

        try {
            $this->service->registrarSalida($producto, 100, $responsable);
        } catch (StockInsuficienteException) {
            // esperado
        }

        $this->assertSame(5, $lote1->fresh()->cantidad);
        $this->assertSame(3, $lote2->fresh()->cantidad);
        $this->assertSame(0, StockMovimiento::count());
    }

    public function test_lanza_excepcion_si_la_cantidad_de_salida_es_cero_o_negativa(): void
    {
        $producto = Producto::factory()->create();
        $responsable = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->registrarSalida($producto, 0, $responsable);
    }

    public function test_lanza_excepcion_si_la_cantidad_de_salida_es_negativa(): void
    {
        $producto = Producto::factory()->create();
        $responsable = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->registrarSalida($producto, -5, $responsable);
    }

    public function test_registrar_entrada_incrementa_la_cantidad_del_lote_y_crea_el_movimiento(): void
    {
        $producto = Producto::factory()->create();
        $responsable = User::factory()->create();
        $lote = Lote::factory()->for($producto, 'producto')->create(['cantidad' => 10]);

        $movimiento = $this->service->registrarEntrada($lote, 15, $responsable);

        $this->assertSame(25, $lote->fresh()->cantidad);
        $this->assertSame('entrada', $movimiento->tipo);
        $this->assertSame(15, $movimiento->cantidad);
        $this->assertSame($responsable->id, $movimiento->responsable_id);
    }

    public function test_lanza_excepcion_si_la_cantidad_de_entrada_es_cero_o_negativa(): void
    {
        $producto = Producto::factory()->create();
        $responsable = User::factory()->create();
        $lote = Lote::factory()->for($producto, 'producto')->create(['cantidad' => 10]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->registrarEntrada($lote, 0, $responsable);
    }
}
