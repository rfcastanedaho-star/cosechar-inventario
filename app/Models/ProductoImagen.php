<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoImagen extends Model
{
    protected $table = 'producto_imagenes';

    protected $fillable = ['producto_id', 'mime', 'contenido'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
