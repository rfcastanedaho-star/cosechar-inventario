<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class VentaService
{
    public function __construct(
        private StockMovimientoService $movimientos,
    ) {}

    /**
     * Registra una venta: por cada línea descuenta stock del almacén de
     * origen (FEFO/FIFO, igual que Movimientos > Salida) y deja la
     * cabecera con el total cobrado al cliente.
     *
     * @param  array{cliente_nombre: string, cliente_documento: ?string, almacen_id: int, fecha: string, numero_comprobante: ?string}  $cabecera
     * @param  array<int, array{producto_id: int, cantidad: int, precio_unitario: float|string}>  $lineas
     */
    public function registrar(array $cabecera, array $lineas, User $responsable): Venta
    {
        return DB::transaction(function () use ($cabecera, $lineas, $responsable) {
            $venta = Venta::create([
                'cliente_nombre' => $cabecera['cliente_nombre'],
                'cliente_documento' => $cabecera['cliente_documento'] ?: null,
                'almacen_id' => $cabecera['almacen_id'],
                'responsable_id' => $responsable->id,
                'fecha' => $cabecera['fecha'],
                'numero_comprobante' => $cabecera['numero_comprobante'] ?: null,
                'total' => 0,
            ]);

            $total = 0;

            foreach ($lineas as $linea) {
                $producto = Producto::findOrFail($linea['producto_id']);

                $this->movimientos->registrarSalidaEnAlmacen(
                    $producto,
                    $cabecera['almacen_id'],
                    (int) $linea['cantidad'],
                    $responsable,
                    'venta',
                );

                $venta->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                ]);

                $total += $linea['cantidad'] * $linea['precio_unitario'];
            }

            $venta->update(['total' => $total]);

            return $venta->fresh('detalles');
        });
    }
}
