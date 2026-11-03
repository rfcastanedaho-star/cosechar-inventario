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

    /** Métodos de pago que acepta la tienda (sin crédito): valor guardado => texto que ve el usuario. */
    public const METODOS_PAGO = [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
    ];

    public const EFECTIVO = 'efectivo';

    protected $fillable = [
        'cliente_id', 'cliente_nombre', 'cliente_documento', 'almacen_id', 'responsable_id', 'fecha', 'numero_comprobante', 'total',
        'anulada_at', 'anulada_por', 'motivo_anulacion', 'metodo_pago', 'monto_recibido', 'vuelto',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'vuelto' => 'decimal:2',
        'anulada_at' => 'datetime',
    ];

    /** Texto del método de pago; las ventas anteriores a este dato no lo tienen. */
    public function etiquetaMetodoPago(): string
    {
        return self::METODOS_PAGO[$this->metodo_pago] ?? '—';
    }

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
