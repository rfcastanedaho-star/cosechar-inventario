<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\VentaForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuscadorProductoPorLineaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string}> */
    public static function formularios(): array
    {
        return [
            'compras' => [CompraForm::class],
            'ventas' => [VentaForm::class],
        ];
    }

    private function formulario(string $clase): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test($clase);
    }

    #[DataProvider('formularios')]
    public function test_al_escribir_sugiere_productos_activos_por_nombre_o_codigo(string $clase): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-001']);
        Producto::factory()->create(['nombre' => 'Fertilizante Urea', 'codigo' => 'P-002']);
        Producto::factory()->create(['nombre' => 'Fungicida Retirado', 'codigo' => 'P-003', 'activo' => false]);

        $this->formulario($clase)
            ->set('busquedaProducto.0', 'fu')
            ->assertSee('Fungicida Cobre')
            ->assertDontSee('Fertilizante Urea')
            ->assertDontSee('Fungicida Retirado')
            ->set('busquedaProducto.0', 'P-002')
            ->assertSee('Fertilizante Urea');
    }

    #[DataProvider('formularios')]
    public function test_sin_texto_no_hay_sugerencias_y_se_invita_a_escribir(string $clase): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        $this->formulario($clase)
            ->assertDontSee('Fungicida Cobre')
            ->assertSee('Escriba el nombre o el código');
    }

    #[DataProvider('formularios')]
    public function test_avisa_cuando_no_hay_coincidencias(string $clase): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        $this->formulario($clase)
            ->set('busquedaProducto.0', 'zzz')
            ->assertSee('No se encontró ningún producto');
    }

    #[DataProvider('formularios')]
    public function test_elegir_una_sugerencia_llena_la_linea_y_limpia_la_busqueda(string $clase): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-001']);

        $this->formulario($clase)
            ->set('busquedaProducto.0', 'fu')
            ->call('elegirProductoLinea', 0, $producto->id)
            ->assertSet('lineas.0.producto_id', $producto->id)
            ->assertSet('busquedaProducto.0', '')
            ->assertSee('P-001')
            ->assertSee('Fungicida Cobre')
            ->assertSee('Cambiar');
    }

    #[DataProvider('formularios')]
    public function test_enter_elige_la_primera_sugerencia(string $clase): void
    {
        Producto::factory()->create(['nombre' => 'Mancozeb Fungicida']);
        $primero = Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        $this->formulario($clase)
            ->set('busquedaProducto.0', 'fung')
            ->call('elegirPrimeroLinea', 0)
            ->assertSet('lineas.0.producto_id', $primero->id);
    }

    #[DataProvider('formularios')]
    public function test_cambiar_deja_la_linea_sin_producto(string $clase): void
    {
        $producto = Producto::factory()->create();

        $this->formulario($clase)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->call('limpiarProductoLinea', 0)
            ->assertSet('lineas.0.producto_id', null)
            ->assertSee('Escriba el nombre o el código');
    }

    #[DataProvider('formularios')]
    public function test_no_se_puede_elegir_un_producto_inactivo_ni_inexistente(string $clase): void
    {
        $inactivo = Producto::factory()->create(['activo' => false]);

        $this->formulario($clase)
            ->call('elegirProductoLinea', 0, $inactivo->id)
            ->assertSet('lineas.0.producto_id', null)
            ->call('elegirProductoLinea', 0, 99999)
            ->assertSet('lineas.0.producto_id', null)
            ->call('elegirProductoLinea', 5, Producto::factory()->create()->id)
            ->assertSet('lineas.0.producto_id', null);
    }

    #[DataProvider('formularios')]
    public function test_cada_linea_tiene_su_propio_buscador(string $clase): void
    {
        $uno = Producto::factory()->create(['nombre' => 'Fungicida Cobre']);
        $dos = Producto::factory()->create(['nombre' => 'Herbicida Total']);

        $this->formulario($clase)
            ->call('agregarLinea')
            ->call('elegirProductoLinea', 0, $uno->id)
            ->set('busquedaProducto.1', 'herb')
            ->assertSet('lineas.0.producto_id', $uno->id)
            ->assertSet('lineas.1.producto_id', null)
            ->call('elegirPrimeroLinea', 1)
            ->assertSet('lineas.1.producto_id', $dos->id)
            ->assertSet('lineas.0.producto_id', $uno->id);
    }

    #[DataProvider('formularios')]
    public function test_al_quitar_una_linea_las_busquedas_siguen_alineadas(string $clase): void
    {
        $this->formulario($clase)
            ->call('agregarLinea')
            ->call('agregarLinea')
            ->set('busquedaProducto.0', 'aaa')
            ->set('busquedaProducto.1', 'bbb')
            ->set('busquedaProducto.2', 'ccc')
            ->call('quitarLinea', 1)
            ->assertSet('busquedaProducto.0', 'aaa')
            ->assertSet('busquedaProducto.1', 'ccc')
            ->assertCount('lineas', 2);
    }

    public function test_en_ventas_el_precio_del_producto_se_propone_pero_no_pisa_lo_escrito(): void
    {
        $producto = Producto::factory()->create(['precio' => 45.5]);
        $otro = Producto::factory()->create(['precio' => 10]);

        $this->formulario(VentaForm::class)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->assertSet('lineas.0.precio_unitario', '45.50')
            ->set('lineas.0.precio_unitario', '40')
            ->call('elegirProductoLinea', 0, $otro->id)
            ->assertSet('lineas.0.precio_unitario', '40');
    }

    public function test_en_compras_no_se_inventa_un_costo(): void
    {
        $producto = Producto::factory()->create(['precio' => 45.5]);

        $this->formulario(CompraForm::class)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->assertSet('lineas.0.costo_unitario', '');
    }

    public function test_se_puede_registrar_una_compra_eligiendo_el_producto_con_el_buscador(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'maneja_vencimiento' => false]);

        $this->formulario(CompraForm::class)
            ->set('busquedaProducto.0', 'fung')
            ->call('elegirPrimeroLinea', 0)
            ->call('elegirProveedor', Proveedor::factory()->create()->id)
            ->set('almacen_id', Almacen::factory()->create()->id)
            ->set('lineas.0.numero_lote', 'L-BUSCADOR')
            ->set('lineas.0.cantidad', 12)
            ->set('lineas.0.costo_unitario', '8.50')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lotes', ['producto_id' => $producto->id, 'numero_lote' => 'L-BUSCADOR', 'cantidad' => 12]);
    }

    public function test_se_puede_registrar_una_venta_eligiendo_el_producto_con_el_buscador(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'precio' => 20, 'maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 30]);

        $this->formulario(VentaForm::class)
            ->set('busquedaProducto.0', 'fung')
            ->call('elegirPrimeroLinea', 0)
            ->set('cliente_nombre', 'Ana Pérez')
            ->set('almacen_id', $almacen->id)
            ->set('lineas.0.cantidad', 7)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('busquedaProducto', []);

        $this->assertSame(23, $lote->fresh()->cantidad);
    }
}
