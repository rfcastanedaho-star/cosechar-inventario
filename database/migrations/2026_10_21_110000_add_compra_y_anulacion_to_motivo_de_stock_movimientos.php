<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Para que el historial diga el origen real de cada movimiento: las
     * entradas de compra y las anulaciones ya no se confunden con un
     * ajuste por conteo o con un movimiento sin motivo.
     */
    public function up(): void
    {
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->enum('motivo', [
                'venta', 'merma', 'producto_danado', 'ajuste_inventario', 'transferencia', 'saldo_inicial', 'compra', 'anulacion',
            ])->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_movimientos')->where('motivo', 'compra')->update(['motivo' => null]);
        DB::table('stock_movimientos')->where('motivo', 'anulacion')->update(['motivo' => 'ajuste_inventario']);

        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->enum('motivo', [
                'venta', 'merma', 'producto_danado', 'ajuste_inventario', 'transferencia', 'saldo_inicial',
            ])->nullable()->change();
        });
    }
};
