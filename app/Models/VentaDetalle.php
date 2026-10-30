<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDetalle extends Model
{
    protected $table = 'venta_detalles';

    protected $fillable = ['venta_id', 'producto_id', 'cantidad', 'precio_unitario', 'por_mayor'];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'por_mayor' => 'boolean',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
