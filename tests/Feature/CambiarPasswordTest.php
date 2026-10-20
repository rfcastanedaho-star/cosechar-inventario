<?php

namespace Tests\Feature;

use App\Livewire\CambiarPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CambiarPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_cualquier_usuario_autenticado_ve_la_pantalla(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'operador_almacen']))
            ->get(route('password.cambiar'))
            ->assertOk()
            ->assertSee('Cambiar contraseña');
    }

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('password.cambiar'))->assertRedirect(route('login'));
    }

    public function test_el_usuario_cambia_su_propia_contrasena(): void
    {
        $usuario = User::factory()->create(['password' => 'clave-actual-1']);

        Livewire::actingAs($usuario)
            ->test(CambiarPassword::class)
            ->set('password_actual', 'clave-actual-1')
            ->set('password', 'clave-nueva-22')
            ->set('password_confirmation', 'clave-nueva-22')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Contraseña actualizada')
            ->assertSet('password_actual', '')
            ->assertSet('password', '');

        $this->assertTrue(Hash::check('clave-nueva-22', $usuario->fresh()->password));
    }

    public function test_con_la_contrasena_actual_incorrecta_no_cambia_nada(): void
    {
        $usuario = User::factory()->create(['password' => 'clave-actual-1']);

        Livewire::actingAs($usuario)
            ->test(CambiarPassword::class)
            ->set('password_actual', 'equivocada')
            ->set('password', 'clave-nueva-22')
            ->set('password_confirmation', 'clave-nueva-22')
            ->call('guardar')
            ->assertHasErrors(['password_actual']);

        $this->assertTrue(Hash::check('clave-actual-1', $usuario->fresh()->password));
    }

    public function test_la_nueva_contrasena_debe_ser_larga_confirmada_y_distinta(): void
    {
        $usuario = User::factory()->create(['password' => 'clave-actual-1']);

        $componente = Livewire::actingAs($usuario)->test(CambiarPassword::class)->set('password_actual', 'clave-actual-1');

        $componente->set('password', 'corta')->set('password_confirmation', 'corta')->call('guardar')
            ->assertHasErrors(['password' => 'min']);

        $componente->set('password', 'clave-nueva-22')->set('password_confirmation', 'otra-distinta-33')->call('guardar')
            ->assertHasErrors(['password' => 'confirmed']);

        $componente->set('password', 'clave-actual-1')->set('password_confirmation', 'clave-actual-1')->call('guardar')
            ->assertHasErrors(['password' => 'different']);

        $this->assertTrue(Hash::check('clave-actual-1', $usuario->fresh()->password));
    }
}
