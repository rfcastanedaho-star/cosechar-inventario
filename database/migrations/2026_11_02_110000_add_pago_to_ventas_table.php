<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cómo pagó el cliente. Las ventas anteriores quedan sin dato (null). En efectivo se guarda lo que
     * entregó el cliente y el vuelto; con tarjeta o transferencia se cobra el total exacto.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('metodo_pago', 20)->nullable()->after('total');
            $table->decimal('monto_recibido', 12, 2)->nullable()->after('metodo_pago');
            $table->decimal('vuelto', 12, 2)->nullable()->after('monto_recibido');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['metodo_pago', 'monto_recibido', 'vuelto']);
        });
    }
};
