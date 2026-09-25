<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StockInsuficienteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegistrarMovimientoRequest;
use App\Models\Producto;
use App\Services\StockMovimientoService;
use Illuminate\Http\JsonResponse;

class MovimientoApiController extends Controller
{
    public function __construct(private StockMovimientoService $servicio) {}

    public function store(RegistrarMovimientoRequest $request): JsonResponse
    {
        $producto = Producto::findOrFail($request->integer('producto_id'));

        try {
            $movimientos = $this->servicio->registrarSalida(
                $producto,
                $request->integer('cantidad'),
                $request->user(),
                $request->string('motivo')->value() ?: 'venta',
            );
        } catch (StockInsuficienteException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $movimientos->map(fn ($m) => [
                'id' => $m->id,
                'lote_id' => $m->lote_id,
                'tipo' => $m->tipo,
                'cantidad' => $m->cantidad,
                'motivo' => $m->motivo,
            ]),
        ], 201);
    }
}
