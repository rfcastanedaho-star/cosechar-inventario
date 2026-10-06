<?php

namespace Tests\Feature;

use App\Livewire\CompraLista;
use App\Livewire\VentaLista;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\CompraService;
use App\Services\StockMovimientoService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnulacionListasTest extends TestCase
{
    use RefreshDatabase;

    private function compraDe(User $usuario, Almacen $almacen, Producto $producto)
    {
        return (new CompraService(new LoteRepository, new StockMovimientoService(new LoteRepository, new StockMovimientoRepository)))->registrar(
            ['proveedor_id' => Proveedor::factory()->create()->id, 'almacen_id' => $almacen->id, 'fecha' => '2026-10-01', 'numero_comprobante' => null],
            [['producto_id' => $producto->id, 'numero_lote' => 'L-1', 'fecha_vencimiento' => null, 'cantidad' => 10, 'costo_unitario' => 5]],
            $usuario,
        );
    }

    public function test_el_administrador_anula_una_compra_desde_la_lista(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $compra = $this->compraDe($admin, Almacen::factory()->create(), Producto::factory()->create());

        Livewire::actingAs($admin)
            ->test(CompraLista::class)
            ->call('iniciarAnulacion', $compra->id)
            ->assertSet('anulandoId', $compra->id)
            ->set('motivoAnulacion', 'Compra registrada por error')
            ->call('confirmarAnulacion')
            ->assertHasNoErrors()
            ->assertSet('anulandoId', null);

        $this->assertNotNull($compra->fresh()->anulada_at);
    }

    public function test_el_motivo_de_anulacion_es_obligatorio(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $compra = $this->compraDe($admin, Almacen::factory()->create(), Producto::factory()->create());

        Livewire::actingAs($admin)
            ->test(CompraLista::class)
            ->call('iniciarAnulacion', $compra->id)
            ->set('motivoAnulacion', 'ab')
            ->call('confirmarAnulacion')
            ->assertHasErrors(['motivoAnulacion']);

        $this->assertNull($compra->fresh()->anulada_at);
    }

    public function test_el_operador_no_puede_anular_compras(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $operador = User::factory()->create(['rol' => 'operador_almacen']);
        $compra = $this->compraDe($admin, Almacen::factory()->create(), Producto::factory()->create());

        Livewire::actingAs($operador)
            ->test(CompraLista::class)
            ->call('iniciarAnulacion', $compra->id)
            ->assertForbidden();

        $this->assertNull($compra->fresh()->anulada_at);
    }

    public function test_si_el_stock_ya_salio_la_lista_muestra_el_error_y_no_anula(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $compra = $this->compraDe($admin, $almacen, $producto);

        (new VentaService(new StockMovimientoService(new LoteRepository, new StockMovimientoRepository)))->registrar(
            ['cliente_nombre' => 'X', 'cliente_documento' => null, 'almacen_id' => $almacen->id, 'fecha' => '2026-10-02', 'numero_comprobante' => null],
            [['producto_id' => $producto->id, 'cantidad' => 8, 'precio_unitario' => 9]],
            $admin,
        );

        Livewire::actingAs($admin)
            ->test(CompraLista::class)
            ->call('iniciarAnulacion', $compra->id)
            ->set('motivoAnulacion', 'Error de digitación')
            ->call('confirmarAnulacion')
            ->assertSee('Parte de ese stock ya salió');

        $this->assertNull($compra->fresh()->anulada_at);
    }

    public function test_el_administrador_anula_una_venta_y_el_stock_vuelve(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 30]);

        $venta = (new VentaService(new StockMovimientoService(new LoteRepository, new StockMovimientoRepository)))->registrar(
            ['cliente_nombre' => 'Rosa', 'cliente_documento' => null, 'almacen_id' => $almacen->id, 'fecha' => '2026-10-02', 'numero_comprobante' => null],
            [['producto_id' => $producto->id, 'cantidad' => 10, 'precio_unitario' => 9]],
            $admin,
        );

        Livewire::actingAs($admin)
            ->test(VentaLista::class)
            ->call('iniciarAnulacion', $venta->id)
            ->set('motivoAnulacion', 'El cliente devolvió todo')
            ->call('confirmarAnulacion')
            ->assertHasNoErrors();

        $this->assertSame(30, $lote->fresh()->cantidad);
        $this->assertNotNull($venta->fresh()->anulada_at);
    }

    public function test_el_operador_no_puede_anular_ventas(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);
        $venta = Venta::factory()->create();

        Livewire::actingAs($operador)
            ->test(VentaLista::class)
            ->call('iniciarAnulacion', $venta->id)
            ->assertForbidden();
    }
}
