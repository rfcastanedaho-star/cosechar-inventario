<?php

namespace App\Livewire;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProveedorForm extends Component
{
    public ?int $proveedorId = null;

    public string $nombre = '';

    public string $ruc = '';

    public string $telefono = '';

    public string $direccion = '';

    public bool $creado = false;

    public function mount(?Proveedor $proveedor = null): void
    {
        if ($proveedor?->exists) {
            $this->proveedorId = $proveedor->id;
            $this->nombre = $proveedor->nombre;
            $this->ruc = $proveedor->ruc ?? '';
            $this->telefono = $proveedor->telefono ?? '';
            $this->direccion = $proveedor->direccion ?? '';
        }
    }

    #[Computed]
    public function editando(): bool
    {
        return $this->proveedorId !== null;
    }

    /** Nombre y RUC identifican a quién se le pagó: solo el administrador los cambia. */
    #[Computed]
    public function puedeEditarIdentidad(): bool
    {
        return ! $this->editando || Gate::allows('editar-datos-sensibles');
    }

    protected function rules(): array
    {
        $reglas = [
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->puedeEditarIdentidad) {
            $reglas['nombre'] = ['required', 'string', 'max:150'];
            $reglas['ruc'] = ['nullable', 'digits:11', Rule::unique('proveedores', 'ruc')->ignore($this->proveedorId)];
        }

        return $reglas;
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
        if (in_array($property, ['nombre', 'ruc'], true) && ! $this->puedeEditarIdentidad) {
            return;
        }

        $this->validateOnly($property);
    }

    public function guardar(ProveedorRepository $proveedores, AuditoriaService $auditoria): void
    {
        $data = $this->validate();

        if ($this->editando) {
            $cambios = [
                'telefono' => $data['telefono'] ?: null,
                'direccion' => $data['direccion'] ?: null,
            ];

            if ($this->puedeEditarIdentidad) {
                $cambios['nombre'] = $data['nombre'];
                $cambios['ruc'] = $data['ruc'] ?: null;
            }

            $auditoria->actualizar(Proveedor::findOrFail($this->proveedorId), $cambios, auth()->user());
            $this->creado = true;

            return;
        }

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
