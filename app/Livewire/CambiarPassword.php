<?php

namespace App\Livewire;

use App\Exceptions\UsuarioNoPermitidoException;
use App\Services\UsuarioService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CambiarPassword extends Component
{
    public string $password_actual = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $actualizada = false;

    protected function rules(): array
    {
        return [
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:password_actual'],
        ];
    }

    protected function messages(): array
    {
        return [
            'password_actual.required' => 'Escriba su contraseña actual.',
            'password.required' => 'Escriba la nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ];
    }

    public function guardar(UsuarioService $servicio): void
    {
        $this->actualizada = false;

        $data = $this->validate();

        try {
            $servicio->cambiarPropiaPassword(auth()->user(), $data['password_actual'], $data['password']);
        } catch (UsuarioNoPermitidoException $e) {
            $this->addError('password_actual', $e->getMessage());

            return;
        }

        $this->reset(['password_actual', 'password', 'password_confirmation']);
        $this->actualizada = true;
    }

    public function render()
    {
        return view('livewire.cambiar-password');
    }
}
