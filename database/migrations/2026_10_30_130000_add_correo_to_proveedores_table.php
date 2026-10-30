<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Correo de contacto del proveedor; se pide al registrar la compra. */
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('correo', 150)->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('correo');
        });
    }
};
