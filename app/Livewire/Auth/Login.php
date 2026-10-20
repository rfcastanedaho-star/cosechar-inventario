<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingrese un correo válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }

    public function autenticar()
    {
        $this->email = trim($this->email);
        $this->password = trim($this->password);

        $credentials = $this->validate();

        if (! Auth::attempt($credentials + ['activo' => true], $this->remember)) {
            $this->addError('email', $this->usuarioDesactivado($credentials)
                ? 'Tu usuario está desactivado. Consulta con el administrador.'
                : 'Las credenciales no coinciden con nuestros registros.');

            return;
        }

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        return redirect()->intended(route('dashboard'));
    }

    /** Solo se avisa que está desactivado si la contraseña era correcta, para no revelar quién existe. */
    private function usuarioDesactivado(array $credentials): bool
    {
        $usuario = User::where('email', $credentials['email'])->first();

        return $usuario !== null && ! $usuario->activo && Hash::check($credentials['password'], $usuario->password);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
