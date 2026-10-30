<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El precio por mayor deja de ser dato del producto: se decide al vender. La línea de
     * la venta guarda si fue por mayor.
     */
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->boolean('por_mayor')->default(false)->after('precio_unitario');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['precio_mayor', 'cantidad_mayor']);
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_mayor', 10, 2)->nullable()->after('precio');
            $table->unsignedInteger('cantidad_mayor')->nullable()->after('precio_mayor');
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn('por_mayor');
        });
    }
};
