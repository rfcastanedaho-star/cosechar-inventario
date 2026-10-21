<?php

namespace App\Livewire;

use App\Exceptions\AnulacionNoPermitidaException;
use App\Livewire\Concerns\FiltraHistorial;
use App\Livewire\Concerns\GestionaAnulacion;
use App\Models\Almacen;
use App\Models\Venta;
use App\Repositories\VentaRepository;
use App\Services\AnulacionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VentaLista extends Component
{
    use FiltraHistorial;
    use GestionaAnulacion;

    #[Computed]
    public function ventas(): LengthAwarePaginator
    {
        return app(VentaRepository::class)->historial($this->filtros());
    }

    /** Almacenes para el filtro de la pantalla. */
    #[Computed]
    public function almacenesFiltro(): Collection
    {
        return Almacen::orderBy('nombre')->get();
    }

    public function confirmarAnulacion(AnulacionService $anulaciones): void
    {
        $motivo = $this->validarMotivoAnulacion();

        try {
            $anulaciones->anularVenta(Venta::findOrFail($this->anulandoId), auth()->user(), $motivo);
        } catch (AnulacionNoPermitidaException $e) {
            $this->errorAnulacion = $e->getMessage();

            return;
        }

        $this->cancelarAnulacion();
        unset($this->ventas);
    }

    public function render()
    {
        return view('livewire.venta-lista');
    }
}
