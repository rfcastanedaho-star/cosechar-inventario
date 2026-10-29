<?php

namespace Tests\Feature;

use App\Livewire\ProveedorForm;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProveedorFormTest extends TestCase
{
    use RefreshDatabase;

    private function editar(Proveedor $proveedor, ?User $usuario = null)
    {
        return Livewire::actingAs($usuario ?? User::factory()->create(['rol' => 'administrador']))
            ->test(ProveedorForm::class, ['proveedor' => $proveedor]);
    }

    public function test_el_formulario_solo_corrige_proveedores_existentes_y_muestra_sus_datos(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789', 'telefono' => '044-123456', 'direccion' => 'Av. Industrial 456']);

        $this->editar($proveedor)
            ->assertSet('nombre', 'Agro Andina SAC')
            ->assertSet('ruc', '20123456789')
            ->assertSet('telefono', '044-123456')
            ->assertSet('direccion', 'Av. Industrial 456')
            ->assertSee('Editar proveedor')
            ->assertDontSee('Registrar proveedor');
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $this->editar(Proveedor::factory()->create())
            ->set('nombre', '')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);
    }

    public function test_el_ruc_debe_tener_11_digitos_si_se_informa(): void
    {
        $this->editar(Proveedor::factory()->create())
            ->set('ruc', '123')
            ->call('guardar')
            ->assertHasErrors(['ruc']);
    }

    public function test_no_permite_ruc_duplicado(): void
    {
        Proveedor::factory()->create(['ruc' => '20123456789']);

        $this->editar(Proveedor::factory()->create(['ruc' => '20987654321']))
            ->set('ruc', '20123456789')
            ->call('guardar')
            ->assertHasErrors(['ruc' => 'unique']);
    }

    public function test_el_ruc_puede_quedar_vacio_y_guardar_con_los_mismos_datos_no_da_error(): void
    {
        $proveedor = Proveedor::factory()->create(['ruc' => '20123456789']);

        $this->editar($proveedor)->call('guardar')->assertHasNoErrors();
        $this->editar($proveedor)->set('ruc', '')->call('guardar')->assertHasNoErrors();

        $this->assertNull($proveedor->fresh()->ruc);
    }

    public function test_despues_de_guardar_vuelve_al_directorio(): void
    {
        $this->editar(Proveedor::factory()->create())
            ->set('telefono', '999')
            ->call('guardar')
            ->assertRedirect(route('proveedores.index'));
    }
}
