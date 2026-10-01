<?php

namespace App\Models;

use Database\Factories\LoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lote extends Model
{
    /** @use HasFactory<LoteFactory> */
    use HasFactory;

    protected $fillable = [
        'producto_id', 'almacen_id', 'numero_lote', 'fecha_ingreso',
        'fecha_vencimiento', 'cantidad', 'codigo_qr',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(StockMovimiento::class);
    }
}
