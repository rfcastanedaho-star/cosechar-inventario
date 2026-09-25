<?php

namespace Tests\Unit;

use App\Models\Lote;
use App\Models\Producto;
use App\Repositories\LoteRepository;
use App\Repositories\ProductoRepository;
use App\Services\AlertaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertaServiceTest extends TestCase
{
    use RefreshDatabase;

    private AlertaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AlertaService(
            new ProductoRepository,
            new LoteRepository,
        );

        $this->travelTo(Carbon::parse('2026-06-01 10:00:00'));
    }

    public function test_incluye_lotes_que_vencen_dentro_de_30_dias(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['fecha_vencimiento' => '2026-06-15', 'cantidad' => 10]);

        $resultado = $this->service->lotesPorVencer();

        $this->assertTrue($resultado->contains('id', $lote->id));
    }

    public function test_incluye_lote_que_vence_exactamente_en_30_dias(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['fecha_vencimiento' => '2026-07-01', 'cantidad' => 10]);

        $resultado = $this->service->lotesPorVencer();

        $this->assertTrue($resultado->contains('id', $lote->id));
    }

    public function test_excluye_lote_que_vence_en_31_dias(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['fecha_vencimiento' => '2026-07-02', 'cantidad' => 10]);

        $resultado = $this->service->lotesPorVencer();

        $this->assertFalse($resultado->contains('id', $lote->id));
    }

    public function test_incluye_lote_ya_vencido(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['fecha_vencimiento' => '2026-05-20', 'cantidad' => 10]);

        $resultado = $this->service->lotesPorVencer();

        $this->assertTrue($resultado->contains('id', $lote->id));
    }

    public function test_excluye_lote_agotado_aunque_este_por_vencer(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['fecha_vencimiento' => '2026-06-10', 'cantidad' => 0]);

        $resultado = $this->service->lotesPorVencer();

        $this->assertFalse($resultado->contains('id', $lote->id));
    }

    public function test_excluye_producto_que_no_maneja_vencimiento(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['fecha_vencimiento' => '2026-06-10', 'cantidad' => 10]);

        $resultado = $this->service->lotesPorVencer();

        $this->assertFalse($resultado->contains('id', $lote->id));
    }

    public function test_permite_configurar_una_ventana_de_dias_distinta(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['fecha_vencimiento' => '2026-06-08', 'cantidad' => 10]);

        $this->assertFalse($this->service->lotesPorVencer(5)->contains('id', $lote->id));
        $this->assertTrue($this->service->lotesPorVencer(10)->contains('id', $lote->id));
    }

    public function test_incluye_producto_cuando_el_stock_actual_es_menor_al_minimo(): void
    {
        $producto = Producto::factory()->create(['stock_minimo' => 20]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 5]);

        $resultado = $this->service->productosConStockMinimo();

        $this->assertTrue($resultado->contains('id', $producto->id));
    }

    public function test_excluye_producto_cuando_el_stock_actual_es_igual_al_minimo(): void
    {
        $producto = Producto::factory()->create(['stock_minimo' => 10]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 10]);

        $resultado = $this->service->productosConStockMinimo();

        $this->assertFalse($resultado->contains('id', $producto->id));
    }

    public function test_excluye_producto_cuando_el_stock_actual_supera_el_minimo(): void
    {
        $producto = Producto::factory()->create(['stock_minimo' => 10]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 50]);

        $resultado = $this->service->productosConStockMinimo();

        $this->assertFalse($resultado->contains('id', $producto->id));
    }

    public function test_incluye_producto_sin_lotes_cuando_el_minimo_es_mayor_a_cero(): void
    {
        $producto = Producto::factory()->create(['stock_minimo' => 5]);

        $resultado = $this->service->productosConStockMinimo();

        $this->assertTrue($resultado->contains('id', $producto->id));
    }

    public function test_excluye_producto_sin_lotes_cuando_el_minimo_es_cero(): void
    {
        $producto = Producto::factory()->create(['stock_minimo' => 0]);

        $resultado = $this->service->productosConStockMinimo();

        $this->assertFalse($resultado->contains('id', $producto->id));
    }
}
