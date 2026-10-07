<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Entre la creación de Almacenes y la obligatoriedad del almacén en
     * Movimientos pudieron nacer lotes sin almacén. Se asignan al principal
     * para que la salida por almacén nunca los deje invisibles.
     */
    public function up(): void
    {
        $principal = DB::table('almacenes')->where('nombre', 'Almacén Principal')->value('id')
            ?? DB::table('almacenes')->min('id');

        if ($principal) {
            DB::table('lotes')->whereNull('almacen_id')->update(['almacen_id' => $principal]);
        }
    }

    public function down(): void
    {
        // Sin reversa: no se puede saber cuáles lotes estaban sin almacén.
    }
};
