<?php

namespace App\Livewire;

use App\Livewire\Concerns\FiltraHistorial;
use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use App\Services\AuditoriaService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProveedorLista extends Component
{
    use FiltraHistorial;

    protected function estadosPermitidos(): array
    {
        return ['activo', 'inactivo'];
    }

    #[Computed]
    public function proveedores(): LengthAwarePaginator
    {
        return app(ProveedorRepository::class)->historial($this->filtros());
    }

    public function cambiarEstado(int $proveedorId, AuditoriaService $auditoria): void
    {
        $this->authorize('anular-registros');

        $proveedor = Proveedor::findOrFail($proveedorId);

        $auditoria->actualizar($proveedor, ['activo' => ! $proveedor->activo], auth()->user());
        unset($this->proveedores);
    }

    public function render()
    {
        return view('livewire.proveedor-lista');
    }
}
