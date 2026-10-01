<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('producto_id')->constrained('almacenes');
        });

        $principalId = DB::table('almacenes')->insertGetId([
            'nombre' => 'Almacén Principal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lotes')->whereNull('almacen_id')->update(['almacen_id' => $principalId]);
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('almacen_id');
        });
    }
};
