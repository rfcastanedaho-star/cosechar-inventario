<?php

namespace App\Livewire;

use App\Repositories\CompraRepository;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CompraLista extends Component
{
    #[Computed]
    public function compras(): Collection
    {
        return (new CompraRepository)->all();
    }

    public function render()
    {
        return view('livewire.compra-lista');
    }
}
