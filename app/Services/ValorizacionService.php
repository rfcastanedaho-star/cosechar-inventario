<?php

namespace App\Services;

use App\Repositories\LoteRepository;
use App\Support\ValorizacionFila;
use Illuminate\Support\Collection;

/**
 * Cuánto vale el stock: a costo de compra (por lote) y a precio de venta.
 * Los lotes sin costo registrado no inventan valor: se cuentan aparte para
 * que el reporte avise que el costo total está incompleto.
 */
class ValorizacionService
{
    public function __construct(private LoteRepository $lotes) {}

    /**
     * @return array{
     *     filas: Collection<int, ValorizacionFila>,
     *     totales: array{productos: int, unidades: int, unidadesSinCosto: int, valorCosto: float, valorVenta: float, utilidad: float, completo: bool}
     * }
     */
    public function calcular(?int $almacenId = null): array
    {
        $filas = $this->lotes->resumenParaValorizacion($almacenId)
            ->map(fn ($grupo) => $this->fila($grupo))
            ->sortBy([
                fn (ValorizacionFila $a, ValorizacionFila $b) => strcasecmp($a->producto->nombre, $b->producto->nombre),
                fn (ValorizacionFila $a, ValorizacionFila $b) => strcasecmp($a->almacen?->nombre ?? '', $b->almacen?->nombre ?? ''),
            ])
            ->values();

        $sinCosto = (int) $filas->sum('unidadesSinCosto');

        return [
            'filas' => $filas,
            'totales' => [
                'productos' => $filas->pluck('producto.id')->unique()->count(),
                'unidades' => (int) $filas->sum('unidades'),
                'unidadesSinCosto' => $sinCosto,
                'valorCosto' => round($filas->sum('valorCosto'), 2),
                'valorVenta' => round($filas->sum('valorVenta'), 2),
                'utilidad' => round($filas->sum('utilidad'), 2),
                'completo' => $sinCosto === 0,
            ],
        ];
    }

    private function fila(object $grupo): ValorizacionFila
    {
        $unidades = (int) $grupo->unidades;
        $sinCosto = (int) $grupo->unidades_sin_costo;
        $conCosto = $unidades - $sinCosto;
        $valorCosto = round((float) $grupo->valor_costo, 2);
        $precio = (float) $grupo->producto->precio;

        return new ValorizacionFila(
            producto: $grupo->producto,
            almacen: $grupo->almacen,
            unidades: $unidades,
            unidadesSinCosto: $sinCosto,
            costoPromedio: $conCosto > 0 ? round($valorCosto / $conCosto, 2) : null,
            valorCosto: $valorCosto,
            valorVenta: round($unidades * $precio, 2),
            utilidad: round($conCosto * $precio - $valorCosto, 2),
        );
    }
}
