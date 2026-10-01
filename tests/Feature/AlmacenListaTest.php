<?php

namespace Tests\Feature;

use App\Livewire\AlmacenLista;
use App\Models\Almacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlmacenListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_los_almacenes_registrados(): void
    {
        $usuario = User::factory()->create();

        Almacen::factory()->create(['nombre' => 'Almacén Norte']);
        Almacen::factory()->create(['nombre' => 'Almacén Sur']);

        $almacenes = Livewire::actingAs($usuario)
            ->test(AlmacenLista::class)
            ->instance()
            ->almacenes;

        // +1 por el "Almacén Principal" creado en la migración de almacen_id en lotes.
        $this->assertCount(3, $almacenes);
    }

    public function test_cualquier_usuario_autenticado_puede_ver_la_lista(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('almacenes.index'))->assertOk();
    }
}
