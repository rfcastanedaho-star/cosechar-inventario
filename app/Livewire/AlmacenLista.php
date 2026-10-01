<?php

namespace App\Livewire;

use App\Repositories\AlmacenRepository;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AlmacenLista extends Component
{
    #[Computed]
    public function almacenes(): Collection
    {
        return (new AlmacenRepository)->all();
    }

    public function render()
    {
        return view('livewire.almacen-lista');
    }
}
