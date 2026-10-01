<?php

namespace App\Livewire;

use App\Repositories\ProveedorRepository;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProveedorForm extends Component
{
    public string $nombre = '';

    public string $ruc = '';

    public string $telefono = '';

    public string $direccion = '';

    public bool $creado = false;

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'ruc' => ['nullable', 'digits:11', 'unique:proveedores,ruc'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'ruc.unique' => 'Ya existe un proveedor registrado con este RUC.',
        ];
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    public function guardar(ProveedorRepository $proveedores): void
    {
        $data = $this->validate();

        $proveedores->create([
            'nombre' => $data['nombre'],
            'ruc' => $data['ruc'] ?: null,
            'telefono' => $data['telefono'] ?: null,
            'direccion' => $data['direccion'] ?: null,
        ]);

        $this->reset(['nombre', 'ruc', 'telefono', 'direccion']);
        $this->creado = true;
    }

    public function render()
    {
        return view('livewire.proveedor-form');
    }
}
