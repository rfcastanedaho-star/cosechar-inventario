<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cualquier_usuario_autenticado_puede_ver_el_historial_de_ventas(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('ventas.historial'))
            ->assertOk()
            ->assertSee('Historial de ventas');
    }

    public function test_la_pantalla_de_ventas_es_el_registro_y_enlaza_al_historial(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('Registrar venta')
            ->assertSee('Ver historial de ventas')
            ->assertSee(route('ventas.historial'), false);
    }

    public function test_el_historial_enlaza_de_vuelta_al_registro_de_ventas(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('ventas.historial'))
            ->assertSee(route('ventas.index'), false);
    }
}
