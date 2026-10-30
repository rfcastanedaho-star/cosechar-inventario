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

    /**
     * Vendido y ajustes de cada lote. Lo vendido sale solo de las ventas (menos las ventas anuladas);
     * una merma o un producto dañado se cuentan aparte, como ajuste.
     *
     * @param  array<int, int>  $loteIds
     * @return array<int, array{vendido: int, ajustes_menos: int, ajustes_mas: int}> por id de lote
     */
    public function totalesPorLote(array $loteIds): array
    {
        return $this->totales('lote_id', StockMovimiento::whereIn('lote_id', $loteIds), $loteIds);
    }

    /**
     * Lo mismo que {@see totalesPorLote} sumando todos los lotes de cada producto.
     *
     * @param  array<int, int>  $productoIds
     * @return array<int, array{vendido: int, ajustes_menos: int, ajustes_mas: int}> por id de producto
     */
    public function totalesPorProducto(array $productoIds): array
    {
        $consulta = StockMovimiento::join('lotes', 'lotes.id', '=', 'stock_movimientos.lote_id')
            ->whereIn('lotes.producto_id', $productoIds);

        return $this->totales('lotes.producto_id', $consulta, $productoIds);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{vendido: int, ajustes_menos: int, ajustes_mas: int}>
     */
    private function totales(string $agruparPor, $consulta, array $ids): array
    {
        $ajustes = "'".implode("','", StockMovimiento::MOTIVOS_AJUSTE)."'";

        $filas = $consulta
            ->selectRaw("{$agruparPor} as clave")
            ->selectRaw("SUM(CASE WHEN stock_movimientos.tipo = 'salida' AND stock_movimientos.motivo = 'venta' THEN stock_movimientos.cantidad WHEN stock_movimientos.tipo = 'entrada' AND stock_movimientos.motivo = 'anulacion' THEN -stock_movimientos.cantidad ELSE 0 END) as vendido")
            ->selectRaw("SUM(CASE WHEN stock_movimientos.tipo = 'salida' AND stock_movimientos.motivo IN ({$ajustes}) THEN stock_movimientos.cantidad ELSE 0 END) as ajustes_menos")
            ->selectRaw("SUM(CASE WHEN stock_movimientos.tipo = 'entrada' AND stock_movimientos.motivo IN ({$ajustes}) THEN stock_movimientos.cantidad ELSE 0 END) as ajustes_mas")
            ->groupBy($agruparPor)
            ->get()
            ->keyBy('clave');

        $totales = [];

        foreach ($ids as $id) {
            $fila = $filas->get($id);
            $totales[$id] = [
                'vendido' => (int) ($fila->vendido ?? 0),
                'ajustes_menos' => (int) ($fila->ajustes_menos ?? 0),
                'ajustes_mas' => (int) ($fila->ajustes_mas ?? 0),
            ];
        }

        return $totales;
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
