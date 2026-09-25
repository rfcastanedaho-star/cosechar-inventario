<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
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

        if (! Auth::attempt($credentials, $this->remember)) {
            $this->addError('email', 'Las credenciales no coinciden con nuestros registros.');

            return;
        }

        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
