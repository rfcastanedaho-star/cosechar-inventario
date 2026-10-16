<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tercera forma normal: el producto de una línea de compra ya lo
     * determina el lote (lotes.producto_id), así que compra_detalles
     * no necesita repetirlo.
     */
    public function up(): void
    {
        Schema::table('compra_detalles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
        });
    }

    public function down(): void
    {
        Schema::table('compra_detalles', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('compra_id')->constrained('productos');
        });

        DB::table('compra_detalles')->update([
            'producto_id' => DB::table('lotes')
                ->whereColumn('lotes.id', 'compra_detalles.lote_id')
                ->select('lotes.producto_id'),
        ]);
    }
};
