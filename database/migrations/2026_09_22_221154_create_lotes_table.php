<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('numero_lote', 50);
            $table->date('fecha_ingreso');
            $table->date('fecha_vencimiento')->nullable();
            $table->integer('cantidad')->default(0);
            $table->string('codigo_qr')->nullable();
            $table->timestamps();

            $table->unique(['producto_id', 'numero_lote']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};