<?php

namespace App\Livewire;

use App\Exceptions\AnulacionNoPermitidaException;
use App\Livewire\Concerns\GestionaAnulacion;
use App\Models\Compra;
use App\Repositories\CompraRepository;
use App\Services\AnulacionService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CompraLista extends Component
{
    use GestionaAnulacion;

    #[Computed]
    public function compras(): Collection
    {
        return (new CompraRepository)->all();
    }

    public function confirmarAnulacion(AnulacionService $anulaciones): void
    {
        $motivo = $this->validarMotivoAnulacion();

        try {
            $anulaciones->anularCompra(Compra::findOrFail($this->anulandoId), auth()->user(), $motivo);
        } catch (AnulacionNoPermitidaException $e) {
            $this->errorAnulacion = $e->getMessage();

            return;
        }

        $this->cancelarAnulacion();
        unset($this->compras);
    }

    public function render()
    {
        return view('livewire.compra-lista');
    }
}
