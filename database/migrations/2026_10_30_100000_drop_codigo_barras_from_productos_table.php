<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** El código de barras no se usa: el producto se identifica por su código, nombre y marca. */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique(['codigo_barras']);
            $table->dropColumn('codigo_barras');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_barras', 50)->nullable()->unique()->after('codigo');
        });
    }
};
