<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\MovimientoForm;
use App\Livewire\ReporteFiltro;
use App\Livewire\VentaForm;
use App\Models\Producto;
use App\Models\User;
use App\Repositories\ProductoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuscadorCodigoBarrasTest extends TestCase
{
    use RefreshDatabase;

    private function nombres(string $termino): array
    {
        return (new ProductoRepository)->buscar($termino)->pluck('nombre')->all();
    }

    // ---------- Repositorio ----------

    public function test_encuentra_por_codigo_de_barras_completo_o_parcial(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo_barras' => '7750123456789']);
        Producto::factory()->create(['nombre' => 'Herbicida Total', 'codigo_barras' => '7750999999999']);

        $this->assertSame(['Fungicida Cobre'], $this->nombres('7750123456789'));
        $this->assertSame(['Herbicida Total'], $this->nombres('99999'));
    }

    public function test_encuentra_por_marca(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'marca' => 'Bayer']);
        Producto::factory()->create(['nombre' => 'Herbicida Total', 'marca' => 'Basf']);

        $this->assertSame(['Fungicida Cobre'], $this->nombres('bayer'));
    }

    public function test_el_codigo_de_barras_exacto_va_primero_aunque_otro_nombre_empiece_igual(): void
    {
        Producto::factory()->create(['nombre' => '7750123 Promoción', 'codigo_barras' => null]);
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo_barras' => '7750123']);

        $this->assertSame(['Fungicida Cobre', '7750123 Promoción'], $this->nombres('7750123'));
    }

    public function test_el_orden_por_nombre_que_ya_existia_se_mantiene(): void
    {
        Producto::factory()->create(['nombre' => 'Mancozeb Fungicida']);
        Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        $this->assertSame(['Fungicida Cobre', 'Mancozeb Fungicida'], $this->nombres('fung'));
    }

    public function test_los_comodines_siguen_siendo_texto_en_marca_y_barras(): void
    {
        Producto::factory()->create(['nombre' => 'Uno', 'marca' => 'Bayer', 'codigo_barras' => '123']);

        $this->assertSame([], $this->nombres('%%'));
    }

    // ---------- Pantallas ----------

    /** @return array<string, array{class-string, string}> */
    public static function pantallas(): array
    {
        return [
            'movimientos' => [MovimientoForm::class, 'buscarProducto'],
            'reportes' => [ReporteFiltro::class, 'buscarProducto'],
        ];
    }

    private function pantalla(string $clase): Testable
    {
        $componente = Livewire::actingAs(User::factory()->create(['rol' => 'administrador']))->test($clase);

        // En Reportes el buscador de producto está en la pestaña Movimientos.
        return $clase === ReporteFiltro::class ? $componente->set('tab', 'movimientos') : $componente;
    }

    #[DataProvider('pantallas')]
    public function test_el_lector_de_barras_elige_el_producto_con_enter(string $clase, string $propiedad): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo_barras' => '7750123456789']);
        Producto::factory()->create(['nombre' => 'Otro producto', 'codigo_barras' => '7750999999999']);

        $componente = $this->pantalla($clase)->set($propiedad, '7750123456789');

        $clase === MovimientoForm::class ? $componente->call('elegirPrimero') : $componente->call('elegirProducto', $producto->id);

        $componente->assertSet('producto_id', $producto->id);
    }

    #[DataProvider('pantallas')]
    public function test_las_sugerencias_muestran_marca_y_presentacion(string $clase, string $propiedad): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'marca' => 'Bayer', 'presentacion' => 'Frasco 250 ml']);

        $this->pantalla($clase)
            ->set($propiedad, 'fung')
            ->assertSee('Fungicida Cobre')
            ->assertSee('Bayer')
            ->assertSee('Frasco 250 ml');
    }

    public function test_el_producto_elegido_en_movimientos_muestra_marca_y_presentacion(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'marca' => 'Bayer', 'presentacion' => 'Frasco 250 ml']);

        $this->pantalla(MovimientoForm::class)
            ->call('elegirProducto', $producto->id)
            ->assertSee('Bayer')
            ->assertSee('Frasco 250 ml');
    }

    /** @return array<string, array{class-string}> */
    public static function formulariosConLineas(): array
    {
        return [
            'compras' => [CompraForm::class],
            'ventas' => [VentaForm::class],
        ];
    }

    #[DataProvider('formulariosConLineas')]
    public function test_en_compras_y_ventas_se_busca_por_barras_y_se_ve_la_marca(string $clase): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'marca' => 'Bayer', 'presentacion' => '250 ml', 'codigo_barras' => '7750123456789']);

        $this->pantalla($clase)
            ->set('busquedaProducto.0', '7750123456789')
            ->assertSee('Fungicida Cobre')
            ->assertSee('Bayer')
            ->call('elegirPrimeroLinea', 0)
            ->assertSet('lineas.0.producto_id', $producto->id)
            ->assertSee('250 ml');
    }
}
