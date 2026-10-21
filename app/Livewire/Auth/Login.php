<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Login extends Component
{
    /** Intentos fallidos permitidos por correo y dirección IP antes de bloquear. */
    private const MAX_INTENTOS = 5;

    /** Segundos que dura el bloqueo y la cuenta de intentos. */
    private const SEGUNDOS_BLOQUEO = 60;

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
        $clave = $this->claveDeLimite();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($clave);
            $this->addError('email', 'Demasiados intentos. Intenta de nuevo en '.$segundos.($segundos === 1 ? ' segundo.' : ' segundos.'));

            return;
        }

        if (! Auth::attempt($credentials + ['activo' => true], $this->remember)) {
            if ($this->usuarioDesactivado($credentials)) {
                $this->addError('email', 'Tu usuario está desactivado. Consulta con el administrador.');

                return;
            }

            RateLimiter::hit($clave, self::SEGUNDOS_BLOQUEO);
            $this->addError('email', 'Las credenciales no coinciden con nuestros registros.');

            return;
        }

        RateLimiter::clear($clave);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        return redirect()->intended(route('dashboard'));
    }

    /** El correo se normaliza para que mayúsculas o espacios no permitan esquivar el límite. */
    private function claveDeLimite(): string
    {
        return 'login:'.Str::lower(trim($this->email)).'|'.request()->ip();
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
