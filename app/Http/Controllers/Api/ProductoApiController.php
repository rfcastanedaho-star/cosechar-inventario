<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductoResource;
use App\Repositories\ProductoRepository;
use Illuminate\Http\JsonResponse;

class ProductoApiController extends Controller
{
    public function __construct(private ProductoRepository $productos) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ProductoResource::collection($this->productos->conStockActual()),
        ]);
    }

    public function stock(int $id): JsonResponse
    {
        $producto = $this->productos->conStockActualPorId($id);

        if (! $producto) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        return response()->json([
            'data' => [
                'producto_id' => $producto->id,
                'codigo' => $producto->codigo,
                'stock_actual' => (int) ($producto->stock_actual ?? 0),
                'stock_minimo' => $producto->stock_minimo,
            ],
        ]);
    }
}
