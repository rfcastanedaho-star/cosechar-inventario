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

    protected $fillable = ['nombre', 'direccion', 'encargado'];

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
