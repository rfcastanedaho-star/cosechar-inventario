<?php

namespace Tests\Feature;

use App\Livewire\ProveedorLista;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class ProveedorListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_los_proveedores_registrados(): void
    {
        $usuario = User::factory()->create();

        Proveedor::factory()->count(2)->create();

        $proveedores = Livewire::actingAs($usuario)
            ->test(ProveedorLista::class)
            ->instance()
            ->proveedores;

        $this->assertCount(2, $proveedores);
    }

    public function test_la_pantalla_de_proveedores_es_el_directorio_para_consultar_y_corregir(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('proveedores.index'))
            ->assertOk()
            ->assertSee('Proveedores')
            ->assertSee('se registran solos al registrar una compra')
            ->assertSee(route('proveedores.editar', $proveedor), false)
            ->assertDontSee('Registrar proveedor');
    }

    public function test_ya_no_existe_una_pantalla_para_registrar_proveedores_a_mano(): void
    {
        $this->assertFalse(Route::has('proveedores.nuevo'));

        $this->actingAs(User::factory()->create())->get('/proveedores/nuevo')->assertNotFound();
    }

    public function test_la_direccion_antigua_del_historial_lleva_al_directorio(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/proveedores/historial')
            ->assertRedirect(route('proveedores.index'));
    }
}
