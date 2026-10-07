<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cualquier_usuario_autenticado_puede_ver_el_historial_de_compras(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('compras.historial'))
            ->assertOk()
            ->assertSee('Historial de compras');
    }

    public function test_la_pantalla_de_compras_es_el_registro_y_enlaza_al_historial(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('compras.index'))
            ->assertOk()
            ->assertSee('Registrar compra')
            ->assertSee('Ver historial de compras')
            ->assertSee(route('compras.historial'), false);
    }

    public function test_el_historial_enlaza_de_vuelta_al_registro_de_compras(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('compras.historial'))
            ->assertSee(route('compras.index'), false);
    }
}
