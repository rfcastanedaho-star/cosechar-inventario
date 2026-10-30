<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovimiento extends Model
{
    /** Motivo guardado => texto que ve el usuario. */
    public const MOTIVOS = [
        'compra' => 'Compra',
        'venta' => 'Venta',
        'merma' => 'Merma',
        'producto_danado' => 'Producto dañado',
        'ajuste_inventario' => 'Ajuste por conteo',
        'transferencia' => 'Transferencia',
        'anulacion' => 'Anulación',
        'saldo_inicial' => 'Saldo inicial',
        'producto_vencido' => 'Producto vencido',
        'error_conteo' => 'Error de conteo',
        'devolucion' => 'Devolución',
        'perdida' => 'Pérdida',
        'otro' => 'Otro',
    ];

    /**
     * Razones que elige el usuario al ajustar un lote. Compra, venta, anulación y saldo inicial
     * las pone solo el sistema; "ajuste_inventario" y "transferencia" quedan solo para el historial antiguo.
     */
    public const MOTIVOS_REDUCIR = ['merma', 'producto_danado', 'producto_vencido', 'error_conteo', 'devolucion', 'perdida', 'otro'];

    public const MOTIVOS_AUMENTAR = ['error_conteo', 'devolucion', 'otro'];

    protected $fillable = [
        'lote_id', 'tipo', 'cantidad', 'fecha', 'responsable_id', 'motivo', 'venta_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    /**
     * Las entradas antiguas de compra se guardaron sin motivo, por eso una
     * entrada sin motivo se muestra como compra; una salida sin motivo, como guion.
     */
    protected function etiquetaMotivo(): Attribute
    {
        return Attribute::get(fn () => self::MOTIVOS[$this->motivo ?? ''] ?? ($this->tipo === 'entrada' && $this->motivo === null ? 'Compra' : '—'));
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
