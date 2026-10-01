<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\User;
use App\Repositories\LoteRepository;
use Illuminate\Support\Facades\DB;

class CompraService
{
    public function __construct(
        private LoteRepository $lotes,
        private StockMovimientoService $movimientos,
    ) {}

    /**
     * Registra una compra: crea la cabecera, un Lote por cada línea (en el
     * almacén de destino) y el movimiento de entrada correspondiente,
     * reutilizando la misma lógica de stock que Movimientos > Entrada.
     *
     * @param  array{proveedor_id: int, almacen_id: int, fecha: string, numero_comprobante: ?string}  $cabecera
     * @param  array<int, array{producto_id: int, numero_lote: string, fecha_vencimiento: ?string, cantidad: int, costo_unitario: float|string}>  $lineas
     */
    public function registrar(array $cabecera, array $lineas, User $responsable): Compra
    {
        return DB::transaction(function () use ($cabecera, $lineas, $responsable) {
            $compra = Compra::create([
                'proveedor_id' => $cabecera['proveedor_id'],
                'almacen_id' => $cabecera['almacen_id'],
                'responsable_id' => $responsable->id,
                'fecha' => $cabecera['fecha'],
                'numero_comprobante' => $cabecera['numero_comprobante'] ?: null,
                'total' => 0,
            ]);

            $total = 0;

            foreach ($lineas as $linea) {
                $lote = $this->lotes->create([
                    'producto_id' => $linea['producto_id'],
                    'almacen_id' => $cabecera['almacen_id'],
                    'numero_lote' => $linea['numero_lote'],
                    'fecha_ingreso' => $cabecera['fecha'],
                    'fecha_vencimiento' => $linea['fecha_vencimiento'] ?: null,
                    'cantidad' => 0,
                ]);

                $this->movimientos->registrarEntrada($lote, (int) $linea['cantidad'], $responsable);

                $compra->detalles()->create([
                    'producto_id' => $linea['producto_id'],
                    'lote_id' => $lote->id,
                    'cantidad' => $linea['cantidad'],
                    'costo_unitario' => $linea['costo_unitario'],
                ]);

                $total += $linea['cantidad'] * $linea['costo_unitario'];
            }

            $compra->update(['total' => $total]);

            return $compra->fresh('detalles.lote');
        });
    }
}
