<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La foto vive en su propia tabla (y en base64) para que las consultas
     * normales de productos no la arrastren, y para que sobreviva a los
     * despliegues en hostings cuyo disco es efímero.
     */
    public function up(): void
    {
        Schema::create('producto_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->unique()->constrained('productos')->cascadeOnDelete();
            $table->string('mime', 50);
            $table->mediumText('contenido');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagenes');
    }
};
