<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Razones reales para ajustar un lote: producto vencido, error de conteo, devolución, pérdida y otro. */
    public function up(): void
    {
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->enum('motivo', [
                'venta', 'merma', 'producto_danado', 'ajuste_inventario', 'transferencia', 'saldo_inicial', 'compra', 'anulacion',
                'producto_vencido', 'error_conteo', 'devolucion', 'perdida', 'otro',
            ])->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_movimientos')
            ->whereIn('motivo', ['producto_vencido', 'error_conteo', 'devolucion', 'perdida', 'otro'])
            ->update(['motivo' => 'ajuste_inventario']);

        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->enum('motivo', [
                'venta', 'merma', 'producto_danado', 'ajuste_inventario', 'transferencia', 'saldo_inicial', 'compra', 'anulacion',
            ])->nullable()->change();
        });
    }
};
