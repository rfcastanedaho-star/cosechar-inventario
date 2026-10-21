<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada lote guarda el costo unitario al que se compró, para poder valorizar
     * el inventario sin recorrer las compras. Los lotes anteriores toman el
     * costo de su línea de compra; los que no vienen de una compra quedan sin costo.
     */
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->decimal('costo_unitario', 10, 2)->nullable()->after('cantidad');
        });

        $this->completarCostos();
    }

    public function completarCostos(): void
    {
        DB::table('lotes')
            ->whereNull('costo_unitario')
            ->update([
                'costo_unitario' => DB::raw('(select costo_unitario from compra_detalles where compra_detalles.lote_id = lotes.id order by compra_detalles.id limit 1)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('costo_unitario');
        });
    }
};
