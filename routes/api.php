<?php

use App\Http\Controllers\Api\MovimientoApiController;
use App\Http\Controllers\Api\ProductoApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/productos', [ProductoApiController::class, 'index']);
    Route::get('/productos/{id}/stock', [ProductoApiController::class, 'stock']);
    Route::post('/movimientos', [MovimientoApiController::class, 'store']);
});
