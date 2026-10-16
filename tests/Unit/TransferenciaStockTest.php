<?php

namespace Tests\Unit;

use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\StockMovimientoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TransferenciaStockTest extends TestCase
{
    use RefreshDatabase;

    private StockMovimientoService $service;

    private Almacen $origen;

    private Almacen $destino;

    private User $responsable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StockMovimientoService(new LoteRepository, new StockMovimientoRepository);
        $this->origen = Almacen::factory()->create();
        $this->destino = Almacen::factory()->create();
        $this->responsable = User::factory()->create();
    }

    public function test_transferir_descuenta_del_origen_y_suma_en_el_destino_con_el_mismo_lote(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $loteOrigen = Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $this->origen->id,
            'numero_lote' => 'L-TRF-1',
            'fecha_ingreso' => '2026-01-01',
            'fecha_vencimiento' => '2027-06-30',
            'cantidad' => 40,
        ]);

        $this->service->transferir($producto, $this->origen->id, $this->destino->id, 15, $this->responsable);

        $this->assertSame(25, $loteOrigen->fresh()->cantidad);
        $this->assertDatabaseHas('lotes', [
            'producto_id' => $producto->id,
            'almacen_id' => $this->destino->id,
            'numero_lote' => 'L-TRF-1',
            'cantidad' => 15,
        ]);

        $loteDestino = Lote::where('almacen_id', $this->destino->id)->firstOrFail();
        $this->assertSame('2027-06-30', $loteDestino->fecha_vencimiento->toDateString());
        $this->assertSame('2026-01-01', $loteDestino->fecha_ingreso->toDateString());
    }

    public function test_transferir_registra_una_salida_y_una_entrada_con_motivo_transferencia(): void
    {
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $this->origen->id, 'cantidad' => 30]);

        $movimientos = $this->service->transferir($producto, $this->origen->id, $this->destino->id, 10, $this->responsable);

        $this->assertCount(2, $movimientos);
        $this->assertSame(1, StockMovimiento::where('tipo', 'salida')->where('motivo', 'transferencia')->count());
        $this->assertSame(1, StockMovimiento::where('tipo', 'entrada')->where('motivo', 'transferencia')->count());
        $this->assertSame(
            [$this->responsable->id],
            StockMovimiento::pluck('responsable_id')->unique()->values()->all(),
        );
    }

    public function test_transferir_respeta_fefo_y_reparte_entre_varios_lotes_del_origen(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $primero = Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $this->origen->id, 'numero_lote' => 'L-A', 'fecha_vencimiento' => '2027-01-01', 'cantidad' => 10,
        ]);
        $segundo = Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $this->origen->id, 'numero_lote' => 'L-B', 'fecha_vencimiento' => '2027-09-01', 'cantidad' => 50,
        ]);

        $this->service->transferir($producto, $this->origen->id, $this->destino->id, 25, $this->responsable);

        $this->assertSame(0, $primero->fresh()->cantidad);
        $this->assertSame(35, $segundo->fresh()->cantidad);
        $this->assertDatabaseHas('lotes', ['almacen_id' => $this->destino->id, 'numero_lote' => 'L-A', 'cantidad' => 10]);
        $this->assertDatabaseHas('lotes', ['almacen_id' => $this->destino->id, 'numero_lote' => 'L-B', 'cantidad' => 15]);
        $this->assertSame(4, StockMovimiento::count());
    }

    public function test_transferir_suma_al_lote_del_destino_si_ya_existe_con_el_mismo_numero(): void
    {
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $this->origen->id, 'numero_lote' => 'L-X', 'cantidad' => 20,
        ]);
        $existente = Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $this->destino->id, 'numero_lote' => 'L-X', 'cantidad' => 5,
        ]);

        $this->service->transferir($producto, $this->origen->id, $this->destino->id, 8, $this->responsable);

        $this->assertSame(13, $existente->fresh()->cantidad);
        $this->assertSame(1, Lote::where('almacen_id', $this->destino->id)->count());
    }

    public function test_transferir_mas_de_lo_disponible_lanza_excepcion_y_no_cambia_nada(): void
    {
        $producto = Producto::factory()->create();
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $this->origen->id, 'cantidad' => 5]);

        try {
            $this->service->transferir($producto, $this->origen->id, $this->destino->id, 9, $this->responsable);
            $this->fail('Debió lanzar StockInsuficienteException.');
        } catch (StockInsuficienteException) {
            $this->assertSame(5, $lote->fresh()->cantidad);
            $this->assertSame(0, Lote::where('almacen_id', $this->destino->id)->count());
            $this->assertSame(0, StockMovimiento::count());
        }
    }

    public function test_transferir_al_mismo_almacen_lanza_excepcion(): void
    {
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $this->origen->id, 'cantidad' => 30]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->transferir($producto, $this->origen->id, $this->origen->id, 5, $this->responsable);
    }

    public function test_transferir_una_cantidad_cero_o_negativa_lanza_excepcion(): void
    {
        $producto = Producto::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->transferir($producto, $this->origen->id, $this->destino->id, 0, $this->responsable);
    }

    public function test_registrar_entrada_guarda_el_motivo_recibido(): void
    {
        $producto = Producto::factory()->create();
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $this->origen->id, 'cantidad' => 0]);

        $movimiento = $this->service->registrarEntrada($lote, 12, $this->responsable, 'saldo_inicial');

        $this->assertSame('saldo_inicial', $movimiento->motivo);
    }

    public function test_un_mismo_numero_de_lote_puede_existir_en_dos_almacenes_pero_no_dos_veces_en_el_mismo(): void
    {
        $producto = Producto::factory()->create();

        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $this->origen->id, 'numero_lote' => 'L-DUP']);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $this->destino->id, 'numero_lote' => 'L-DUP']);

        $this->assertSame(2, Lote::where('numero_lote', 'L-DUP')->count());

        $this->expectException(QueryException::class);

        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $this->origen->id, 'numero_lote' => 'L-DUP']);
    }
}
