<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['compras', 'ventas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->timestamp('anulada_at')->nullable();
                $table->foreignId('anulada_por')->nullable()->constrained('users');
                $table->string('motivo_anulacion')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['compras', 'ventas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('anulada_por');
                $table->dropColumn(['anulada_at', 'motivo_anulacion']);
            });
        }
    }
};
