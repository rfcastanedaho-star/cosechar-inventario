<?php

namespace App\Services;

use App\Exceptions\AnulacionNoPermitidaException;
use App\Models\Compra;
use App\Models\User;
use App\Models\Venta;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use Illuminate\Support\Facades\DB;

class AnulacionService
{
    public function __construct(
        private LoteRepository $lotes,
        private StockMovimientoRepository $movimientos,
    ) {}

    /**
     * Anula una compra devolviendo el stock que ingresó. Si parte de ese
     * stock ya salió (por ventas o mermas), no se anula: dejaría el
     * inventario con cantidades negativas.
     */
    public function anularCompra(Compra $compra, User $usuario, string $motivo): Compra
    {
        return DB::transaction(function () use ($compra, $usuario, $motivo) {
            $compra = Compra::with('detalles.lote')->lockForUpdate()->findOrFail($compra->id);

            if ($compra->anulada_at) {
                throw AnulacionNoPermitidaException::yaAnulada();
            }

            foreach ($compra->detalles as $detalle) {
                if ($detalle->lote->cantidad < $detalle->cantidad) {
                    throw AnulacionNoPermitidaException::stockYaUtilizado(
                        $detalle->lote->numero_lote,
                        $detalle->cantidad,
                        $detalle->lote->cantidad,
                    );
                }
            }

            foreach ($compra->detalles as $detalle) {
                $this->lotes->decrementar($detalle->lote, $detalle->cantidad);

                $this->movimientos->create([
                    'lote_id' => $detalle->lote_id,
                    'tipo' => 'salida',
                    'cantidad' => $detalle->cantidad,
                    'fecha' => now(),
                    'responsable_id' => $usuario->id,
                    'motivo' => 'ajuste_inventario',
                ]);
            }

            $this->marcarAnulada($compra, $usuario, $motivo);

            return $compra;
        });
    }

    /**
     * Anula una venta devolviendo cada unidad al mismo lote del que salió.
     */
    public function anularVenta(Venta $venta, User $usuario, string $motivo): Venta
    {
        return DB::transaction(function () use ($venta, $usuario, $motivo) {
            $venta = Venta::lockForUpdate()->findOrFail($venta->id);

            if ($venta->anulada_at) {
                throw AnulacionNoPermitidaException::yaAnulada();
            }

            $salidas = $this->movimientos->salidasDeVenta($venta->id);

            if ($salidas->isEmpty()) {
                throw AnulacionNoPermitidaException::sinTrazabilidad();
            }

            foreach ($salidas as $salida) {
                $this->lotes->incrementar($salida->lote, $salida->cantidad);

                $this->movimientos->create([
                    'lote_id' => $salida->lote_id,
                    'tipo' => 'entrada',
                    'cantidad' => $salida->cantidad,
                    'fecha' => now(),
                    'responsable_id' => $usuario->id,
                    'motivo' => null,
                ]);
            }

            $this->marcarAnulada($venta, $usuario, $motivo);

            return $venta;
        });
    }

    private function marcarAnulada(Compra|Venta $registro, User $usuario, string $motivo): void
    {
        $registro->update([
            'anulada_at' => now(),
            'anulada_por' => $usuario->id,
            'motivo_anulacion' => $motivo,
        ]);
    }
}
