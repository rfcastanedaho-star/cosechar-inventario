<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las ventas anteriores no guardaban de qué lote salió cada línea, pero el kardex sí tiene las salidas
     * de esa venta. Se reparten entre sus líneas del mismo producto, en orden. Las ventas que ya tienen
     * lotes registrados no se tocan, así que se puede correr más de una vez.
     */
    public function up(): void
    {
        $ventas = DB::table('stock_movimientos')
            ->where('tipo', 'salida')
            ->whereNotNull('venta_id')
            ->distinct()
            ->pluck('venta_id');

        foreach ($ventas as $ventaId) {
            $detalles = DB::table('venta_detalles')->where('venta_id', $ventaId)->orderBy('id')->get();

            if ($detalles->isEmpty() || DB::table('venta_detalle_lotes')->whereIn('venta_detalle_id', $detalles->pluck('id'))->exists()) {
                continue;
            }

            $pendientes = DB::table('stock_movimientos')
                ->join('lotes', 'lotes.id', '=', 'stock_movimientos.lote_id')
                ->where('stock_movimientos.tipo', 'salida')
                ->where('stock_movimientos.venta_id', $ventaId)
                ->orderBy('stock_movimientos.id')
                ->get(['stock_movimientos.lote_id', 'stock_movimientos.cantidad', 'lotes.producto_id'])
                ->map(fn ($m) => (array) $m)
                ->all();

            foreach ($detalles as $detalle) {
                $falta = (int) $detalle->cantidad;

                foreach ($pendientes as &$salida) {
                    if ($falta <= 0) {
                        break;
                    }

                    if ($salida['producto_id'] !== $detalle->producto_id || $salida['cantidad'] <= 0) {
                        continue;
                    }

                    $tomar = min($salida['cantidad'], $falta);

                    DB::table('venta_detalle_lotes')->insert([
                        'venta_detalle_id' => $detalle->id,
                        'lote_id' => $salida['lote_id'],
                        'cantidad' => $tomar,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $salida['cantidad'] -= $tomar;
                    $falta -= $tomar;
                }

                unset($salida);
            }
        }
    }

    /** Se conserva lo reconstruido; la tabla se elimina al deshacer la migración anterior. */
    public function down(): void {}
};
