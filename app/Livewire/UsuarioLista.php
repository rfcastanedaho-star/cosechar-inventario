<?php

namespace App\Livewire;

use App\Exceptions\UsuarioNoPermitidoException;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UsuarioLista extends Component
{
    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('gestionar-usuarios');
    }

    #[Computed]
    public function usuarios(): Collection
    {
        return User::orderBy('name')->get();
    }

    public function cambiarEstado(int $usuarioId, UsuarioService $servicio): void
    {
        $this->authorize('gestionar-usuarios');
        $this->error = null;

        try {
            $servicio->cambiarEstado(User::findOrFail($usuarioId), auth()->user());
        } catch (UsuarioNoPermitidoException $e) {
            $this->error = $e->getMessage();

            return;
        }

        unset($this->usuarios);
    }

    public function render()
    {
        return view('livewire.usuario-lista');
    }
}
