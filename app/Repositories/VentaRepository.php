<?php

namespace App\Repositories;

use App\Models\Venta;
use App\Repositories\Concerns\EscapaComodinesLike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VentaRepository
{
    use EscapaComodinesLike;

    public function all(): Collection
    {
        return Venta::with(['almacen', 'detalles.producto', 'anuladaPor'])
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * Historial paginado, de la venta más reciente a la más antigua.
     *
     * @param  array{buscar?: ?string, estado?: ?string, almacen_id?: ?int, desde?: ?string, hasta?: ?string}  $filtros
     */
    public function historial(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $patron = '%'.$this->textoLiteral($buscar).'%';

        return Venta::with(['almacen', 'detalles.producto', 'anuladaPor'])
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw("cliente_nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("cliente_documento LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("numero_comprobante LIKE ? ESCAPE '!'", [$patron])
                ->orWhereHas('detalles.producto', fn ($p) => $p
                    ->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron])
                    ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$patron]))))
            ->when(($filtros['estado'] ?? null) === 'vigente', fn ($q) => $q->whereNull('anulada_at'))
            ->when(($filtros['estado'] ?? null) === 'anulada', fn ($q) => $q->whereNotNull('anulada_at'))
            ->when($filtros['almacen_id'] ?? null, fn ($q, $almacenId) => $q->where('almacen_id', $almacenId))
            ->when($this->fechaValida($filtros['desde'] ?? null), fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($this->fechaValida($filtros['hasta'] ?? null), fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    public function porFiltros(?string $desde, ?string $hasta, ?int $almacenId): Collection
    {
        return Venta::with(['almacen'])
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->when($almacenId, fn ($q) => $q->where('almacen_id', $almacenId))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{total: float, cantidad: int}
     */
    public function resumenDelMes(): array
    {
        $vigentes = Venta::whereNull('anulada_at')
            ->whereYear('fecha', now()->year)
            ->whereMonth('fecha', now()->month);

        return ['total' => (float) $vigentes->sum('total'), 'cantidad' => $vigentes->count()];
    }

    private function fechaValida(?string $fecha): ?string
    {
        return $fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null;
    }
}
