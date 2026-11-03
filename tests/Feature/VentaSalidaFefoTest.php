<?php

namespace Tests\Feature;

use App\Exceptions\StockInsuficienteException;
use App\Livewire\VentaForm;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Services\StockMovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las ventas descuentan primero del lote que vence antes (FEFO), dejan los lotes sin fecha al final y
 * nunca venden de un lote ya vencido.
 */
class VentaSalidaFefoTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;

    private Producto $producto;

    private StockMovimientoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = Almacen::unicoActivo();
        $this->producto = Producto::factory()->create(['maneja_vencimiento' => true, 'nombre' => 'Fungicida Cobre']);
        $this->service = new StockMovimientoService(new LoteRepository, new StockMovimientoRepository);
    }

    private function lote(?string $vence, int $cantidad): Lote
    {
        return Lote::factory()->for($this->producto, 'producto')->create([
            'almacen_id' => $this->almacen->id,
            'fecha_vencimiento' => $vence,
            'cantidad' => $cantidad,
        ]);
    }

    private function vender(int $cantidad): void
    {
        $this->service->registrarSalidaEnAlmacen($this->producto, $this->almacen->id, $cantidad, User::factory()->create(), 'venta');
    }

    public function test_sale_primero_el_lote_que_vence_antes_y_sigue_con_el_siguiente(): void
    {
        $lejos = $this->lote(today()->addMonths(10)->toDateString(), 50);
        $cerca = $this->lote(today()->addDays(20)->toDateString(), 10);
        $medio = $this->lote(today()->addMonths(3)->toDateString(), 50);

        $this->vender(25);

        $this->assertSame(0, $cerca->fresh()->cantidad);
        $this->assertSame(35, $medio->fresh()->cantidad);
        $this->assertSame(50, $lejos->fresh()->cantidad);
    }

    public function test_un_lote_vencido_no_se_vende_aunque_sea_el_que_vence_primero(): void
    {
        $vencido = $this->lote(today()->subDays(5)->toDateString(), 30);
        $vigente = $this->lote(today()->addMonths(2)->toDateString(), 30);

        $this->vender(10);

        $this->assertSame(30, $vencido->fresh()->cantidad);
        $this->assertSame(20, $vigente->fresh()->cantidad);
    }

    public function test_un_lote_que_vence_hoy_todavia_se_puede_vender(): void
    {
        $hoy = $this->lote(today()->toDateString(), 10);

        $this->vender(4);

        $this->assertSame(6, $hoy->fresh()->cantidad);
    }

    public function test_los_lotes_sin_fecha_salen_despues_de_los_que_tienen_fecha(): void
    {
        $sinFecha = $this->lote(null, 30);
        $conFecha = $this->lote(today()->addMonths(6)->toDateString(), 30);

        $this->vender(10);

        $this->assertSame(20, $conFecha->fresh()->cantidad);
        $this->assertSame(30, $sinFecha->fresh()->cantidad);

        $this->vender(25);

        $this->assertSame(0, $conFecha->fresh()->cantidad);
        $this->assertSame(25, $sinFecha->fresh()->cantidad);
    }

    public function test_si_solo_queda_stock_vencido_la_venta_se_rechaza_con_un_mensaje_claro(): void
    {
        $vencido = $this->lote(today()->subDays(1)->toDateString(), 30);

        try {
            $this->vender(5);
            $this->fail('Debió rechazar la venta.');
        } catch (StockInsuficienteException $e) {
            $this->assertStringContainsString('30 unidades en lotes vencidos', $e->getMessage());
            $this->assertStringContainsString('Producto vencido', $e->getMessage());
        }

        $this->assertSame(30, $vencido->fresh()->cantidad);
    }

    public function test_si_falta_stock_sin_que_haya_vencidos_el_mensaje_es_el_de_siempre(): void
    {
        $this->lote(today()->addMonths(2)->toDateString(), 3);

        $this->expectException(StockInsuficienteException::class);
        $this->expectExceptionMessage('solo hay 3 disponibles');

        $this->vender(5);
    }

    public function test_la_salida_general_tambien_salta_los_vencidos(): void
    {
        $vencido = $this->lote(today()->subDays(2)->toDateString(), 30);
        $vigente = $this->lote(today()->addMonths(1)->toDateString(), 30);

        $this->service->registrarSalida($this->producto, 10, User::factory()->create());

        $this->assertSame(30, $vencido->fresh()->cantidad);
        $this->assertSame(20, $vigente->fresh()->cantidad);
    }

    public function test_el_formulario_de_ventas_muestra_el_aviso_de_stock_vencido(): void
    {
        $this->lote(today()->subDays(1)->toDateString(), 30);

        Livewire::actingAs(User::factory()->create())
            ->test(VentaForm::class)
            ->call('elegirCliente', Cliente::factory()->create()->id)
            ->call('elegirProductoLinea', 0, $this->producto->id)
            ->set('lineas.0.cantidad', 2)
            ->call('guardar')
            ->assertSee('lotes vencidos');

        $this->assertDatabaseCount('ventas', 0);
    }
}
