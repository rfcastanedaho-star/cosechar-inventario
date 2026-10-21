<?php

namespace Tests\Feature;

use App\Livewire\ReporteFiltro;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Services\ValorizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\LeeExcel;
use Tests\TestCase;

class ReporteValorizacionTest extends TestCase
{
    use LeeExcel;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function operador(): User
    {
        return User::factory()->create(['rol' => 'operador_almacen']);
    }

    private function lote(Producto $producto, Almacen $almacen, int $cantidad, ?float $costo): Lote
    {
        return Lote::factory()->for($producto, 'producto')->create([
            'almacen_id' => $almacen->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo,
        ]);
    }

    private function pestana(?User $usuario = null): Testable
    {
        return Livewire::actingAs($usuario ?? $this->admin())->test(ReporteFiltro::class)->set('tab', 'valorizacion');
    }

    // ---------- Acceso ----------

    public function test_el_administrador_ve_la_pestana_valorizacion(): void
    {
        $this->actingAs($this->admin())->get(route('reportes'))->assertSee('Valorización');
    }

    public function test_el_operador_no_ve_la_pestana_ni_puede_abrirla_a_la_fuerza(): void
    {
        $this->actingAs($this->operador())->get(route('reportes'))->assertDontSee('Valorización');

        $this->pestana($this->operador())->assertForbidden();
    }

    public function test_el_operador_no_puede_exportar_la_valorizacion(): void
    {
        $this->actingAs($this->operador())->get(route('reportes.valorizacion.pdf'))->assertForbidden();
        $this->actingAs($this->operador())->get(route('reportes.valorizacion.excel'))->assertForbidden();
    }

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('reportes.valorizacion.pdf'))->assertRedirect(route('login'));
    }

    // ---------- Pantalla ----------

    public function test_muestra_producto_almacen_unidades_costo_y_valores(): void
    {
        $categoria = Categoria::factory()->create();
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'FUN-1', 'precio' => 10, 'categoria_id' => $categoria->id]);
        $this->lote($producto, Almacen::factory()->create(['nombre' => 'Almacén Central']), 20, 6);

        $this->pestana()
            ->assertSee('FUN-1')
            ->assertSee('Fungicida Cobre')
            ->assertSee('Almacén Central')
            ->assertSee('20')
            ->assertSee('S/ 6.00')
            ->assertSee('S/ 120.00')
            ->assertSee('S/ 200.00')
            ->assertSee('S/ 80.00');
    }

    public function test_muestra_los_totales_generales(): void
    {
        $almacen = Almacen::factory()->create();
        $this->lote(Producto::factory()->create(['precio' => 10]), $almacen, 10, 4);
        $this->lote(Producto::factory()->create(['precio' => 20]), $almacen, 5, 8);

        $this->pestana()
            ->assertSee('Valor a costo')
            ->assertSee('S/ 80.00')
            ->assertSee('Valor a precio de venta')
            ->assertSee('S/ 200.00')
            ->assertSee('15 unidades');
    }

    public function test_el_filtro_de_almacen_acota_el_reporte(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'precio' => 10]);
        $central = Almacen::factory()->create(['nombre' => 'Central']);
        $sur = Almacen::factory()->create(['nombre' => 'Sur']);
        $this->lote($producto, $central, 10, 5);
        $this->lote(Producto::factory()->create(['nombre' => 'Herbicida Total', 'precio' => 10]), $sur, 4, 5);

        $this->pestana()
            ->set('almacen_id', $sur->id)
            ->assertSee('Herbicida Total')
            ->assertDontSee('Fungicida Cobre');
    }

    public function test_avisa_cuando_hay_lotes_sin_costo(): void
    {
        $this->lote(Producto::factory()->create(['precio' => 10]), Almacen::factory()->create(), 8, null);

        $this->pestana()
            ->assertSee('8 unidades sin costo registrado')
            ->assertSee('incompleto');
    }

    public function test_no_avisa_cuando_todos_los_lotes_tienen_costo(): void
    {
        $this->lote(Producto::factory()->create(['precio' => 10]), Almacen::factory()->create(), 8, 4);

        $this->pestana()->assertDontSee('sin costo registrado');
    }

    public function test_sin_stock_muestra_el_mensaje_vacio(): void
    {
        $this->pestana()->assertSee('No hay stock para valorizar');
    }

    public function test_los_botones_de_exportar_llevan_el_filtro_de_almacen(): void
    {
        $sur = Almacen::factory()->create();

        $this->pestana()
            ->set('almacen_id', $sur->id)
            ->assertSeeHtml(route('reportes.valorizacion.pdf', ['almacen_id' => $sur->id]))
            ->assertSeeHtml(route('reportes.valorizacion.excel', ['almacen_id' => $sur->id]));
    }

    // ---------- PDF ----------

    public function test_el_pdf_se_genera_para_el_administrador(): void
    {
        $this->lote(Producto::factory()->create(['precio' => 10]), Almacen::factory()->create(), 10, 4);

        $respuesta = $this->actingAs($this->admin())->get(route('reportes.valorizacion.pdf'));

        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('Content-Type'));
    }

    public function test_el_pdf_contiene_el_titulo_las_filas_y_los_totales(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'precio' => 10]);
        $this->lote($producto, Almacen::factory()->create(['nombre' => 'Central']), 10, 4);
        $this->lote($producto, Almacen::factory()->create(['nombre' => 'Sur']), 5, null);

        $html = view('reportes.valorizacion-pdf', app(ValorizacionService::class)->calcular() + ['filtro' => 'todos los almacenes'])->render();

        $this->assertStringContainsString('Valorización del inventario', $html);
        $this->assertStringContainsString('todos los almacenes', $html);
        $this->assertStringContainsString('Fungicida Cobre', $html);
        $this->assertStringContainsString('Central', $html);
        $this->assertStringContainsString('S/ 40.00', $html);
        $this->assertStringContainsString('TOTAL', $html);
        $this->assertStringContainsString('sin costo', $html);
    }

    // ---------- Excel ----------

    public function test_el_excel_lleva_encabezados_filas_y_total(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'FUN-1', 'precio' => 10]);
        $this->lote($producto, Almacen::factory()->create(['nombre' => 'Central']), 10, 4);

        $filas = $this->filasDeExcel($this->actingAs($this->admin())->get(route('reportes.valorizacion.excel'))->streamedContent());

        $this->assertSame(
            ['Código', 'Producto', 'Categoría', 'Almacén', 'Unidades', 'Costo promedio (S/)', 'Valor a costo (S/)', 'Valor a precio de venta (S/)', 'Utilidad potencial (S/)', 'Unidades sin costo'],
            $filas[0],
        );
        $this->assertSame('FUN-1', $filas[1][0]);
        $this->assertSame('Fungicida Cobre', $filas[1][1]);
        $this->assertSame('Central', $filas[1][3]);
        $this->assertEquals(10, $filas[1][4]);
        $this->assertEquals(4, $filas[1][5]);
        $this->assertEquals(40, $filas[1][6]);
        $this->assertEquals(100, $filas[1][7]);
        $this->assertEquals(60, $filas[1][8]);
        $this->assertEquals(0, $filas[1][9]);

        $this->assertSame('TOTAL', $filas[2][0]);
        $this->assertEquals(10, $filas[2][4]);
        $this->assertEquals(40, $filas[2][6]);
    }

    public function test_el_excel_respeta_el_filtro_de_almacen_y_deja_vacio_el_costo_si_no_hay(): void
    {
        $producto = Producto::factory()->create(['precio' => 10]);
        $sur = Almacen::factory()->create(['nombre' => 'Sur']);
        $this->lote($producto, Almacen::factory()->create(['nombre' => 'Central']), 10, 4);
        $this->lote($producto, $sur, 6, null);

        $filas = $this->filasDeExcel(
            $this->actingAs($this->admin())->get(route('reportes.valorizacion.excel', ['almacen_id' => $sur->id]))->streamedContent()
        );

        $this->assertCount(3, $filas); // encabezado + 1 fila + total
        $this->assertSame('Sur', $filas[1][3]);
        $this->assertTrue($filas[1][5] === null || $filas[1][5] === '');
        $this->assertEquals(6, $filas[1][9]);
    }
}
