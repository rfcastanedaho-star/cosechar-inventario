<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->foreignId('venta_id')->nullable()->constrained('ventas');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venta_id');
        });
    }
};
