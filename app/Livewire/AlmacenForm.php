<?php

namespace App\Livewire;

use App\Models\Almacen;
use App\Repositories\AlmacenRepository;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AlmacenForm extends Component
{
    public ?int $almacenId = null;

    public string $nombre = '';

    public string $direccion = '';

    public string $encargado = '';

    public bool $creado = false;

    public function mount(?Almacen $almacen = null): void
    {
        if ($almacen?->exists) {
            $this->almacenId = $almacen->id;
            $this->nombre = $almacen->nombre;
            $this->direccion = $almacen->direccion ?? '';
            $this->encargado = $almacen->encargado ?? '';
        }
    }

    #[Computed]
    public function editando(): bool
    {
        return $this->almacenId !== null;
    }

    /** El nombre identifica al almacén en compras y ventas: solo el administrador lo cambia. */
    #[Computed]
    public function puedeEditarNombre(): bool
    {
        return ! $this->editando || Gate::allows('editar-datos-sensibles');
    }

    protected function rules(): array
    {
        $reglas = [
            'direccion' => ['nullable', 'string', 'max:255'],
            'encargado' => ['nullable', 'string', 'max:150'],
        ];

        if ($this->puedeEditarNombre) {
            $reglas['nombre'] = ['required', 'string', 'max:150', Rule::unique('almacenes', 'nombre')->ignore($this->almacenId)];
        }

        return $reglas;
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
        if ($property === 'nombre' && ! $this->puedeEditarNombre) {
            return;
        }

        $this->validateOnly($property);
    }

    public function guardar(AlmacenRepository $almacenes, AuditoriaService $auditoria): void
    {
        $data = $this->validate();

        if ($this->editando) {
            $cambios = [
                'direccion' => $data['direccion'] ?: null,
                'encargado' => $data['encargado'] ?: null,
            ];

            if ($this->puedeEditarNombre) {
                $cambios['nombre'] = $data['nombre'];
            }

            $auditoria->actualizar(Almacen::findOrFail($this->almacenId), $cambios, auth()->user());
            $this->creado = true;

            return;
        }

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
