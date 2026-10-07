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

    public function test_cualquier_usuario_autenticado_puede_ver_el_historial_de_almacenes(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('almacenes.historial'))
            ->assertOk()
            ->assertSee('Historial de almacenes');
    }

    public function test_la_pantalla_de_almacenes_es_el_registro_y_enlaza_al_historial(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('almacenes.index'))
            ->assertOk()
            ->assertSee('Registrar almacén')
            ->assertSee('Ver historial de almacenes')
            ->assertSee(route('almacenes.historial'), false);
    }

    public function test_el_historial_enlaza_de_vuelta_al_registro_de_almacenes(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('almacenes.historial'))
            ->assertSee(route('almacenes.index'), false);
    }
}
