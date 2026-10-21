<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'documento', 'telefono', 'direccion', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** Un cliente nuevo nace activo, también antes de guardarse. */
    protected $attributes = ['activo' => true];

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
