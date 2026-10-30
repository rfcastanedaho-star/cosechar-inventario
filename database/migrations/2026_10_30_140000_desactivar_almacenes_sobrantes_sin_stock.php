<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El negocio trabaja con un solo almacén: los almacenes de prueba que quedaron activos y sin stock
     * se desactivan (no se borran, para conservar su historial) y así el sistema deja de preguntar cuál usar.
     * Siempre queda al menos uno activo: los que tienen stock, o el más antiguo si ninguno tiene.
     */
    public function up(): void
    {
        $activos = DB::table('almacenes')->where('activo', true)->orderBy('id')->pluck('id');

        if ($activos->count() < 2) {
            return;
        }

        $conStock = DB::table('lotes')
            ->whereIn('almacen_id', $activos)
            ->where('cantidad', '>', 0)
            ->distinct()
            ->pluck('almacen_id');

        $conservar = $conStock->isEmpty() ? collect([$activos->first()]) : $conStock;

        DB::table('almacenes')
            ->whereIn('id', $activos->diff($conservar)->all())
            ->update(['activo' => false]);
    }

    /** Los almacenes desactivados se pueden volver a activar a mano; no se adivina cuáles eran. */
    public function down(): void {}
};
