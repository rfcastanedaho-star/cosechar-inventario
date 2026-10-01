<?php

namespace App\Models;

use Database\Factories\CompraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    /** @use HasFactory<CompraFactory> */
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = [
        'proveedor_id', 'almacen_id', 'responsable_id', 'fecha', 'numero_comprobante', 'total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
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
        return $this->hasMany(CompraDetalle::class);
    }
}
