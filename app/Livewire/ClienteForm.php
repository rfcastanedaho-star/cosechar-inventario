<?php

namespace App\Livewire;

use App\Livewire\Concerns\VuelveAlHistorial;
use App\Models\Cliente;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ClienteForm extends Component
{
    use VuelveAlHistorial;

    public ?int $clienteId = null;

    public string $nombre = '';

    public string $documento = '';

    public string $telefono = '';

    public string $direccion = '';

    /** Los clientes se registran solos al vender; esta pantalla solo corrige sus datos. */
    public function mount(Cliente $cliente): void
    {
        $this->clienteId = $cliente->id;
        $this->nombre = $cliente->nombre;
        $this->documento = $cliente->documento ?? '';
        $this->telefono = $cliente->telefono ?? '';
        $this->direccion = $cliente->direccion ?? '';
    }

    #[Computed]
    public function editando(): bool
    {
        return $this->clienteId !== null;
    }

    /** El nombre y el documento identifican al cliente en sus ventas: al editar, solo el administrador los cambia. */
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
            $reglas['documento'] = [
                'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('clientes', 'documento')->ignore($this->clienteId),
            ];
        }

        return $reglas;
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'documento.unique' => 'Ya existe un cliente con este documento.',
            'documento.regex' => 'El documento solo puede tener letras, números y guiones.',
            'telefono.max' => 'El teléfono es demasiado largo.',
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['nombre', 'documento'], true) && ! $this->puedeEditarIdentidad) {
            return;
        }

        $this->validateOnly($property);
    }

    public function guardar(AuditoriaService $auditoria)
    {
        $data = $this->validate();

        $datos = [
            'telefono' => $data['telefono'] ?: null,
            'direccion' => $data['direccion'] ?: null,
        ];

        if ($this->puedeEditarIdentidad) {
            $datos['nombre'] = $data['nombre'];
            $datos['documento'] = $data['documento'] ?: null;
        }

        $auditoria->actualizar(Cliente::findOrFail($this->clienteId), $datos, auth()->user());

        return redirect()->route($this->retorno['ruta']);
    }

    protected function rutaDirectorio(): string
    {
        return 'clientes.index';
    }

    protected function etiquetaDirectorio(): string
    {
        return 'Volver a clientes';
    }

    public function render()
    {
        return view('livewire.cliente-form');
    }
}
