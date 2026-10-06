<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaCambio extends Model
{
    protected $table = 'auditoria_cambios';

    protected $fillable = [
        'auditable_type', 'auditable_id', 'user_id', 'campo', 'valor_anterior', 'valor_nuevo',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
