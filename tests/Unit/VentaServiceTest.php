<?php

namespace Tests\Unit;

use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\StockMovimientoService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaServiceTest extends TestCase
{
    use RefreshDatabase;

    private VentaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new VentaService(
            new StockMovimientoService(new LoteRepository, new StockMovimientoRepository),
        );
    }

    public function test_registra_una_venta_y_descuenta_el_stock_del_almacen_elegido(): void
    {
        $almacen = Almacen::factory()->create();
        $responsable = User::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 50]);

        $venta = $this->service->registrar(
            [
                'cliente_nombre' => 'Juan Pérez',
                'cliente_documento' => '12345678',
                'almacen_id' => $almacen->id,
                'fecha' => '2026-09-20',
                'numero_comprobante' => null,
            ],
            [
                ['producto_id' => $producto->id, 'cantidad' => 20, 'precio_unitario' => 15],
            ],
            $responsable,
        );

        $this->assertSame(300.0, (float) $venta->total);
        $this->assertSame(30, $lote->fresh()->cantidad);
    }

    public function test_no_descuenta_stock_de_otro_almacen(): void
    {
        $almacenOrigen = Almacen::factory()->create();
        $otroAlmacen = Almacen::factory()->create();
        $responsable = User::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        // Stock suficiente, pero en OTRO almacén.
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $otroAlmacen->id, 'cantidad' => 100]);

        $this->expectException(StockInsuficienteException::class);

        $this->service->registrar(
            [
                'cliente_nombre' => 'Juan Pérez',
                'cliente_documento' => null,
                'almacen_id' => $almacenOrigen->id,
                'fecha' => '2026-09-20',
                'numero_comprobante' => null,
            ],
            [
                ['producto_id' => $producto->id, 'cantidad' => 10, 'precio_unitario' => 15],
            ],
            $responsable,
        );
    }

    public function test_calcula_el_total_con_varias_lineas(): void
    {
        $almacen = Almacen::factory()->create();
        $responsable = User::factory()->create();
        $productoA = Producto::factory()->create();
        $productoB = Producto::factory()->create();
        Lote::factory()->for($productoA, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 50]);
        Lote::factory()->for($productoB, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 50]);

        $venta = $this->service->registrar(
            [
                'cliente_nombre' => 'Cliente X',
                'cliente_documento' => null,
                'almacen_id' => $almacen->id,
                'fecha' => '2026-09-20',
                'numero_comprobante' => null,
            ],
            [
                ['producto_id' => $productoA->id, 'cantidad' => 5, 'precio_unitario' => 10],
                ['producto_id' => $productoB->id, 'cantidad' => 2, 'precio_unitario' => 30],
            ],
            $responsable,
        );

        $this->assertSame(110.0, (float) $venta->total);
    }
}
