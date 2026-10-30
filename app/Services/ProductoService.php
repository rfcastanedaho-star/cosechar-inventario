<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\ProductoRepository;
use Illuminate\Support\Facades\DB;

class ProductoService
{
    /** Número del lote que recibe el stock con el que nace el producto. */
    public const LOTE_INICIAL = 'INICIAL';

    public function __construct(
        private ProductoRepository $productos,
        private LoteRepository $lotes,
        private StockMovimientoService $movimientos,
    ) {}

    /**
     * Registra el producto y, si ya hay mercadería, su stock inicial: un lote «INICIAL» en el
     * almacén elegido, al precio de costo del producto, con su movimiento de entrada
     * (saldo inicial). Todo en una transacción: o queda el producto con su stock, o nada.
     *
     * @param  array<string, mixed>  $datos
     * @param  array{cantidad: int, almacen_id: int, fecha_vencimiento: ?string}|null  $stockInicial
     */
    public function registrar(array $datos, ?array $stockInicial, User $responsable): Producto
    {
        return DB::transaction(function () use ($datos, $stockInicial, $responsable) {
            $producto = $this->productos->create($datos);

            if ($stockInicial !== null && $stockInicial['cantidad'] > 0) {
                $lote = $this->lotes->create([
                    'producto_id' => $producto->id,
                    'almacen_id' => $stockInicial['almacen_id'],
                    'numero_lote' => self::LOTE_INICIAL,
                    'fecha_ingreso' => now()->toDateString(),
                    'fecha_vencimiento' => $stockInicial['fecha_vencimiento'] ?: null,
                    'cantidad' => 0,
                    'costo_unitario' => $producto->precio_costo,
                ]);

                $this->movimientos->registrarEntrada($lote, $stockInicial['cantidad'], $responsable, 'saldo_inicial');
            }

            return $producto;
        });
    }
}
