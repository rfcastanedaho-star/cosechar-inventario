<?php

namespace App\Livewire;

use App\Exceptions\CategoriaNoPermitidaException;
use App\Models\Categoria;
use App\Services\CategoriaService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class CategoriaForm extends Component
{
    public ?int $categoriaId = null;

    public string $nombre = '';

    /** Grupo al que pertenece; vacío si es un grupo principal. Se puede traer en la dirección (?grupo=). */
    #[Url(as: 'grupo')]
    public ?int $categoria_padre_id = null;

    public function mount(?Categoria $categoria = null): void
    {
        $this->authorize('gestionar-categorias');

        if ($categoria?->exists) {
            $this->categoriaId = $categoria->id;
            $this->nombre = $categoria->nombre;
            $this->categoria_padre_id = $categoria->categoria_padre_id;
        }
    }

    #[Computed]
    public function editando(): bool
    {
        return $this->categoriaId !== null;
    }

    /** Grupos principales entre los que se puede elegir al crear. */
    #[Computed]
    public function grupos(): Collection
    {
        return Categoria::whereNull('categoria_padre_id')->orderBy('nombre')->get();
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'categoria_padre_id' => ['nullable', 'integer'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede pasar de 100 caracteres.',
        ];
    }

    public function guardar(CategoriaService $servicio)
    {
        $this->authorize('gestionar-categorias');

        $data = $this->validate();

        try {
            if ($this->editando) {
                $servicio->renombrar(Categoria::findOrFail($this->categoriaId), $data['nombre'], auth()->user());
            } else {
                $servicio->crear($data['nombre'], $data['categoria_padre_id'] ?? null, auth()->user());
            }
        } catch (CategoriaNoPermitidaException $e) {
            $this->addError($e->campo, $e->getMessage());

            return;
        }

        return redirect()->route('categorias.index');
    }

    public function render()
    {
        return view('livewire.categoria-form');
    }
}
