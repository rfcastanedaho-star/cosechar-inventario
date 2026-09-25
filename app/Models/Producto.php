<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    protected $fillable = [
        'codigo', 'nombre', 'categoria_id', 'unidad_medida',
        'stock_minimo', 'precio', 'maneja_vencimiento',
    ];

    protected $casts = [
        'maneja_vencimiento' => 'boolean',
        'precio' => 'decimal:2',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
