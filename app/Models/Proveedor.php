<?php

namespace App\Models;

use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'ruc', 'telefono', 'direccion', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
