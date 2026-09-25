<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'categoria' => $this->whenLoaded('categoria', fn () => $this->categoria->nombre),
            'unidad_medida' => $this->unidad_medida,
            'stock_minimo' => $this->stock_minimo,
            'stock_actual' => (int) ($this->stock_actual ?? 0),
            'precio' => (float) $this->precio,
            'maneja_vencimiento' => $this->maneja_vencimiento,
        ];
    }
}
