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

    public function test_cualquier_usuario_autenticado_puede_ver_la_lista(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('proveedores.index'))->assertOk();
    }
}
