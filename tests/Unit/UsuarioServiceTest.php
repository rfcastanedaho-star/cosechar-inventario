<?php

namespace Tests\Unit;

use App\Exceptions\UsuarioNoPermitidoException;
use App\Models\AuditoriaCambio;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsuarioService $servicio;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(UsuarioService::class);
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    private function operador(array $atributos = []): User
    {
        return User::factory()->create($atributos + ['rol' => 'operador_almacen']);
    }

    public function test_crear_guarda_el_usuario_activo_con_la_contrasena_cifrada(): void
    {
        $usuario = $this->servicio->crear([
            'name' => 'Luis Operador', 'email' => 'luis@cosechar.test', 'rol' => 'operador_almacen', 'password' => 'clave-segura-1',
        ]);

        $this->assertTrue($usuario->fresh()->activo);
        $this->assertSame('operador_almacen', $usuario->rol);
        $this->assertNotSame('clave-segura-1', $usuario->fresh()->getRawOriginal('password'));
        $this->assertTrue(Hash::check('clave-segura-1', $usuario->fresh()->password));
    }

    public function test_actualizar_cambia_nombre_correo_y_rol_y_deja_historial(): void
    {
        $usuario = $this->operador(['name' => 'Luis', 'email' => 'luis@cosechar.test']);

        $this->servicio->actualizar($usuario, ['name' => 'Luis Pérez', 'email' => 'lperez@cosechar.test', 'rol' => 'administrador'], $this->admin);

        $usuario->refresh();
        $this->assertSame('Luis Pérez', $usuario->name);
        $this->assertSame('lperez@cosechar.test', $usuario->email);
        $this->assertSame('administrador', $usuario->rol);
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => User::class,
            'auditable_id' => $usuario->id,
            'user_id' => $this->admin->id,
            'campo' => 'rol',
            'valor_anterior' => 'operador_almacen',
            'valor_nuevo' => 'administrador',
        ]);
    }

    public function test_un_administrador_no_puede_quitarse_a_si_mismo_el_rol(): void
    {
        $otroAdmin = User::factory()->create(['rol' => 'administrador']);

        $this->expectException(UsuarioNoPermitidoException::class);

        try {
            $this->servicio->actualizar($this->admin, ['name' => $this->admin->name, 'email' => $this->admin->email, 'rol' => 'operador_almacen'], $this->admin);
        } finally {
            $this->assertSame('administrador', $this->admin->fresh()->rol);
            $this->assertNotNull($otroAdmin);
        }
    }

    public function test_no_se_puede_degradar_al_ultimo_administrador_activo(): void
    {
        $soloAdmin = $this->admin;
        $otro = $this->operador();
        $otro->update(['rol' => 'administrador', 'activo' => false]);

        $this->expectException(UsuarioNoPermitidoException::class);

        // el actor es otro administrador inactivo no cuenta: el único activo es $soloAdmin
        $this->servicio->actualizar($soloAdmin, ['name' => $soloAdmin->name, 'email' => $soloAdmin->email, 'rol' => 'operador_almacen'], $otro);
    }

    public function test_desactivar_bloquea_el_acceso_y_revoca_los_tokens_de_la_api(): void
    {
        $usuario = $this->operador();
        $usuario->createToken('integracion-ventas');

        $this->servicio->cambiarEstado($usuario, $this->admin);

        $this->assertFalse($usuario->fresh()->activo);
        $this->assertSame(0, $usuario->tokens()->count());
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => User::class, 'auditable_id' => $usuario->id, 'campo' => 'activo', 'user_id' => $this->admin->id,
        ]);
    }

    public function test_reactivar_vuelve_a_dar_acceso(): void
    {
        $usuario = $this->operador(['activo' => false]);

        $this->servicio->cambiarEstado($usuario, $this->admin);

        $this->assertTrue($usuario->fresh()->activo);
    }

    public function test_nadie_puede_desactivarse_a_si_mismo(): void
    {
        User::factory()->create(['rol' => 'administrador']);

        $this->expectException(UsuarioNoPermitidoException::class);

        $this->servicio->cambiarEstado($this->admin, $this->admin);
    }

    public function test_no_se_puede_desactivar_al_ultimo_administrador_activo(): void
    {
        $operador = $this->operador();
        $inactivo = User::factory()->create(['rol' => 'administrador', 'activo' => false]);

        $this->expectException(UsuarioNoPermitidoException::class);

        // $inactivo intenta (como actor) desactivar al único administrador activo
        $this->servicio->cambiarEstado($this->admin, $inactivo);
        $this->assertNotNull($operador);
    }

    public function test_se_puede_desactivar_a_un_administrador_si_queda_otro_activo(): void
    {
        $otroAdmin = User::factory()->create(['rol' => 'administrador']);

        $this->servicio->cambiarEstado($otroAdmin, $this->admin);

        $this->assertFalse($otroAdmin->fresh()->activo);
    }

    public function test_restablecer_la_contrasena_la_cambia_y_no_guarda_su_valor_en_el_historial(): void
    {
        $usuario = $this->operador();

        $this->servicio->restablecerPassword($usuario, 'nueva-clave-2026', $this->admin);

        $this->assertTrue(Hash::check('nueva-clave-2026', $usuario->fresh()->password));
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => User::class, 'auditable_id' => $usuario->id, 'campo' => 'password', 'user_id' => $this->admin->id,
        ]);
        $this->assertSame(0, AuditoriaCambio::where('valor_nuevo', 'like', '%nueva-clave-2026%')->count());
        $this->assertSame(0, AuditoriaCambio::where('valor_anterior', 'like', '$2y$%')->count());
    }

    public function test_restablecer_la_contrasena_cierra_las_sesiones_de_la_api(): void
    {
        $usuario = $this->operador();
        $usuario->createToken('integracion');

        $this->servicio->restablecerPassword($usuario, 'nueva-clave-2026', $this->admin);

        $this->assertSame(0, $usuario->tokens()->count());
    }

    public function test_cambiar_la_propia_contrasena_exige_la_actual(): void
    {
        $usuario = $this->operador(['password' => 'clave-actual-1']);

        $this->servicio->cambiarPropiaPassword($usuario, 'clave-actual-1', 'clave-nueva-22');
        $this->assertTrue(Hash::check('clave-nueva-22', $usuario->fresh()->password));

        $this->expectException(UsuarioNoPermitidoException::class);
        $this->servicio->cambiarPropiaPassword($usuario->fresh(), 'incorrecta', 'otra-clave-333');
    }
}
