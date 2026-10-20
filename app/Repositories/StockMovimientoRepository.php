<?php

namespace App\Repositories;

use App\Models\StockMovimiento;
use App\Repositories\Concerns\EscapaComodinesLike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class StockMovimientoRepository
{
    use EscapaComodinesLike;

    public function create(array $data): StockMovimiento
    {
        return StockMovimiento::create($data);
    }

    public function porFiltros(?string $fecha, ?int $productoId, ?string $tipo): Collection
    {
        return StockMovimiento::with('lote.producto')
            ->when($fecha, fn ($q) => $q->whereDate('fecha', $fecha))
            ->when($productoId, fn ($q) => $q->whereHas('lote', fn ($q2) => $q2->where('producto_id', $productoId)))
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('fecha', 'desc')
            ->get();
    }

    /**
     * Historial paginado, del más reciente al más antiguo.
     *
     * @param  array{buscar?: ?string, tipo?: ?string, almacen_id?: ?int, motivo?: ?string, desde?: ?string, hasta?: ?string}  $filtros
     */
    public function historial(array $filtros = [], int $porPagina = 20): LengthAwarePaginator
    {
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $literal = $this->textoLiteral($buscar);
        $motivo = $filtros['motivo'] ?? null;

        return StockMovimiento::with(['lote.producto', 'lote.almacen', 'responsable'])
            ->when($buscar !== '', fn ($q) => $q->whereHas('lote', fn ($lote) => $lote
                ->whereRaw("numero_lote LIKE ? ESCAPE '!'", ['%'.$literal.'%'])
                ->orWhereHas('producto', fn ($producto) => $producto
                    ->whereRaw("nombre LIKE ? ESCAPE '!'", ['%'.$literal.'%'])
                    ->orWhereRaw("codigo LIKE ? ESCAPE '!'", ['%'.$literal.'%']))))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['almacen_id'] ?? null, fn ($q, $almacenId) => $q->whereHas('lote', fn ($lote) => $lote->where('almacen_id', $almacenId)))
            ->when($motivo, fn ($q) => $motivo === 'compra'
                ? $q->where(fn ($w) => $w->where('motivo', 'compra')->orWhere(fn ($antiguas) => $antiguas->whereNull('motivo')->where('tipo', 'entrada')))
                : $q->where('motivo', $motivo))
            ->when($this->fechaValida($filtros['desde'] ?? null), fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($this->fechaValida($filtros['hasta'] ?? null), fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }

    private function fechaValida(?string $fecha): ?string
    {
        return $fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null;
    }

    public function salidasDeVenta(int $ventaId): Collection
    {
        return StockMovimiento::with('lote')
            ->where('venta_id', $ventaId)
            ->where('tipo', 'salida')
            ->get();
    }

    public function contarHoy(): int
    {
        return StockMovimiento::whereDate('fecha', today())->count();
    }

    public function ultimo(): ?StockMovimiento
    {
        return StockMovimiento::latest('fecha')->first();
    }
}
