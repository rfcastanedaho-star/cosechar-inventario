<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\ProductoRepository;
use Illuminate\Support\Facades\DB;

class ProductoService
{
    /** Número del lote en que se convierte el stock existente al activar «Usa lotes». */
    public const LOTE_INICIAL = 'INICIAL';

    public function __construct(
        private ProductoRepository $productos,
        private LoteRepository $lotes,
        private StockMovimientoService $movimientos,
    ) {}

    /**
     * Al activar «Usa lotes» en un producto que ya tiene mercadería: el stock que estaba en el lote
     * automático «SIN-LOTE» pasa a ser el lote «INICIAL» (con el vencimiento, si se conoce). No se
     * mueve stock, así que no hay movimiento: solo el lote cambia de nombre y de fecha.
     */
    public function convertirEnLotes(Producto $producto, ?string $fechaVencimiento): void
    {
        DB::transaction(function () use ($producto, $fechaVencimiento) {
            foreach ($this->lotes->conStockSinLote($producto->id) as $lote) {
                $datos = ['fecha_vencimiento' => $fechaVencimiento];

                if (! $this->lotes->existeNumero($producto->id, self::LOTE_INICIAL)) {
                    $datos['numero_lote'] = self::LOTE_INICIAL;
                }

                $lote->update($datos);
            }
        });
    }

    /**
     * Registra el producto y, si ya hay mercadería, su stock inicial: el lote que se indica (o, si el
     * producto no usa lotes, el lote automático «SIN-LOTE») en el almacén elegido, al precio de costo
     * del producto, con su movimiento de entrada (saldo inicial). Todo en una transacción: o queda el
     * producto con su stock, o nada.
     *
     * @param  array<string, mixed>  $datos
     * @param  array{cantidad: int, almacen_id: int, numero_lote?: ?string, fecha_vencimiento: ?string}|null  $stockInicial
     */
    public function registrar(array $datos, ?array $stockInicial, User $responsable): Producto
    {
        return DB::transaction(function () use ($datos, $stockInicial, $responsable) {
            $producto = $this->productos->create($datos);

            if ($stockInicial !== null && $stockInicial['cantidad'] > 0) {
                $lote = $this->lotes->create([
                    'producto_id' => $producto->id,
                    'almacen_id' => $stockInicial['almacen_id'],
                    'numero_lote' => ($stockInicial['numero_lote'] ?? null) ?: Lote::SIN_LOTE,
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
