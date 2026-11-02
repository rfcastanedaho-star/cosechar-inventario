<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\ProveedorForm;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProveedorRegistroTest extends TestCase
{
    use RefreshDatabase;

    private function formulario(): Testable
    {
        return Livewire::actingAs(User::factory()->create(['rol' => 'operador_almacen']))->test(ProveedorForm::class);
    }

    public function test_se_registra_un_proveedor_nuevo_desde_su_formulario(): void
    {
        $this->formulario()
            ->set('nombre', '  Agro Andina SAC ')
            ->set('ruc', '20123456789')
            ->set('telefono', '044-123456')
            ->set('correo', 'ventas@agroandina.pe')
            ->set('direccion', 'Av. Larco 123')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('proveedores.index'));

        $this->assertDatabaseHas('proveedores', [
            'nombre' => 'Agro Andina SAC', 'ruc' => '20123456789', 'telefono' => '044-123456',
            'correo' => 'ventas@agroandina.pe', 'direccion' => 'Av. Larco 123', 'activo' => true,
        ]);
    }

    public function test_solo_el_nombre_es_obligatorio(): void
    {
        $this->formulario()->call('guardar')->assertHasErrors(['nombre' => 'required']);

        $this->formulario()->set('nombre', 'Agro Sur')->call('guardar')->assertHasNoErrors();
        $this->assertNull(Proveedor::where('nombre', 'Agro Sur')->firstOrFail()->ruc);
    }

    public function test_el_nombre_y_el_ruc_no_se_repiten_y_se_validan_los_formatos(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789']);

        $this->formulario()->set('nombre', 'Agro Andina SAC')->call('guardar')->assertHasErrors(['nombre' => 'unique']);
        $this->formulario()->set('nombre', 'Otro')->set('ruc', '20123456789')->call('guardar')->assertHasErrors(['ruc' => 'unique']);
        $this->formulario()->set('nombre', 'Otro')->set('ruc', '123')->call('guardar')->assertHasErrors(['ruc' => 'digits']);
        $this->formulario()->set('nombre', 'Otro')->set('correo', 'malo')->call('guardar')->assertHasErrors(['correo' => 'email']);

        $this->assertSame(1, Proveedor::count());
    }

    public function test_al_registrar_desde_compras_se_vuelve_al_formulario_de_compra(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['desde' => 'compra'])
            ->test(ProveedorForm::class)
            ->set('nombre', 'Agro Sur')
            ->call('guardar')
            ->assertRedirect(route('compras.index'));
    }

    public function test_el_proveedor_registrado_aparece_enseguida_en_la_lista_desplegable_de_compras(): void
    {
        $this->formulario()->set('nombre', 'Rural Semillas')->call('guardar');

        Livewire::actingAs(User::factory()->create())
            ->test(CompraForm::class)
            ->set('buscarProveedor', 'ru')
            ->assertSee('Rural Semillas');
    }
}
