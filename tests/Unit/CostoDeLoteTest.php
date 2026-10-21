<?php

namespace Tests\Unit;

use App\Models\Almacen;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\CompraService;
use App\Services\StockMovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostoDeLoteTest extends TestCase
{
    use RefreshDatabase;

    private StockMovimientoService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = new StockMovimientoService(new LoteRepository, new StockMovimientoRepository);
    }

    public function test_una_compra_guarda_el_costo_unitario_en_cada_lote(): void
    {
        $servicio = new CompraService(new LoteRepository, $this->stock);
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        $compra = $servicio->registrar(
            ['proveedor_id' => Proveedor::factory()->create()->id, 'almacen_id' => Almacen::factory()->create()->id, 'fecha' => '2026-10-01', 'numero_comprobante' => null],
            [['producto_id' => $producto->id, 'numero_lote' => 'L-COSTO', 'fecha_vencimiento' => null, 'cantidad' => 10, 'costo_unitario' => '8.50']],
            User::factory()->create(),
        );

        $this->assertSame('8.50', $compra->detalles->first()->lote->fresh()->costo_unitario);
    }

    public function test_el_lote_nace_sin_costo_si_no_viene_de_una_compra(): void
    {
        $this->assertNull(Lote::factory()->create()->fresh()->costo_unitario);
    }

    public function test_la_transferencia_lleva_el_costo_al_lote_del_destino(): void
    {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $origen->id, 'numero_lote' => 'L-T', 'cantidad' => 20, 'costo_unitario' => 12.5,
        ]);

        $this->stock->transferir($producto, $origen->id, $destino->id, 8, User::factory()->create());

        $this->assertSame('12.50', Lote::where('almacen_id', $destino->id)->firstOrFail()->costo_unitario);
    }

    public function test_si_el_lote_del_destino_ya_existe_el_costo_se_promedia_por_cantidad(): void
    {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $origen->id, 'numero_lote' => 'L-P', 'cantidad' => 20, 'costo_unitario' => 6]);
        $existente = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $destino->id, 'numero_lote' => 'L-P', 'cantidad' => 10, 'costo_unitario' => 4]);

        $this->stock->transferir($producto, $origen->id, $destino->id, 5, User::factory()->create());

        // (10 × 4,00 + 5 × 6,00) / 15 = 4,666… → 4,67
        $this->assertSame('4.67', $existente->fresh()->costo_unitario);
        $this->assertSame(15, $existente->fresh()->cantidad);
    }

    public function test_si_el_lote_del_destino_no_tenia_costo_toma_el_del_origen(): void
    {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $origen->id, 'numero_lote' => 'L-Q', 'cantidad' => 20, 'costo_unitario' => 7]);
        $existente = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $destino->id, 'numero_lote' => 'L-Q', 'cantidad' => 3, 'costo_unitario' => null]);

        $this->stock->transferir($producto, $origen->id, $destino->id, 4, User::factory()->create());

        $this->assertSame('7.00', $existente->fresh()->costo_unitario);
    }

    public function test_transferir_un_lote_sin_costo_no_borra_el_costo_del_destino(): void
    {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $origen->id, 'numero_lote' => 'L-R', 'cantidad' => 20, 'costo_unitario' => null]);
        $existente = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $destino->id, 'numero_lote' => 'L-R', 'cantidad' => 3, 'costo_unitario' => 9]);

        $this->stock->transferir($producto, $origen->id, $destino->id, 4, User::factory()->create());

        $this->assertSame('9.00', $existente->fresh()->costo_unitario);
    }

    public function test_transferir_un_lote_sin_costo_crea_el_destino_sin_costo(): void
    {
        $origen = Almacen::factory()->create();
        $destino = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $origen->id, 'numero_lote' => 'L-S', 'cantidad' => 20]);

        $this->stock->transferir($producto, $origen->id, $destino->id, 4, User::factory()->create());

        $this->assertNull(Lote::where('almacen_id', $destino->id)->firstOrFail()->costo_unitario);
    }

    public function test_la_migracion_rellena_el_costo_de_los_lotes_que_ya_venian_de_una_compra(): void
    {
        $conCompra = Lote::factory()->create(['costo_unitario' => null]);
        $sinCompra = Lote::factory()->create(['costo_unitario' => null]);
        $yaConCosto = Lote::factory()->create(['costo_unitario' => 99]);
        $compra = Compra::factory()->create();
        CompraDetalle::create(['compra_id' => $compra->id, 'lote_id' => $conCompra->id, 'cantidad' => 5, 'costo_unitario' => 3.25]);

        $migracion = require database_path('migrations/2026_10_23_100000_add_costo_unitario_to_lotes_table.php');
        $migracion->completarCostos();

        $this->assertSame('3.25', $conCompra->fresh()->costo_unitario);
        $this->assertNull($sinCompra->fresh()->costo_unitario);
        $this->assertSame('99.00', $yaConCosto->fresh()->costo_unitario);
    }
}
