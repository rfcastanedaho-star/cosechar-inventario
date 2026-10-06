<?php

namespace App\Livewire;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProveedorLista extends Component
{
    #[Computed]
    public function proveedores(): Collection
    {
        return (new ProveedorRepository)->all();
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
