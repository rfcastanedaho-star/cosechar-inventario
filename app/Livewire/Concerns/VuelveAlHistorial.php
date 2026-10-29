<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Los formularios de corrección de proveedor y cliente se abren desde el historial de
 * compras o de ventas (?desde=compras|ventas) y al terminar vuelven allí. Cualquier otro
 * valor lleva al directorio.
 */
trait VuelveAlHistorial
{
    #[Url]
    public string $desde = '';

    /** @return array{ruta: string, etiqueta: string} */
    #[Computed]
    public function retorno(): array
    {
        return match ($this->desde) {
            'compras' => ['ruta' => 'compras.historial', 'etiqueta' => 'Volver al historial de compras'],
            'ventas' => ['ruta' => 'ventas.historial', 'etiqueta' => 'Volver al historial de ventas'],
            default => ['ruta' => $this->rutaDirectorio(), 'etiqueta' => $this->etiquetaDirectorio()],
        };
    }

    /** Nombre de la ruta del directorio al que se vuelve si no se viene de un historial. */
    abstract protected function rutaDirectorio(): string;

    abstract protected function etiquetaDirectorio(): string;
}
