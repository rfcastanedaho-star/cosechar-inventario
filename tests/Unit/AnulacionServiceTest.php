<?php

namespace Tests\Unit;

use App\Exceptions\AnulacionNoPermitidaException;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\AnulacionService;
use App\Services\CompraService;
use App\Services\StockMovimientoService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnulacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnulacionService $anulaciones;

    private CompraService $compras;

    private VentaService $ventas;

    protected function setUp(): void
    {
        parent::setUp();

        $movimientos = new StockMovimientoService(new LoteRepository, new StockMovimientoRepository);

        $this->anulaciones = new AnulacionService(new LoteRepository, new StockMovimientoRepository);
        $this->compras = new CompraService(new LoteRepository, $movimientos);
        $this->ventas = new VentaService($movimientos);
    }

    private function comprar(Almacen $almacen, Producto $producto, User $usuario, int $cantidad = 20)
    {
        return $this->compras->registrar(
            [
                'proveedor_id' => Proveedor::factory()->create()->id,
                'almacen_id' => $almacen->id,
                'fecha' => '2026-10-01',
                'numero_comprobante' => null,
            ],
            [['producto_id' => $producto->id, 'numero_lote' => 'L-'.uniqid(), 'fecha_vencimiento' => null, 'cantidad' => $cantidad, 'costo_unitario' => 10]],
            $usuario,
        );
    }

    public function test_anular_una_compra_devuelve_el_stock_y_guarda_quien_y_por_que(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $compra = $this->comprar($almacen, $producto, $admin);
        $lote = $compra->detalles->first()->lote;

        $this->anulaciones->anularCompra($compra, $admin, 'Se registró con el proveedor equivocado');

        $this->assertSame(0, $lote->fresh()->cantidad);
        $compra = $compra->fresh();
        $this->assertNotNull($compra->anulada_at);
        $this->assertSame($admin->id, $compra->anulada_por);
        $this->assertSame('Se registró con el proveedor equivocado', $compra->motivo_anulacion);
        $this->assertDatabaseHas('stock_movimientos', ['lote_id' => $lote->id, 'tipo' => 'salida', 'motivo' => 'ajuste_inventario', 'cantidad' => 20]);
    }

    public function test_no_se_puede_anular_una_compra_si_parte_del_stock_ya_salio(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $compra = $this->comprar($almacen, $producto, $admin, 20);

        $this->ventas->registrar(
            ['cliente_nombre' => 'X', 'cliente_documento' => null, 'almacen_id' => $almacen->id, 'fecha' => '2026-10-02', 'numero_comprobante' => null],
            [['producto_id' => $producto->id, 'cantidad' => 15, 'precio_unitario' => 12]],
            $admin,
        );

        try {
            $this->anulaciones->anularCompra($compra, $admin, 'Error de digitación');
            $this->fail('Debió lanzar AnulacionNoPermitidaException');
        } catch (AnulacionNoPermitidaException) {
            $this->assertNull($compra->fresh()->anulada_at);
            $this->assertSame(5, $compra->detalles->first()->lote->fresh()->cantidad);
        }
    }

    public function test_no_se_puede_anular_dos_veces(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $compra = $this->comprar(Almacen::factory()->create(), Producto::factory()->create(), $admin);

        $this->anulaciones->anularCompra($compra, $admin, 'Primera anulación');

        $this->expectException(AnulacionNoPermitidaException::class);
        $this->anulaciones->anularCompra($compra->fresh(), $admin, 'Segunda anulación');
    }

    public function test_anular_una_venta_devuelve_el_stock_a_los_mismos_lotes(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 40]);

        $venta = $this->ventas->registrar(
            ['cliente_nombre' => 'Ana', 'cliente_documento' => null, 'almacen_id' => $almacen->id, 'fecha' => '2026-10-02', 'numero_comprobante' => null],
            [['producto_id' => $producto->id, 'cantidad' => 12, 'precio_unitario' => 9]],
            $admin,
        );
        $this->assertSame(28, $lote->fresh()->cantidad);

        $this->anulaciones->anularVenta($venta, $admin, 'El cliente canceló el pedido');

        $this->assertSame(40, $lote->fresh()->cantidad);
        $venta = $venta->fresh();
        $this->assertNotNull($venta->anulada_at);
        $this->assertSame('El cliente canceló el pedido', $venta->motivo_anulacion);
    }

    public function test_una_venta_anterior_al_control_de_trazabilidad_no_se_anula_automaticamente(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $venta = Venta::factory()->create();

        $this->expectException(AnulacionNoPermitidaException::class);
        $this->anulaciones->anularVenta($venta, $admin, 'Intento sobre venta antigua');
    }
}
