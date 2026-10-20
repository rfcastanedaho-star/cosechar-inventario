<?php

namespace Tests\Feature;

use App\Livewire\UsuarioForm;
use App\Livewire\UsuarioLista;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UsuarioGestionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function operador(array $atributos = []): User
    {
        return User::factory()->create($atributos + ['rol' => 'operador_almacen']);
    }

    public function test_solo_el_administrador_entra_a_la_gestion_de_usuarios(): void
    {
        $this->actingAs($this->operador())->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($this->operador())->get(route('usuarios.nuevo'))->assertForbidden();
        $this->actingAs($this->operador())->get(route('usuarios.editar', $this->operador()))->assertForbidden();

        $this->actingAs($this->admin())->get(route('usuarios.index'))->assertOk()->assertSee('Usuarios');
    }

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_muestra_usuarios_solo_al_administrador(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))->assertSee(route('usuarios.index'), false);
        $this->actingAs($this->operador())->get(route('dashboard'))->assertDontSee(route('usuarios.index'), false);
    }

    public function test_la_lista_muestra_nombre_correo_rol_y_estado(): void
    {
        $this->operador(['name' => 'Luis Operador', 'email' => 'luis@cosechar.test']);
        $this->operador(['name' => 'Ana Retirada', 'email' => 'ana@cosechar.test', 'activo' => false]);

        Livewire::actingAs($this->admin())
            ->test(UsuarioLista::class)
            ->assertSee('Luis Operador')
            ->assertSee('luis@cosechar.test')
            ->assertSee('Operador de almacén')
            ->assertSee('Administrador')
            ->assertSee('Ana Retirada')
            ->assertSee('Inactivo');
    }

    public function test_la_lista_nunca_muestra_contrasenas(): void
    {
        $usuario = $this->operador();

        Livewire::actingAs($this->admin())
            ->test(UsuarioLista::class)
            ->assertDontSee($usuario->getRawOriginal('password'));
    }

    public function test_el_administrador_crea_un_usuario(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UsuarioForm::class)
            ->set('name', 'Rosa Nueva')
            ->set('email', 'rosa@cosechar.test')
            ->set('rol', 'operador_almacen')
            ->set('password', 'clave-segura-1')
            ->set('password_confirmation', 'clave-segura-1')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios.index'));

        $nuevo = User::where('email', 'rosa@cosechar.test')->firstOrFail();
        $this->assertSame('operador_almacen', $nuevo->rol);
        $this->assertTrue($nuevo->activo);
        $this->assertTrue(Hash::check('clave-segura-1', $nuevo->password));
    }

    public function test_crear_valida_los_datos(): void
    {
        $this->operador(['email' => 'repetido@cosechar.test']);

        Livewire::actingAs($this->admin())
            ->test(UsuarioForm::class)
            ->set('name', '')
            ->set('email', 'repetido@cosechar.test')
            ->set('rol', 'superusuario')
            ->set('password', 'corta')
            ->set('password_confirmation', 'distinta')
            ->call('guardar')
            ->assertHasErrors(['name' => 'required', 'email' => 'unique', 'rol' => 'in', 'password' => 'min']);
    }

    public function test_la_confirmacion_de_la_contrasena_debe_coincidir(): void
    {
        Livewire::actingAs($this->admin())
            ->test(UsuarioForm::class)
            ->set('name', 'Rosa')
            ->set('email', 'rosa@cosechar.test')
            ->set('rol', 'operador_almacen')
            ->set('password', 'clave-segura-1')
            ->set('password_confirmation', 'clave-segura-2')
            ->call('guardar')
            ->assertHasErrors(['password' => 'confirmed']);

        $this->assertDatabaseMissing('users', ['email' => 'rosa@cosechar.test']);
    }

    public function test_el_formulario_de_edicion_muestra_los_datos_y_edita_sin_pedir_contrasena(): void
    {
        $usuario = $this->operador(['name' => 'Luis', 'email' => 'luis@cosechar.test']);
        $claveOriginal = $usuario->password;

        Livewire::actingAs($this->admin())
            ->test(UsuarioForm::class, ['usuario' => $usuario])
            ->assertSet('name', 'Luis')
            ->assertSet('email', 'luis@cosechar.test')
            ->assertSet('rol', 'operador_almacen')
            ->set('name', 'Luis Pérez')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Luis Pérez', $usuario->fresh()->name);
        $this->assertSame($claveOriginal, $usuario->fresh()->password);
    }

    public function test_editar_permite_conservar_el_propio_correo(): void
    {
        $usuario = $this->operador(['email' => 'luis@cosechar.test']);

        Livewire::actingAs($this->admin())
            ->test(UsuarioForm::class, ['usuario' => $usuario])
            ->call('guardar')
            ->assertHasNoErrors();
    }

    public function test_el_administrador_restablece_la_contrasena_de_otro_usuario(): void
    {
        $usuario = $this->operador();

        Livewire::actingAs($this->admin())
            ->test(UsuarioForm::class, ['usuario' => $usuario])
            ->set('nuevaPassword', 'clave-restablecida-9')
            ->set('nuevaPassword_confirmation', 'clave-restablecida-9')
            ->call('restablecerPassword')
            ->assertHasNoErrors()
            ->assertSet('nuevaPassword', '')
            ->assertSee('Contraseña restablecida');

        $this->assertTrue(Hash::check('clave-restablecida-9', $usuario->fresh()->password));
    }

    public function test_restablecer_exige_una_contrasena_valida_y_confirmada(): void
    {
        $usuario = $this->operador();
        $anterior = $usuario->password;

        Livewire::actingAs($this->admin())
            ->test(UsuarioForm::class, ['usuario' => $usuario])
            ->set('nuevaPassword', 'corta')
            ->set('nuevaPassword_confirmation', 'corta')
            ->call('restablecerPassword')
            ->assertHasErrors(['nuevaPassword' => 'min']);

        $this->assertSame($anterior, $usuario->fresh()->password);
    }

    public function test_el_administrador_desactiva_y_reactiva_desde_la_lista(): void
    {
        $usuario = $this->operador();

        $lista = Livewire::actingAs($this->admin())->test(UsuarioLista::class);

        $lista->call('cambiarEstado', $usuario->id)->assertSet('error', null);
        $this->assertFalse($usuario->fresh()->activo);

        $lista->call('cambiarEstado', $usuario->id);
        $this->assertTrue($usuario->fresh()->activo);
    }

    public function test_la_lista_explica_por_que_no_se_puede_desactivar_al_propio_usuario(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UsuarioLista::class)
            ->call('cambiarEstado', $admin->id)
            ->assertSet('error', 'No puedes desactivar tu propio usuario.');

        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_un_operador_no_puede_ejecutar_acciones_de_usuarios(): void
    {
        $operador = $this->operador();
        $otro = $this->operador();

        Livewire::actingAs($operador)->test(UsuarioLista::class)->assertForbidden();
        Livewire::actingAs($operador)->test(UsuarioForm::class)->assertForbidden();

        $this->assertTrue($otro->fresh()->activo);
    }

    public function test_el_formulario_de_edicion_explica_el_error_al_quitarse_el_rol_a_si_mismo(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UsuarioForm::class, ['usuario' => $admin])
            ->set('rol', 'operador_almacen')
            ->call('guardar')
            ->assertHasErrors(['rol']);

        $this->assertSame('administrador', $admin->fresh()->rol);
    }
}
