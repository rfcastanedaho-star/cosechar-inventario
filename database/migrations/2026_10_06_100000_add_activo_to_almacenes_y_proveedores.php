<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('almacenes', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('encargado');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('almacenes', function (Blueprint $table) {
            $table->dropColumn('activo');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
