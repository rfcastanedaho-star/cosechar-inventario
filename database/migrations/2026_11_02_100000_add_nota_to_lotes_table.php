<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nota opcional del lote, que se escribe al registrar la compra (por ejemplo «llegó con la caja golpeada»). */
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->string('nota', 255)->nullable()->after('costo_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('nota');
        });
    }
};
