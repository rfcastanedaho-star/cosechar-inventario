<?php

namespace App\Services;

use App\Exceptions\UsuarioNoPermitidoException;
use App\Models\AuditoriaCambio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsuarioService
{
    public function __construct(private AuditoriaService $auditoria) {}

    /**
     * @param  array{name: string, email: string, rol: string, password: string}  $datos
     */
    public function crear(array $datos): User
    {
        return User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'rol' => $datos['rol'],
            'password' => $datos['password'],
            'activo' => true,
        ]);
    }

    /**
     * Cambia nombre, correo y rol dejando historial. Nadie se quita a sí mismo
     * el rol de administrador y siempre debe quedar un administrador activo.
     *
     * @param  array{name: string, email: string, rol: string}  $datos
     */
    public function actualizar(User $usuario, array $datos, User $actor): User
    {
        $pierdeRolAdmin = $usuario->rol === 'administrador' && $datos['rol'] !== 'administrador';

        if ($pierdeRolAdmin) {
            if ($actor->is($usuario)) {
                throw UsuarioNoPermitidoException::autoDegradacion();
            }

            $this->asegurarOtroAdministradorActivo($usuario);
        }

        $this->auditoria->actualizar($usuario, [
            'name' => $datos['name'],
            'email' => $datos['email'],
            'rol' => $datos['rol'],
        ], $actor);

        return $usuario->refresh();
    }

    /** Desactiva o reactiva. Al desactivar se revocan sus tokens de la API. */
    public function cambiarEstado(User $usuario, User $actor): User
    {
        if ($usuario->activo) {
            if ($actor->is($usuario)) {
                throw UsuarioNoPermitidoException::autoDesactivacion();
            }

            if ($usuario->rol === 'administrador') {
                $this->asegurarOtroAdministradorActivo($usuario);
            }
        }

        DB::transaction(function () use ($usuario, $actor) {
            $this->auditoria->actualizar($usuario, ['activo' => ! $usuario->activo], $actor);

            if (! $usuario->activo) {
                $usuario->tokens()->delete();
            }
        });

        return $usuario->refresh();
    }

    /** El administrador fija una contraseña nueva. Su valor nunca se guarda en el historial. */
    public function restablecerPassword(User $usuario, string $nueva, User $actor): void
    {
        $this->guardarPassword($usuario, $nueva, $actor);
    }

    public function cambiarPropiaPassword(User $usuario, string $actual, string $nueva): void
    {
        if (! Hash::check($actual, $usuario->password)) {
            throw UsuarioNoPermitidoException::passwordActualIncorrecta();
        }

        $this->guardarPassword($usuario, $nueva, $usuario);
    }

    private function guardarPassword(User $usuario, string $nueva, User $actor): void
    {
        DB::transaction(function () use ($usuario, $nueva, $actor) {
            $usuario->forceFill(['password' => $nueva, 'remember_token' => Str::random(60)])->save();
            $usuario->tokens()->delete();

            AuditoriaCambio::create([
                'auditable_type' => User::class,
                'auditable_id' => $usuario->id,
                'user_id' => $actor->id,
                'campo' => 'password',
                'valor_anterior' => null,
                'valor_nuevo' => '(contraseña cambiada)',
            ]);
        });
    }

    private function asegurarOtroAdministradorActivo(User $usuario): void
    {
        $quedan = User::where('rol', 'administrador')
            ->where('activo', true)
            ->whereKeyNot($usuario->id)
            ->exists();

        if (! $quedan) {
            throw UsuarioNoPermitidoException::ultimoAdministrador();
        }
    }
}
