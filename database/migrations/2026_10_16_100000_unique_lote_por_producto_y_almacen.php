<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El número de lote deja de ser único por producto y pasa a serlo por
     * producto y almacén, para que un mismo lote físico pueda existir en
     * dos almacenes (transferencias). Se crea primero el índice nuevo y
     * después se elimina el viejo, porque en MySQL el índice viejo es el
     * que respalda la llave foránea de producto_id.
     */
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->unique(['producto_id', 'almacen_id', 'numero_lote']);
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->dropUnique(['producto_id', 'numero_lote']);
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->unique(['producto_id', 'numero_lote']);
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->dropUnique(['producto_id', 'almacen_id', 'numero_lote']);
        });
    }
};
