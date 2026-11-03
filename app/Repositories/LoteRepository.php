<?php

namespace App\Repositories;

use App\Models\Lote;
use App\Repositories\Concerns\EscapaComodinesLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LoteRepository
{
    use EscapaComodinesLike;

    public function porProducto(int $productoId): Collection
    {
        return Lote::where('producto_id', $productoId)->get();
    }

    public function buscarPorVencimiento(int $productoId, ?string $fechaVencimiento): ?Lote
    {
        return Lote::where('producto_id', $productoId)
            ->where('fecha_vencimiento', $fechaVencimiento)
            ->first();
    }

    /**
     * Lotes de un producto en un almacén cuyo número contiene el texto
     * escrito (primero los que empiezan igual). Para el buscador de lotes.
     */
    public function buscarPorNumero(int $productoId, int $almacenId, string $termino, int $limite = 8): Collection
    {
        $termino = trim($termino);

        if ($termino === '') {
            return new Collection;
        }

        $literal = $this->textoLiteral($termino);

        return Lote::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->whereRaw("numero_lote LIKE ? ESCAPE '!'", ['%'.$literal.'%'])
            ->orderByRaw("CASE WHEN numero_lote LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", [$literal.'%'])
            ->orderBy('numero_lote')
            ->limit($limite)
            ->get();
    }

    /** Lotes automáticos «SIN-LOTE» de un producto que todavía tienen unidades. */
    public function conStockSinLote(int $productoId): Collection
    {
        return Lote::where('producto_id', $productoId)
            ->where('numero_lote', Lote::SIN_LOTE)
            ->where('cantidad', '>', 0)
            ->get();
    }

    public function existeNumero(int $productoId, string $numeroLote): bool
    {
        return Lote::where('producto_id', $productoId)->where('numero_lote', $numeroLote)->exists();
    }

    public function buscarEnAlmacen(int $productoId, int $almacenId, string $numeroLote): ?Lote
    {
        return Lote::where('producto_id', $productoId)
            ->where('almacen_id', $almacenId)
            ->where('numero_lote', $numeroLote)
            ->first();
    }

    /**
     * Lotes de los que se puede descontar, en el orden de salida: FEFO (vence antes, sale antes; los
     * lotes sin fecha, al final) si el producto maneja vencimiento, o FIFO (ingresó antes, sale antes).
     * Por defecto se dejan fuera los lotes ya vencidos: un producto vencido no se vende.
     */
    public function ordenadosParaSalida(int $productoId, bool $manejaVencimiento, bool $excluirVencidos = true): Collection
    {
        return $this->ordenarParaSalida(
            Lote::where('producto_id', $productoId)->where('cantidad', '>', 0),
            $manejaVencimiento,
            $excluirVencidos,
        );
    }

    /** Unidades en lotes vencidos (con stock) de un producto, opcionalmente de un solo almacén. */
    public function unidadesVencidas(int $productoId, ?int $almacenId = null): int
    {
        return (int) Lote::where('producto_id', $productoId)
            ->when($almacenId, fn ($q) => $q->where('almacen_id', $almacenId))
            ->conEstado('vencido')
            ->sum('cantidad');
    }

    private function ordenarParaSalida(Builder $query, bool $manejaVencimiento, bool $excluirVencidos): Collection
    {
        if ($excluirVencidos) {
            $query->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>=', today()->toDateString()));
        }

        return $manejaVencimiento
            ? $query->orderByRaw('fecha_vencimiento IS NULL')->orderBy('fecha_vencimiento', 'asc')->orderBy('id')->get()   // FEFO
            : $query->orderBy('fecha_ingreso', 'asc')->orderBy('id')->get();                                                 // FIFO
    }

    /**
     * Control de lotes: paginado por producto y vencimiento, con búsqueda por producto,
     * código, marca o número de lote, y filtro por estado.
     *
     * @param  array{buscar?: ?string, estado?: ?string}  $filtros
     */
    public function historial(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $patron = '%'.$this->textoLiteral($buscar).'%';

        return Lote::query()
            ->select('lotes.*')
            ->join('productos', 'productos.id', '=', 'lotes.producto_id')
            ->with(['producto', 'almacen'])
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw("lotes.numero_lote LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("productos.nombre LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("productos.codigo LIKE ? ESCAPE '!'", [$patron])
                ->orWhereRaw("productos.marca LIKE ? ESCAPE '!'", [$patron])))
            ->conEstado($filtros['estado'] ?? null)
            ->orderBy('productos.nombre')
            ->orderBy('lotes.fecha_vencimiento')
            ->orderBy('lotes.id')
            ->paginate($porPagina);
    }

    /**
     * Tarjetas de Lotes: con stock, por vencer, vencidos (con stock) y agotados.
     *
     * @return array{con_stock: int, por_vencer: int, vencidos: int, agotados: int}
     */
    public function resumen(): array
    {
        return [
            'con_stock' => Lote::where('cantidad', '>', 0)->count(),
            'por_vencer' => Lote::conEstado('por_vencer')->count(),
            'vencidos' => Lote::conEstado('vencido')->count(),
            'agotados' => Lote::conEstado('agotado')->count(),
        ];
    }

    public function create(array $data): Lote
    {
        return Lote::create($data);
    }

    public function decrementar(Lote $lote, int $cantidad): void
    {
        $lote->decrement('cantidad', $cantidad);
    }

    public function incrementar(Lote $lote, int $cantidad): void
    {
        $lote->increment('cantidad', $cantidad);
    }

    public function porVencerEnDias(int $dias): Collection
    {
        return Lote::whereHas('producto', fn ($q) => $q->where('maneja_vencimiento', true))
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', now()->addDays($dias))
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    /**
     * Lotes con stock disponible, desagregados por producto y lote (RF-08),
     * opcionalmente de un solo almacén.
     */
    public function conStock(?int $almacenId = null): Collection
    {
        return Lote::with('producto.categoria', 'almacen')
            ->where('cantidad', '>', 0)
            ->when($almacenId, fn ($q) => $q->where('almacen_id', $almacenId))
            ->get()
            ->sortBy([['producto.nombre', 'asc'], ['fecha_vencimiento', 'asc']])
            ->values();
    }

    /**
     * Stock agrupado por producto y almacén, con las unidades, las que no tienen
     * costo y el valor a costo (cantidad × costo de cada lote). Para valorizar.
     *
     * @return Collection<int, Lote> cada elemento trae producto_id, almacen_id, unidades,
     *                               unidades_sin_costo, valor_costo y las relaciones producto y almacen
     */
    public function resumenParaValorizacion(?int $almacenId = null): Collection
    {
        return Lote::query()
            ->selectRaw('producto_id, almacen_id, SUM(cantidad) as unidades')
            ->selectRaw('SUM(CASE WHEN costo_unitario IS NULL THEN cantidad ELSE 0 END) as unidades_sin_costo')
            ->selectRaw('SUM(CASE WHEN costo_unitario IS NULL THEN 0 ELSE cantidad * costo_unitario END) as valor_costo')
            ->where('cantidad', '>', 0)
            ->when($almacenId, fn ($q) => $q->where('almacen_id', $almacenId))
            ->groupBy('producto_id', 'almacen_id')
            ->with(['producto.categoria', 'almacen'])
            ->get();
    }

    /**
     * Unidades disponibles de un producto agrupadas por almacén.
     *
     * @return Collection<int, Lote> cada elemento trae almacen_id, unidades y la relación almacen
     */
    public function stockPorAlmacen(int $productoId): Collection
    {
        return Lote::with('almacen')
            ->select('almacen_id', DB::raw('SUM(cantidad) as unidades'))
            ->where('producto_id', $productoId)
            ->where('cantidad', '>', 0)
            ->whereNotNull('almacen_id')
            ->groupBy('almacen_id')
            ->orderBy('almacen_id')
            ->get();
    }

    public function contarActivos(): int
    {
        return Lote::where('cantidad', '>', 0)->count();
    }

    /** Igual que {@see ordenadosParaSalida}, solo con los lotes de un almacén. */
    public function ordenadosParaSalidaEnAlmacen(int $productoId, int $almacenId, bool $manejaVencimiento, bool $excluirVencidos = true): Collection
    {
        return $this->ordenarParaSalida(
            Lote::where('producto_id', $productoId)->where('almacen_id', $almacenId)->where('cantidad', '>', 0),
            $manejaVencimiento,
            $excluirVencidos,
        );
    }
}
