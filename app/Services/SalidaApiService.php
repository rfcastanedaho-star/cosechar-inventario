<?php

namespace App\Services;

use App\Exceptions\AlmacenRequeridoException;
use App\Exceptions\StockInsuficienteException;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use Illuminate\Support\Collection;

/**
 * Salidas de stock pedidas por un sistema externo (el módulo de Ventas) vía API.
 * Siempre se descuenta de un almacén concreto, y si es una venta queda su
 * registro en Inventario igual que las ventas hechas desde la pantalla.
 */
class SalidaApiService
{
    public function __construct(
        private LoteRepository $lotes,
        private StockMovimientoRepository $movimientos,
        private StockMovimientoService $stock,
        private VentaService $ventas,
    ) {}

    /**
     * @param  array{cliente_nombre?: ?string, cliente_documento?: ?string, numero_comprobante?: ?string, precio_unitario?: float|string|null}  $datosVenta
     * @return array{movimientos: Collection, venta: ?Venta, almacen_id: int}
     *
     * @throws AlmacenRequeridoException
     * @throws StockInsuficienteException
     */
    public function registrar(Producto $producto, int $cantidad, User $responsable, ?int $almacenId, string $motivo, array $datosVenta = []): array
    {
        $almacenId ??= $this->resolverAlmacen($producto, $cantidad);

        if ($motivo !== 'venta') {
            return [
                'movimientos' => $this->stock->registrarSalidaEnAlmacen($producto, $almacenId, $cantidad, $responsable, $motivo),
                'venta' => null,
                'almacen_id' => $almacenId,
            ];
        }

        $venta = $this->ventas->registrar(
            [
                'cliente_nombre' => $datosVenta['cliente_nombre'] ?? null ?: 'Cliente no especificado',
                'cliente_documento' => $datosVenta['cliente_documento'] ?? null,
                'almacen_id' => $almacenId,
                'fecha' => now()->toDateString(),
                'numero_comprobante' => $datosVenta['numero_comprobante'] ?? null,
            ],
            [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $datosVenta['precio_unitario'] ?? $producto->precio,
            ]],
            $responsable,
        );

        return [
            'movimientos' => $this->movimientos->salidasDeVenta($venta->id),
            'venta' => $venta,
            'almacen_id' => $almacenId,
        ];
    }

    /**
     * Si todo el stock del producto está en un único almacén no hay duda de
     * dónde sale; si está repartido, el sistema externo debe indicarlo.
     */
    private function resolverAlmacen(Producto $producto, int $cantidad): int
    {
        $grupos = $this->lotes->stockPorAlmacen($producto->id);

        if ($grupos->isEmpty()) {
            throw StockInsuficienteException::paraProducto($producto, $cantidad, 0);
        }

        if ($grupos->count() > 1) {
            throw AlmacenRequeridoException::conStockEnVarios(
                $grupos->map(fn ($grupo) => "{$grupo->almacen->nombre} ({$grupo->unidades})")->all()
            );
        }

        return $grupos->first()->almacen_id;
    }
}
