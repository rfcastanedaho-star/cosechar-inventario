<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AlmacenRequeridoException;
use App\Exceptions\StockInsuficienteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegistrarMovimientoRequest;
use App\Models\Producto;
use App\Services\SalidaApiService;
use Illuminate\Http\JsonResponse;

class MovimientoApiController extends Controller
{
    public function __construct(private SalidaApiService $salidas) {}

    public function store(RegistrarMovimientoRequest $request): JsonResponse
    {
        $producto = Producto::findOrFail($request->integer('producto_id'));

        try {
            $resultado = $this->salidas->registrar(
                $producto,
                $request->integer('cantidad'),
                $request->user(),
                $request->filled('almacen_id') ? $request->integer('almacen_id') : null,
                $request->string('motivo')->value() ?: 'venta',
                $request->only(['cliente_nombre', 'cliente_documento', 'numero_comprobante', 'precio_unitario']),
            );
        } catch (StockInsuficienteException|AlmacenRequeridoException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $venta = $resultado['venta'];

        return response()->json([
            'data' => $resultado['movimientos']->map(fn ($m) => [
                'id' => $m->id,
                'lote_id' => $m->lote_id,
                'tipo' => $m->tipo,
                'cantidad' => $m->cantidad,
                'motivo' => $m->motivo,
            ])->values(),
            'venta' => $venta ? [
                'id' => $venta->id,
                'cliente_nombre' => $venta->cliente_nombre,
                'almacen_id' => $venta->almacen_id,
                'total' => $venta->total,
            ] : null,
        ], 201);
    }
}
