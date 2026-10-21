<?php

namespace App\Repositories;

use App\Models\AuditoriaCambio;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditoriaRepository
{
    /**
     * Historial paginado, del cambio más reciente al más antiguo.
     *
     * @param  array{entidad?: ?string, user_id?: ?int, desde?: ?string, hasta?: ?string}  $filtros
     */
    public function historial(array $filtros = [], int $porPagina = 20): LengthAwarePaginator
    {
        return AuditoriaCambio::with(['usuario', 'auditable'])
            ->when($filtros['entidad'] ?? null, fn ($q, $entidad) => $q->where('auditable_type', $entidad))
            ->when($filtros['user_id'] ?? null, fn ($q, $usuarioId) => $q->where('user_id', $usuarioId))
            ->when($this->fechaValida($filtros['desde'] ?? null), fn ($q, $desde) => $q->whereDate('created_at', '>=', $desde))
            ->when($this->fechaValida($filtros['hasta'] ?? null), fn ($q, $hasta) => $q->whereDate('created_at', '<=', $hasta))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    /** @return array<int, string> */
    public function entidadesRegistradas(): array
    {
        return AuditoriaCambio::query()->distinct()->pluck('auditable_type')->all();
    }

    /** Usuarios que hicieron al menos un cambio, para el filtro. */
    public function usuariosQueCambiaron(): Collection
    {
        return User::whereIn('id', AuditoriaCambio::query()->select('user_id'))->orderBy('name')->get();
    }

    private function fechaValida(?string $fecha): ?string
    {
        return $fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null;
    }
}
