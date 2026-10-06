<?php

namespace App\Livewire;

use App\Models\Almacen;
use App\Repositories\AlmacenRepository;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AlmacenLista extends Component
{
    public ?string $errorEstado = null;

    #[Computed]
    public function almacenes(): Collection
    {
        return (new AlmacenRepository)->all();
    }

    public function cambiarEstado(int $almacenId, AuditoriaService $auditoria): void
    {
        $this->authorize('anular-registros');
        $this->errorEstado = null;

        $almacen = Almacen::findOrFail($almacenId);

        if ($almacen->activo && $almacen->lotes()->where('cantidad', '>', 0)->exists()) {
            $this->errorEstado = 'No se puede desactivar: el almacén todavía tiene stock.';

            return;
        }

        $auditoria->actualizar($almacen, ['activo' => ! $almacen->activo], auth()->user());
        unset($this->almacenes);
    }

    public function render()
    {
        return view('livewire.almacen-lista');
    }
}
