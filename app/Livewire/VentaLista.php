<?php

namespace App\Livewire;

use App\Exceptions\AnulacionNoPermitidaException;
use App\Livewire\Concerns\GestionaAnulacion;
use App\Models\Venta;
use App\Repositories\VentaRepository;
use App\Services\AnulacionService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VentaLista extends Component
{
    use GestionaAnulacion;

    #[Computed]
    public function ventas(): Collection
    {
        return (new VentaRepository)->all();
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
