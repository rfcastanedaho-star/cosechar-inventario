<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las entradas manuales ahora llevan motivo, y "saldo_inicial" no estaba
     * en el ENUM original de stock_movimientos.motivo.
     */
    public function up(): void
    {
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->enum('motivo', [
                'venta', 'merma', 'producto_danado', 'ajuste_inventario', 'transferencia', 'saldo_inicial',
            ])->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_movimientos')
            ->where('motivo', 'saldo_inicial')
            ->update(['motivo' => 'ajuste_inventario']);

        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->enum('motivo', [
                'venta', 'merma', 'producto_danado', 'ajuste_inventario', 'transferencia',
            ])->nullable()->change();
        });
    }
};
