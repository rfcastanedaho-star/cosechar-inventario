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

    public function test_se_puede_registrar_un_proveedor(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(ProveedorForm::class)
            ->set('nombre', 'Agroquímicos del Norte S.A.C.')
            ->set('ruc', '20123456789')
            ->set('telefono', '044-123456')
            ->set('direccion', 'Av. Industrial 456')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('proveedores', [
            'nombre' => 'Agroquímicos del Norte S.A.C.',
            'ruc' => '20123456789',
        ]);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(ProveedorForm::class)
            ->set('nombre', '')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);
    }

    public function test_el_ruc_debe_tener_11_digitos_si_se_informa(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(ProveedorForm::class)
            ->set('nombre', 'Proveedor X')
            ->set('ruc', '123')
            ->call('guardar')
            ->assertHasErrors(['ruc']);
    }

    public function test_no_permite_ruc_duplicado(): void
    {
        $usuario = User::factory()->create();
        Proveedor::factory()->create(['ruc' => '20123456789']);

        Livewire::actingAs($usuario)
            ->test(ProveedorForm::class)
            ->set('nombre', 'Otro proveedor')
            ->set('ruc', '20123456789')
            ->call('guardar')
            ->assertHasErrors(['ruc' => 'unique']);
    }
}
