<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes');
            $table->enum('tipo', ['entrada', 'salida']);
            $table->integer('cantidad');
            $table->dateTime('fecha');
            $table->foreignId('responsable_id')->constrained('users');
            $table->enum('motivo', [
                'venta', 'merma', 'producto_danado', 'ajuste_inventario', 'transferencia',
            ])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movimientos');
    }
};
