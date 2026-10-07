<?php

namespace Tests\Feature;

use App\Livewire\ProveedorLista;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_cualquier_usuario_autenticado_puede_ver_el_historial_de_proveedores(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('proveedores.historial'))
            ->assertOk()
            ->assertSee('Historial de proveedores');
    }

    public function test_la_pantalla_de_proveedores_es_el_registro_y_enlaza_al_historial(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('proveedores.index'))
            ->assertOk()
            ->assertSee('Registrar proveedor')
            ->assertSee('Ver historial de proveedores')
            ->assertSee(route('proveedores.historial'), false);
    }

    public function test_el_historial_enlaza_de_vuelta_al_registro_de_proveedores(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('proveedores.historial'))
            ->assertSee(route('proveedores.index'), false);
    }
}
