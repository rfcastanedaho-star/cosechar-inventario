<?php

namespace Tests\Unit;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\CompraService;
use App\Services\StockMovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompraService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CompraService(
            new LoteRepository,
            new StockMovimientoService(new LoteRepository, new StockMovimientoRepository),
        );
    }

    public function test_registra_una_compra_con_una_linea_y_actualiza_el_stock(): void
    {
        $proveedor = Proveedor::factory()->create();
        $almacen = Almacen::factory()->create();
        $responsable = User::factory()->create();
        $producto = Producto::factory()->create();

        $compra = $this->service->registrar(
            [
                'proveedor_id' => $proveedor->id,
                'almacen_id' => $almacen->id,
                'fecha' => '2026-09-15',
                'numero_comprobante' => 'F001-123',
            ],
            [
                [
                    'producto_id' => $producto->id,
                    'numero_lote' => 'LOTE-A1',
                    'fecha_vencimiento' => null,
                    'cantidad' => 50,
                    'costo_unitario' => 12.5,
                ],
            ],
            $responsable,
        );

        $this->assertSame(625.0, (float) $compra->total);
        $this->assertCount(1, $compra->detalles);

        $lote = $compra->detalles->first()->lote;
        $this->assertSame(50, $lote->fresh()->cantidad);
        $this->assertSame($almacen->id, $lote->almacen_id);
        $this->assertSame('LOTE-A1', $lote->numero_lote);
    }

    public function test_calcula_el_total_sumando_varias_lineas(): void
    {
        $proveedor = Proveedor::factory()->create();
        $almacen = Almacen::factory()->create();
        $responsable = User::factory()->create();
        $productoA = Producto::factory()->create();
        $productoB = Producto::factory()->create();

        $compra = $this->service->registrar(
            [
                'proveedor_id' => $proveedor->id,
                'almacen_id' => $almacen->id,
                'fecha' => '2026-09-15',
                'numero_comprobante' => null,
            ],
            [
                ['producto_id' => $productoA->id, 'numero_lote' => 'A1', 'fecha_vencimiento' => null, 'cantidad' => 10, 'costo_unitario' => 5],
                ['producto_id' => $productoB->id, 'numero_lote' => 'B1', 'fecha_vencimiento' => null, 'cantidad' => 4, 'costo_unitario' => 25],
            ],
            $responsable,
        );

        $this->assertSame(150.0, (float) $compra->total);
        $this->assertCount(2, $compra->detalles);
    }

    public function test_crea_un_movimiento_de_entrada_por_cada_linea(): void
    {
        $proveedor = Proveedor::factory()->create();
        $almacen = Almacen::factory()->create();
        $responsable = User::factory()->create();
        $producto = Producto::factory()->create();

        $compra = $this->service->registrar(
            [
                'proveedor_id' => $proveedor->id,
                'almacen_id' => $almacen->id,
                'fecha' => '2026-09-15',
                'numero_comprobante' => null,
            ],
            [
                ['producto_id' => $producto->id, 'numero_lote' => 'A1', 'fecha_vencimiento' => null, 'cantidad' => 30, 'costo_unitario' => 8],
            ],
            $responsable,
        );

        $lote = $compra->detalles->first()->lote;

        $this->assertDatabaseHas('stock_movimientos', [
            'lote_id' => $lote->id,
            'tipo' => 'entrada',
            'cantidad' => 30,
        ]);
    }
}
