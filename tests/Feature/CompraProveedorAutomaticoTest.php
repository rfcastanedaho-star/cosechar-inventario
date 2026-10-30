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

class CompraProveedorAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    private function formulario(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(CompraForm::class);
    }

    /** Llena la compra con una línea válida; el proveedor se completa en cada prueba. */
    private function conLinea(Testable $componente): Testable
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        return $componente
            ->set('almacen_id', Almacen::factory()->create()->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 3)
            ->set('lineas.0.costo_unitario', '4');
    }

    // ---------- Registro automático ----------

    public function test_un_proveedor_nuevo_con_solo_el_nombre_se_registra_al_guardar_la_compra(): void
    {
        $this->conLinea($this->formulario()->set('proveedor_nombre', 'Agro Andina SAC'))
            ->call('guardar')
            ->assertHasNoErrors();

        $proveedor = Proveedor::where('nombre', 'Agro Andina SAC')->firstOrFail();
        $this->assertNull($proveedor->ruc);
        $this->assertNull($proveedor->telefono);
        $this->assertTrue($proveedor->activo);
        $this->assertSame($proveedor->id, Compra::latest('id')->firstOrFail()->proveedor_id);
    }

    public function test_se_registra_tambien_con_ruc_y_telefono(): void
    {
        $this->conLinea($this->formulario()
            ->set('proveedor_nombre', 'Agro Andina SAC')
            ->set('proveedor_ruc', '20123456789')
            ->set('proveedor_telefono', '044-123456'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('proveedores', ['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789', 'telefono' => '044-123456']);
    }

    public function test_si_el_ruc_ya_existe_se_reutiliza_el_proveedor_sin_duplicarlo(): void
    {
        $existente = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789']);

        $this->conLinea($this->formulario()->set('proveedor_nombre', 'Agro Andina')->set('proveedor_ruc', '20123456789'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1, Proveedor::count());
        $this->assertSame($existente->id, Compra::latest('id')->firstOrFail()->proveedor_id);
        $this->assertSame('Agro Andina SAC', $existente->fresh()->nombre);
    }

    public function test_sin_ruc_se_reutiliza_el_proveedor_del_mismo_nombre(): void
    {
        $existente = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => null]);

        $this->conLinea($this->formulario()->set('proveedor_nombre', 'agro andina sac'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1, Proveedor::count());
        $this->assertSame($existente->id, Compra::latest('id')->firstOrFail()->proveedor_id);
    }

    public function test_un_proveedor_elegido_de_la_lista_no_crea_otro(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789', 'telefono' => '044-123456']);

        $this->conLinea($this->formulario())
            ->call('elegirProveedor', $proveedor->id)
            ->assertSet('proveedor_nombre', 'Agro Andina SAC')
            ->assertSet('proveedor_ruc', '20123456789')
            ->assertSet('proveedor_telefono', '044-123456')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1, Proveedor::count());
        $this->assertSame($proveedor->id, Compra::latest('id')->firstOrFail()->proveedor_id);
    }

    public function test_si_se_edita_a_mano_el_nombre_o_el_ruc_deja_de_ser_el_proveedor_registrado(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789']);

        $this->formulario()
            ->call('elegirProveedor', $proveedor->id)
            ->set('proveedor_nombre', 'Otro proveedor')
            ->assertSet('proveedor_id', null);

        $this->formulario()
            ->call('elegirProveedor', $proveedor->id)
            ->set('proveedor_ruc', '20999999999')
            ->assertSet('proveedor_id', null);
    }

    public function test_cambiar_el_telefono_de_un_proveedor_elegido_no_lo_desvincula(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->formulario()
            ->call('elegirProveedor', $proveedor->id)
            ->set('proveedor_telefono', '999')
            ->assertSet('proveedor_id', $proveedor->id);
    }

    public function test_limpiar_el_proveedor_vacia_todos_los_datos(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->formulario()
            ->call('elegirProveedor', $proveedor->id)
            ->call('limpiarProveedor')
            ->assertSet('proveedor_id', null)
            ->assertSet('proveedor_nombre', '')
            ->assertSet('proveedor_ruc', '')
            ->assertSet('proveedor_telefono', '');
    }

    public function test_tras_guardar_se_limpian_los_datos_del_proveedor(): void
    {
        $this->conLinea($this->formulario()->set('proveedor_nombre', 'Agro Andina SAC')->set('proveedor_ruc', '20123456789'))
            ->call('guardar')
            ->assertSet('proveedor_id', null)
            ->assertSet('proveedor_nombre', '')
            ->assertSet('proveedor_ruc', '')
            ->assertSet('proveedor_telefono', '')
            ->assertSet('buscarProveedor', '');
    }

    // ---------- Validación ----------

    public function test_sin_proveedor_elegido_el_nombre_es_obligatorio(): void
    {
        $this->conLinea($this->formulario())
            ->call('guardar')
            ->assertHasErrors(['proveedor_nombre' => 'required']);

        $this->assertDatabaseCount('proveedores', 0);
        $this->assertDatabaseCount('compras', 0);
    }

    public function test_valida_largo_del_nombre_el_ruc_y_el_telefono(): void
    {
        $this->conLinea($this->formulario()->set('proveedor_nombre', str_repeat('a', 151)))->call('guardar')->assertHasErrors(['proveedor_nombre' => 'max']);
        $this->conLinea($this->formulario()->set('proveedor_nombre', 'X')->set('proveedor_ruc', '123'))->call('guardar')->assertHasErrors(['proveedor_ruc']);
        $this->conLinea($this->formulario()->set('proveedor_nombre', 'X')->set('proveedor_ruc', '2012345678a'))->call('guardar')->assertHasErrors(['proveedor_ruc']);
        $this->conLinea($this->formulario()->set('proveedor_nombre', 'X')->set('proveedor_telefono', str_repeat('9', 31)))->call('guardar')->assertHasErrors(['proveedor_telefono' => 'max']);
    }

    public function test_el_nombre_se_recorta_de_espacios(): void
    {
        $this->conLinea($this->formulario()->set('proveedor_nombre', '   Agro Andina SAC  '))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('proveedores', ['nombre' => 'Agro Andina SAC']);
    }

    public function test_si_la_compra_falla_no_queda_registrado_el_proveedor(): void
    {
        // Sin líneas válidas la compra no se guarda y el proveedor tampoco.
        $this->formulario()
            ->set('proveedor_nombre', 'Agro Fantasma')
            ->set('almacen_id', Almacen::factory()->create()->id)
            ->call('guardar')
            ->assertHasErrors();

        $this->assertDatabaseMissing('proveedores', ['nombre' => 'Agro Fantasma']);
    }

    // ---------- Pantalla ----------

    public function test_la_pantalla_pide_el_nombre_y_ofrece_ruc_y_telefono_opcionales(): void
    {
        $this->formulario()
            ->assertSee('Nombre del proveedor')
            ->assertSee('RUC')
            ->assertSee('Teléfono')
            ->assertSee('se registra solo al guardar la compra')
            ->assertDontSee('Registrar proveedor');
    }

    public function test_el_proveedor_elegido_se_muestra_como_registrado(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC']);

        $this->formulario()
            ->call('elegirProveedor', $proveedor->id)
            ->assertSee('Proveedor registrado')
            ->assertSee('Agro Andina SAC');
    }
}
