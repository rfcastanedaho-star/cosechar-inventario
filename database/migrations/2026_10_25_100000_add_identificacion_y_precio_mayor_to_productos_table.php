<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos que ayudan a identificar el producto en el mostrador (código de barras,
     * marca, presentación) y el precio por mayor con la cantidad mínima que lo activa.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_barras', 50)->nullable()->unique()->after('codigo');
            $table->string('marca', 100)->nullable()->after('nombre');
            $table->string('presentacion', 100)->nullable()->after('marca');
            $table->decimal('precio_mayor', 10, 2)->nullable()->after('precio');
            $table->unsignedInteger('cantidad_mayor')->nullable()->after('precio_mayor');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique(['codigo_barras']);
            $table->dropColumn(['codigo_barras', 'marca', 'presentacion', 'precio_mayor', 'cantidad_mayor']);
        });
    }
};
