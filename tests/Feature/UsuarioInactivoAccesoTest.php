<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsuarioInactivoAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_inactivo_no_puede_iniciar_sesion_y_se_le_explica(): void
    {
        User::factory()->create(['email' => 'baja@cosechar.test', 'password' => 'clave-correcta-1', 'activo' => false]);

        Livewire::test(Login::class)
            ->set('email', 'baja@cosechar.test')
            ->set('password', 'clave-correcta-1')
            ->call('autenticar')
            ->assertHasErrors(['email'])
            ->assertSee('desactivado');

        $this->assertGuest();
    }

    public function test_con_clave_incorrecta_no_se_revela_que_el_usuario_esta_inactivo(): void
    {
        User::factory()->create(['email' => 'baja@cosechar.test', 'password' => 'clave-correcta-1', 'activo' => false]);

        Livewire::test(Login::class)
            ->set('email', 'baja@cosechar.test')
            ->set('password', 'clave-incorrecta')
            ->call('autenticar')
            ->assertSee('Las credenciales no coinciden')
            ->assertDontSee('desactivado');
    }

    public function test_un_usuario_activo_sigue_entrando_con_normalidad(): void
    {
        User::factory()->create(['email' => 'ok@cosechar.test', 'password' => 'clave-correcta-1']);

        Livewire::test(Login::class)
            ->set('email', 'ok@cosechar.test')
            ->set('password', 'clave-correcta-1')
            ->call('autenticar')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_si_desactivan_a_alguien_con_la_sesion_abierta_se_le_cierra_la_sesion(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('dashboard'))->assertOk();

        $usuario->update(['activo' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_un_usuario_inactivo_tampoco_puede_usar_la_api(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('integracion')->plainTextToken;
        $usuario->update(['activo' => false]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/productos')
            ->assertForbidden();
    }

    public function test_un_usuario_activo_si_usa_la_api(): void
    {
        $token = User::factory()->create()->createToken('integracion')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/productos')
            ->assertOk();
    }
}
