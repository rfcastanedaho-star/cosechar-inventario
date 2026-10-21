<?php

namespace App\Models;

use Database\Factories\VentaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    /** @use HasFactory<VentaFactory> */
    use HasFactory;

    protected $table = 'ventas';

    protected $fillable = [
        'cliente_id', 'cliente_nombre', 'cliente_documento', 'almacen_id', 'responsable_id', 'fecha', 'numero_comprobante', 'total',
        'anulada_at', 'anulada_por', 'motivo_anulacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2',
        'anulada_at' => 'datetime',
    ];

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    /** Cliente registrado al que se vendió; null si se vendió a alguien no registrado. */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }
}
