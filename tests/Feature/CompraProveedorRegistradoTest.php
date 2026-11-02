<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * En Compras el proveedor solo se ELIGE entre los ya registrados (lista desplegable); se registra
 * aparte, desde Proveedores en el menú.
 */
class CompraProveedorRegistradoTest extends TestCase
{
    use RefreshDatabase;

    private function formulario(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(CompraForm::class);
    }

    /** Llena la compra con una línea válida; el proveedor se elige en cada prueba. */
    private function conLinea(Testable $componente): Testable
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        return $componente
            ->set('almacen_id', Almacen::factory()->create()->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 3)
            ->set('lineas.0.costo_unitario', '4');
    }

    public function test_un_proveedor_elegido_de_la_lista_queda_en_la_compra_y_no_se_crea_otro(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789']);

        $this->conLinea($this->formulario())
            ->call('elegirProveedor', $proveedor->id)
            ->assertSet('proveedor_id', $proveedor->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1, Proveedor::count());
        $this->assertSame($proveedor->id, Compra::latest('id')->firstOrFail()->proveedor_id);
    }

    public function test_sin_proveedor_elegido_la_compra_no_se_guarda(): void
    {
        $this->conLinea($this->formulario())
            ->call('guardar')
            ->assertHasErrors(['proveedor_id' => 'required']);

        $this->assertDatabaseCount('compras', 0);
    }

    public function test_escribir_un_nombre_que_no_se_eligio_no_registra_ningun_proveedor(): void
    {
        $this->conLinea($this->formulario()->set('buscarProveedor', 'Agro Fantasma'))
            ->call('guardar')
            ->assertHasErrors(['proveedor_id' => 'required']);

        $this->assertDatabaseCount('proveedores', 0);
        $this->assertDatabaseCount('compras', 0);
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

    public function test_limpiar_el_proveedor_vuelve_al_campo_de_busqueda(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->formulario()
            ->call('elegirProveedor', $proveedor->id)
            ->call('limpiarProveedor')
            ->assertSet('proveedor_id', null)
            ->assertSet('buscarProveedor', '')
            ->assertSet('listaProveedores', false);
    }

    public function test_tras_guardar_se_limpia_el_proveedor_elegido(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->conLinea($this->formulario())
            ->call('elegirProveedor', $proveedor->id)
            ->call('guardar')
            ->assertSet('proveedor_id', null)
            ->assertSet('buscarProveedor', '');
    }

    public function test_el_proveedor_elegido_se_muestra_como_registrado_con_sus_datos(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'telefono' => '044-123456']);

        $this->formulario()
            ->call('elegirProveedor', $proveedor->id)
            ->assertSee('Registrado')
            ->assertSee('Agro Andina SAC')
            ->assertSee('044-123456');
    }

    public function test_la_pantalla_pide_elegir_el_proveedor_y_manda_a_registrarlo_en_proveedores(): void
    {
        $this->formulario()
            ->assertSee('Proveedor')
            ->assertSee('Solo se pueden elegir proveedores ya registrados')
            ->assertSeeHtml(route('proveedores.nuevo', ['desde' => 'compra']))
            ->assertDontSee('se registra solo al guardar la compra');
    }

    // ---------- Lista desplegable (flechita) ----------

    public function test_la_flechita_abre_la_lista_con_todos_los_proveedores_en_orden_alfabetico(): void
    {
        Proveedor::factory()->create(['nombre' => 'Zeta Agro']);
        Proveedor::factory()->create(['nombre' => 'Alfa Semillas']);
        Proveedor::factory()->create(['nombre' => 'Retirado', 'activo' => false]);

        $componente = $this->formulario()
            ->assertDontSee('Alfa Semillas')
            ->call('alternarListaProveedores')
            ->assertSet('listaProveedores', true)
            ->assertSee('Alfa Semillas')
            ->assertSee('Zeta Agro')
            ->assertDontSee('Retirado');

        $this->assertSame(
            ['Alfa Semillas', 'Zeta Agro'],
            $componente->instance()->proveedoresSugeridos->pluck('nombre')->all(),
        );

        $componente->call('alternarListaProveedores')->assertSet('listaProveedores', false);
    }

    public function test_al_elegir_se_cierra_la_lista(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->formulario()
            ->call('alternarListaProveedores')
            ->call('elegirProveedor', $proveedor->id)
            ->assertSet('listaProveedores', false);
    }
}
