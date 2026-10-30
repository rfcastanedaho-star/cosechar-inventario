<?php

namespace App\Models;

use Database\Factories\AlmacenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    /** @use HasFactory<AlmacenFactory> */
    use HasFactory;

    protected $table = 'almacenes';

    protected $fillable = ['nombre', 'direccion', 'encargado', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** El almacén activo cuando es el único: no hay nada que elegir y las pantallas no lo preguntan. */
    public static function unicoActivo(): ?self
    {
        $activos = static::where('activo', true)->limit(2)->get();

        return $activos->count() === 1 ? $activos->first() : null;
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
