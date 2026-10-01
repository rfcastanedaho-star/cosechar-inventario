<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cualquier_usuario_autenticado_puede_ver_la_lista_de_ventas(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('ventas.index'))->assertOk();
    }
}
