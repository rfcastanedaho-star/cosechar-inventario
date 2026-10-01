<?php

namespace App\Livewire;

use App\Repositories\AlmacenRepository;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AlmacenForm extends Component
{
    public string $nombre = '';

    public string $direccion = '';

    public string $encargado = '';

    public bool $creado = false;

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150', 'unique:almacenes,nombre'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'encargado' => ['nullable', 'string', 'max:150'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Ya existe un almacén registrado con este nombre.',
        ];
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    public function guardar(AlmacenRepository $almacenes): void
    {
        $data = $this->validate();

        $almacenes->create([
            'nombre' => $data['nombre'],
            'direccion' => $data['direccion'] ?: null,
            'encargado' => $data['encargado'] ?: null,
        ]);

        $this->reset(['nombre', 'direccion', 'encargado']);
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.almacen-form');
    }
}
