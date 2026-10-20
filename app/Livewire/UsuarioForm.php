<?php

namespace App\Livewire;

use App\Exceptions\UsuarioNoPermitidoException;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UsuarioForm extends Component
{
    public ?int $usuarioId = null;

    public string $name = '';

    public string $email = '';

    public string $rol = 'operador_almacen';

    public string $password = '';

    public string $password_confirmation = '';

    public string $nuevaPassword = '';

    public string $nuevaPassword_confirmation = '';

    public bool $passwordRestablecida = false;

    public function mount(?User $usuario = null): void
    {
        $this->authorize('gestionar-usuarios');

        if ($usuario?->exists) {
            $this->usuarioId = $usuario->id;
            $this->name = $usuario->name;
            $this->email = $usuario->email;
            $this->rol = $usuario->rol;
        }
    }

    #[Computed]
    public function editando(): bool
    {
        return $this->usuarioId !== null;
    }

    protected function rules(): array
    {
        $reglas = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->usuarioId)],
            'rol' => ['required', Rule::in(array_keys(User::ROLES))],
        ];

        if (! $this->editando) {
            $reglas['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $reglas;
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingrese un correo válido.',
            'email.unique' => 'Ya existe un usuario con este correo.',
            'rol.in' => 'Seleccione un rol válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'nuevaPassword.required' => 'Escriba la nueva contraseña.',
            'nuevaPassword.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'nuevaPassword.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }

    public function guardar(UsuarioService $servicio)
    {
        $this->authorize('gestionar-usuarios');

        $data = $this->validate();

        try {
            if ($this->editando) {
                $servicio->actualizar(User::findOrFail($this->usuarioId), [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'rol' => $data['rol'],
                ], auth()->user());
            } else {
                $servicio->crear($data);
            }
        } catch (UsuarioNoPermitidoException $e) {
            $this->addError('rol', $e->getMessage());

            return;
        }

        return redirect()->route('usuarios.index');
    }

    public function restablecerPassword(UsuarioService $servicio): void
    {
        $this->authorize('gestionar-usuarios');
        abort_unless($this->editando, 404);

        $this->passwordRestablecida = false;

        $this->validate([
            'nuevaPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $servicio->restablecerPassword(User::findOrFail($this->usuarioId), $this->nuevaPassword, auth()->user());

        $this->reset(['nuevaPassword', 'nuevaPassword_confirmation']);
        $this->passwordRestablecida = true;
    }

    public function render()
    {
        return view('livewire.usuario-form');
    }
}
