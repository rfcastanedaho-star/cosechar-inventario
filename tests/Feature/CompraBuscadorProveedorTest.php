<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CompraBuscadorProveedorTest extends TestCase
{
    use RefreshDatabase;

    private function formulario(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(CompraForm::class);
    }

    public function test_al_escribir_las_iniciales_sugiere_los_proveedores_activos(): void
    {
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);
        Proveedor::factory()->create(['nombre' => 'Agroinsumos del Norte']);
        Proveedor::factory()->create(['nombre' => 'Rural Retirado', 'activo' => false]);

        $this->formulario()
            ->set('buscarProveedor', 'ru')
            ->assertSee('Rural Semillas')
            ->assertDontSee('Agroinsumos del Norte')
            ->assertDontSee('Rural Retirado');
    }

    public function test_la_sugerencia_muestra_el_ruc_si_lo_tiene(): void
    {
        Proveedor::factory()->create(['nombre' => 'Rural Semillas', 'ruc' => '20123456789']);

        $this->formulario()
            ->set('buscarProveedor', 'rural')
            ->assertSee('20123456789');
    }

    public function test_sin_texto_no_hay_sugerencias_y_se_invita_a_escribir(): void
    {
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);

        $this->formulario()
            ->assertDontSee('Rural Semillas')
            ->assertSee('Escriba el nombre o el RUC del proveedor');
    }

    public function test_avisa_cuando_no_hay_coincidencias(): void
    {
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);

        $this->formulario()
            ->set('buscarProveedor', 'zzz')
            ->assertSee('No se encontró ningún proveedor');
    }

    public function test_muestra_como_maximo_ocho_sugerencias(): void
    {
        foreach (range(1, 12) as $n) {
            Proveedor::factory()->create(['nombre' => 'Rural '.$n]);
        }

        $componente = $this->formulario()->set('buscarProveedor', 'rural');

        $this->assertCount(8, $componente->instance()->proveedoresSugeridos);
    }

    public function test_elegir_una_sugerencia_selecciona_el_proveedor_y_limpia_la_busqueda(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Rural Semillas']);

        $this->formulario()
            ->set('buscarProveedor', 'ru')
            ->call('elegirProveedor', $proveedor->id)
            ->assertSet('proveedor_id', $proveedor->id)
            ->assertSet('buscarProveedor', '')
            ->assertSee('Rural Semillas')
            ->assertSee('Cambiar');
    }

    public function test_enter_elige_la_primera_sugerencia(): void
    {
        Proveedor::factory()->create(['nombre' => 'Distribuidora Rural']);
        $primero = Proveedor::factory()->create(['nombre' => 'Rural Semillas']);

        $this->formulario()
            ->set('buscarProveedor', 'rural')
            ->call('elegirPrimerProveedor')
            ->assertSet('proveedor_id', $primero->id);
    }

    public function test_enter_sin_coincidencias_no_elige_nada(): void
    {
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);

        $this->formulario()
            ->set('buscarProveedor', 'zzz')
            ->call('elegirPrimerProveedor')
            ->assertSet('proveedor_id', null);
    }

    public function test_cambiar_deja_el_proveedor_sin_elegir(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->formulario()
            ->call('elegirProveedor', $proveedor->id)
            ->call('limpiarProveedor')
            ->assertSet('proveedor_id', null)
            ->assertSee('Escriba el nombre o el RUC del proveedor');
    }

    public function test_no_se_puede_elegir_un_proveedor_desactivado_ni_inexistente(): void
    {
        $inactivo = Proveedor::factory()->create(['activo' => false]);

        $this->formulario()
            ->call('elegirProveedor', $inactivo->id)
            ->assertSet('proveedor_id', null)
            ->call('elegirProveedor', 99999)
            ->assertSet('proveedor_id', null);
    }

    public function test_la_busqueda_de_proveedor_no_interfiere_con_la_de_producto(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre']);
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);

        $this->formulario()
            ->set('buscarProveedor', 'rural')
            ->set('busquedaProducto.0', 'fung')
            ->assertSee('Rural Semillas')
            ->assertSee('Fungicida Cobre');

        $this->assertSame($producto->nombre, 'Fungicida Cobre');
    }

    public function test_se_puede_registrar_una_compra_eligiendo_el_proveedor_con_el_buscador(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Rural Semillas']);
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        $this->formulario()
            ->set('buscarProveedor', 'rural')
            ->call('elegirPrimerProveedor')
            ->set('almacen_id', Almacen::factory()->create()->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.numero_lote', 'L-PROVEEDOR')
            ->set('lineas.0.cantidad', 8)
            ->set('lineas.0.costo_unitario', '4.50')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('proveedor_id', null)
            ->assertSet('buscarProveedor', '');

        $this->assertDatabaseHas('compras', ['proveedor_id' => $proveedor->id]);
    }

    public function test_sin_proveedor_la_compra_no_se_guarda_y_pide_elegirlo(): void
    {
        $this->formulario()
            ->set('almacen_id', Almacen::factory()->create()->id)
            ->call('guardar')
            ->assertHasErrors(['proveedor_id' => 'required']);
    }
}
