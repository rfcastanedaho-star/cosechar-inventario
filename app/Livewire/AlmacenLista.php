<?php

namespace App\Livewire;

use App\Livewire\Concerns\FiltraHistorial;
use App\Models\Almacen;
use App\Repositories\AlmacenRepository;
use App\Services\AuditoriaService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AlmacenLista extends Component
{
    use FiltraHistorial;

    public ?string $errorEstado = null;

    protected function estadosPermitidos(): array
    {
        return ['activo', 'inactivo'];
    }

    #[Computed]
    public function almacenes(): LengthAwarePaginator
    {
        return app(AlmacenRepository::class)->historial($this->filtros());
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
