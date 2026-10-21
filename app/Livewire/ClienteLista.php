<?php

namespace App\Livewire;

use App\Livewire\Concerns\FiltraHistorial;
use App\Models\Cliente;
use App\Repositories\ClienteRepository;
use App\Services\AuditoriaService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ClienteLista extends Component
{
    use FiltraHistorial;

    protected function estadosPermitidos(): array
    {
        return ['activo', 'inactivo'];
    }

    #[Computed]
    public function clientes(): LengthAwarePaginator
    {
        return app(ClienteRepository::class)->historial($this->filtros());
    }

    public function cambiarEstado(int $clienteId, AuditoriaService $auditoria): void
    {
        $this->authorize('anular-registros');

        $cliente = Cliente::findOrFail($clienteId);

        $auditoria->actualizar($cliente, ['activo' => ! $cliente->activo], auth()->user());
        unset($this->clientes);
    }

    public function render()
    {
        return view('livewire.cliente-lista');
    }
}
