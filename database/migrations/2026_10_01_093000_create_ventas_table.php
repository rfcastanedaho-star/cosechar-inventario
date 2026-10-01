<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('cliente_nombre', 150);
            $table->string('cliente_documento', 20)->nullable();
            $table->foreignId('almacen_id')->constrained('almacenes');
            $table->foreignId('responsable_id')->constrained('users');
            $table->date('fecha');
            $table->string('numero_comprobante', 50)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
