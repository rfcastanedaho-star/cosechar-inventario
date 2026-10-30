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
        'fecha_vencimiento', 'cantidad', 'costo_unitario',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_vencimiento' => 'date',
        'costo_unitario' => 'decimal:2',
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

    /** Líneas de venta que consumieron unidades de este lote. */
    public function consumosEnVentas(): HasMany
    {
        return $this->hasMany(VentaDetalleLote::class);
    }
}
