<?php

namespace App\Livewire\Concerns;

trait GestionaAnulacion
{
    public ?int $anulandoId = null;

    public string $motivoAnulacion = '';

    public ?string $errorAnulacion = null;

    public function iniciarAnulacion(int $id): void
    {
        $this->authorize('anular-registros');

        $this->anulandoId = $id;
        $this->motivoAnulacion = '';
        $this->errorAnulacion = null;
    }

    public function cancelarAnulacion(): void
    {
        $this->reset(['anulandoId', 'motivoAnulacion', 'errorAnulacion']);
        $this->resetErrorBag();
    }

    protected function validarMotivoAnulacion(): string
    {
        $this->authorize('anular-registros');

        return $this->validate(
            ['motivoAnulacion' => ['required', 'string', 'min:5', 'max:255']],
            [
                'motivoAnulacion.required' => 'Escribe el motivo de la anulación.',
                'motivoAnulacion.min' => 'El motivo debe tener al menos 5 caracteres.',
            ],
        )['motivoAnulacion'];
    }
}
