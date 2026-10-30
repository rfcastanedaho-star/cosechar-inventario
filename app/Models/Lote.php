<?php

namespace App\Models;

use Database\Factories\LoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /** Estado guardado => texto que ve el usuario. */
    public const ESTADOS = [
        'vigente' => 'Vigente',
        'por_vencer' => 'Por vencer',
        'vencido' => 'Vencido',
        'agotado' => 'Agotado',
    ];

    /** Un lote está «por vencer» cuando vence dentro de este número de días. */
    public const DIAS_POR_VENCER = 30;

    /** Sin unidades = agotado; con unidades, según su fecha de vencimiento (sin fecha = vigente). */
    protected function estado(): Attribute
    {
        return Attribute::get(function () {
            if ($this->cantidad <= 0) {
                return 'agotado';
            }

            if ($this->fecha_vencimiento === null) {
                return 'vigente';
            }

            $vence = $this->fecha_vencimiento->copy()->startOfDay();

            return match (true) {
                $vence->lt(today()) => 'vencido',
                $vence->lte(today()->addDays(self::DIAS_POR_VENCER)) => 'por_vencer',
                default => 'vigente',
            };
        });
    }

    /** Filtra por estado con los mismos criterios que el atributo `estado`; un valor desconocido no filtra. */
    public function scopeConEstado(Builder $query, ?string $estado): Builder
    {
        $hoy = today()->toDateString();
        $limite = today()->addDays(self::DIAS_POR_VENCER)->toDateString();

        return match ($estado) {
            'agotado' => $query->where('cantidad', '<=', 0),
            'vencido' => $query->where('cantidad', '>', 0)->whereNotNull('fecha_vencimiento')->whereDate('fecha_vencimiento', '<', $hoy),
            'por_vencer' => $query->where('cantidad', '>', 0)->whereDate('fecha_vencimiento', '>=', $hoy)->whereDate('fecha_vencimiento', '<=', $limite),
            'vigente' => $query->where('cantidad', '>', 0)->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>', $limite)),
            default => $query,
        };
    }

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
