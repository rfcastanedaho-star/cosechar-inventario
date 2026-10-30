<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Unidades de una línea de venta que salieron de un lote concreto. */
class VentaDetalleLote extends Model
{
    protected $table = 'venta_detalle_lotes';

    protected $fillable = ['venta_detalle_id', 'lote_id', 'cantidad'];

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'venta_detalle_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
