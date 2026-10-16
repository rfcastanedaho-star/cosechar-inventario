<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class CompraDetalle extends Model
{
    protected $table = 'compra_detalles';

    protected $fillable = ['compra_id', 'lote_id', 'cantidad', 'costo_unitario'];

    protected $casts = [
        'costo_unitario' => 'decimal:2',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto(): HasOneThrough
    {
        return $this->hasOneThrough(Producto::class, Lote::class, 'id', 'id', 'lote_id', 'producto_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
