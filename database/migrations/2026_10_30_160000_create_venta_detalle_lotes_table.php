<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** De qué lotes salió cada línea de una venta (una línea puede salir de varios lotes). */
    public function up(): void
    {
        Schema::create('venta_detalle_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('lotes');
            $table->integer('cantidad');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalle_lotes');
    }
};
