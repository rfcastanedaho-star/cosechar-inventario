<?php

namespace App\Livewire;

use App\Repositories\ProveedorRepository;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProveedorLista extends Component
{
    #[Computed]
    public function proveedores(): Collection
    {
        return (new ProveedorRepository)->all();
    }

    public function render()
    {
        return view('livewire.proveedor-lista');
    }
}
