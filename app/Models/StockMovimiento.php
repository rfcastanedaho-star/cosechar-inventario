<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovimiento extends Model
{
    protected $fillable = [
        'lote_id', 'tipo', 'cantidad', 'fecha', 'responsable_id', 'motivo', 'venta_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
