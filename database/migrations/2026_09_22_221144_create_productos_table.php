<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->enum('unidad_medida', ['saco', 'kg', 'litro', 'galon', 'unidad']);
            $table->integer('stock_minimo')->default(0);
            $table->decimal('precio', 10, 2);
            $table->boolean('maneja_vencimiento')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};