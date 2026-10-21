<?php

namespace App\Livewire;

use App\Exceptions\CategoriaNoPermitidaException;
use App\Models\Categoria;
use App\Services\CategoriaService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CategoriaLista extends Component
{
    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('gestionar-categorias');
    }

    /** Grupos principales con sus subcategorías y cuántos productos tiene cada una. */
    #[Computed]
    public function grupos(): Collection
    {
        return Categoria::whereNull('categoria_padre_id')
            ->withCount('productos')
            ->with(['subcategorias' => fn ($q) => $q->withCount('productos')->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();
    }

    public function eliminar(int $categoriaId, CategoriaService $servicio): void
    {
        $this->authorize('gestionar-categorias');
        $this->error = null;

        try {
            $servicio->eliminar(Categoria::findOrFail($categoriaId), auth()->user());
        } catch (CategoriaNoPermitidaException $e) {
            $this->error = $e->getMessage();

            return;
        }

        unset($this->grupos);
    }

    public function render()
    {
        return view('livewire.categoria-lista');
    }
}
