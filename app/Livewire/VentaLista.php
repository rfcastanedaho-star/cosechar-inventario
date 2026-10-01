<?php

namespace App\Livewire;

use App\Repositories\VentaRepository;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VentaLista extends Component
{
    #[Computed]
    public function ventas(): Collection
    {
        return (new VentaRepository)->all();
    }

    public function render()
    {
        return view('livewire.venta-lista');
    }
}
