<?php

namespace App\Services;

use App\Models\AuditoriaCambio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditoriaService
{
    /**
     * Actualiza el modelo y deja una fila de historial por cada campo que
     * realmente cambió (quién, cuándo, valor anterior y valor nuevo).
     */
    public function actualizar(Model $modelo, array $datos, User $usuario): void
    {
        DB::transaction(function () use ($modelo, $datos, $usuario) {
            $modelo->fill($datos);
            $cambios = $modelo->getDirty();

            foreach ($cambios as $campo => $nuevo) {
                AuditoriaCambio::create([
                    'auditable_type' => $modelo::class,
                    'auditable_id' => $modelo->getKey(),
                    'user_id' => $usuario->id,
                    'campo' => $campo,
                    'valor_anterior' => $this->texto($modelo->getOriginal($campo)),
                    'valor_nuevo' => $this->texto($nuevo),
                ]);
            }

            $modelo->save();
        });
    }

    private function texto(mixed $valor): ?string
    {
        return $valor === null ? null : (string) $valor;
    }
}
