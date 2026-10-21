<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditoriaCambio extends Model
{
    protected $table = 'auditoria_cambios';

    protected $fillable = [
        'auditable_type', 'auditable_id', 'user_id', 'campo', 'valor_anterior', 'valor_nuevo',
    ];

    /** El registro que cambió (producto, almacén, proveedor o usuario); null si ya no existe. */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
