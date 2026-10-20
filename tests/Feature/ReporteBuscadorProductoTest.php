<?php

namespace Tests\Feature;

use App\Livewire\ReporteFiltro;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ReporteBuscadorProductoTest extends TestCase
{
    use RefreshDatabase;

    private function formulario(): Testable
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(ReporteFiltro::class)
            ->set('tab', 'movimientos');
    }

    public function test_el_filtro_de_producto_se_busca_escribiendo(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-001']);
        Producto::factory()->create(['nombre' => 'Fertilizante Urea', 'codigo' => 'P-002']);

        $this->formulario()
            ->set('buscarProducto', 'fu')
            ->assertSee('Fungicida Cobre')
            ->assertDontSee('Fertilizante Urea');
    }

    public function test_los_reportes_incluyen_productos_inactivos_porque_tienen_historial(): void
    {
        Producto::factory()->create(['nombre' => 'Producto Retirado', 'activo' => false]);

        $this->formulario()
            ->set('buscarProducto', 'retirado')
            ->assertSee('Producto Retirado');
    }

    public function test_sin_coincidencias_avisa(): void
    {
        $this->formulario()
            ->set('buscarProducto', 'zzz')
            ->assertSee('No se encontró ningún producto');
    }

    public function test_elegir_un_producto_filtra_los_movimientos_y_se_puede_cambiar(): void
    {
        $usuario = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $uno = Producto::factory()->create(['nombre' => 'Fungicida Cobre']);
        $dos = Producto::factory()->create(['nombre' => 'Herbicida Total']);
        foreach ([$uno, $dos] as $producto) {
            $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id]);
            StockMovimiento::create(['lote_id' => $lote->id, 'tipo' => 'entrada', 'cantidad' => 5, 'fecha' => now(), 'responsable_id' => $usuario->id]);
        }

        $componente = $this->formulario()
            ->assertSee('2 movimientos')
            ->call('elegirProducto', $uno->id)
            ->assertSet('producto_id', $uno->id)
            ->assertSet('buscarProducto', '')
            ->assertSee('1 movimientos')
            ->assertSee('Cambiar');

        $componente->call('limpiarProducto')
            ->assertSet('producto_id', null)
            ->assertSee('2 movimientos');
    }

    public function test_limpiar_filtros_tambien_quita_el_producto_y_la_busqueda(): void
    {
        $producto = Producto::factory()->create();

        $this->formulario()
            ->call('elegirProducto', $producto->id)
            ->set('buscarProducto', 'x')
            ->call('limpiarFiltros')
            ->assertSet('producto_id', null)
            ->assertSet('buscarProducto', '');
    }

    public function test_no_se_puede_elegir_un_producto_inexistente(): void
    {
        $this->formulario()
            ->call('elegirProducto', 99999)
            ->assertSet('producto_id', null);
    }
}
