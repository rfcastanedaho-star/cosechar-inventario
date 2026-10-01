<?php

namespace Tests\Feature;

use App\Livewire\AlmacenForm;
use App\Models\Almacen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlmacenFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_registrar_un_almacen(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(AlmacenForm::class)
            ->set('nombre', 'Almacén Norte')
            ->set('direccion', 'Av. Los Fundadores 123')
            ->set('encargado', 'Juan Pérez')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('almacenes', [
            'nombre' => 'Almacén Norte',
            'direccion' => 'Av. Los Fundadores 123',
            'encargado' => 'Juan Pérez',
        ]);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $usuario = User::factory()->create();

        Livewire::actingAs($usuario)
            ->test(AlmacenForm::class)
            ->set('nombre', '')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);
    }

    public function test_no_permite_nombres_duplicados(): void
    {
        $usuario = User::factory()->create();
        Almacen::factory()->create(['nombre' => 'Almacén Norte']);

        Livewire::actingAs($usuario)
            ->test(AlmacenForm::class)
            ->set('nombre', 'Almacén Norte')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);
    }
}
