<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se eliminó la función de códigos QR por lote (exponía datos internos
     * a cualquier persona que escaneara la etiqueta), así que la columna
     * con la URL del QR ya no se usa.
     */
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('codigo_qr');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->string('codigo_qr')->nullable();
        });
    }
};
