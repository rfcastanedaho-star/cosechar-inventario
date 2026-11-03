<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Repositories\ClienteRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VentaService
{
    public function __construct(
        private StockMovimientoService $movimientos,
        private ClienteRepository $clientes = new ClienteRepository,
    ) {}

    /**
     * Registra una venta: por cada línea descuenta stock del almacén de
     * origen (FEFO/FIFO, igual que Movimientos > Salida) y deja la
     * cabecera con el total cobrado al cliente.
     *
     * El método de pago es opcional (la API no lo envía). En efectivo, «monto_recibido» es lo que entregó el cliente:
     * si es menor que el total la venta no se registra, y si falta se toma el pago exacto. Con tarjeta o
     * transferencia se cobra el total exacto. En ambos casos se guarda el vuelto.
     *
     * @param  array{cliente_id?: ?int, cliente_nombre: string, cliente_documento: ?string, almacen_id: int, fecha: string, numero_comprobante: ?string, metodo_pago?: ?string, monto_recibido?: float|string|null}  $cabecera
     * @param  array<int, array{producto_id: int, cantidad: int, precio_unitario: float|string, por_mayor?: bool}>  $lineas
     */
    public function registrar(array $cabecera, array $lineas, User $responsable): Venta
    {
        return DB::transaction(function () use ($cabecera, $lineas, $responsable) {
            $documento = trim((string) ($cabecera['cliente_documento'] ?? ''));
            $clienteId = $cabecera['cliente_id'] ?? null;

            // Quien se vende con documento queda registrado como cliente (o se reutiliza el que ya existe).
            if ($clienteId === null && $documento !== '') {
                $clienteId = $this->clientes->registrarOReutilizar($cabecera['cliente_nombre'], $documento)->id;
            }

            $venta = Venta::create([
                'cliente_id' => $clienteId,
                'cliente_nombre' => $cabecera['cliente_nombre'],
                'cliente_documento' => $documento ?: null,
                'almacen_id' => $cabecera['almacen_id'],
                'responsable_id' => $responsable->id,
                'fecha' => $cabecera['fecha'],
                'numero_comprobante' => $cabecera['numero_comprobante'] ?: null,
                'metodo_pago' => $cabecera['metodo_pago'] ?? null,
                'total' => 0,
            ]);

            $total = 0;

            foreach ($lineas as $linea) {
                $producto = Producto::findOrFail($linea['producto_id']);

                $salidas = $this->movimientos->registrarSalidaEnAlmacen(
                    $producto,
                    $cabecera['almacen_id'],
                    (int) $linea['cantidad'],
                    $responsable,
                    'venta',
                    $venta->id,
                );

                $detalle = $venta->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'por_mayor' => (bool) ($linea['por_mayor'] ?? false),
                ]);

                // Queda anotado de qué lote(s) salió esta línea.
                foreach ($salidas as $salida) {
                    $detalle->lotes()->create(['lote_id' => $salida->lote_id, 'cantidad' => $salida->cantidad]);
                }

                $total += $linea['cantidad'] * $linea['precio_unitario'];
            }

            $venta->update(['total' => $total] + $this->pago($cabecera, (float) $total));

            return $venta->fresh('detalles');
        });
    }

    /**
     * Monto recibido y vuelto según el método de pago. En efectivo el cliente puede entregar más que
     * el total (el resto es el vuelto); entregar menos es un error. Sin método de pago no se guarda nada.
     *
     * @param  array<string, mixed>  $cabecera
     * @return array{monto_recibido?: float, vuelto?: float}
     */
    private function pago(array $cabecera, float $total): array
    {
        $metodo = $cabecera['metodo_pago'] ?? null;

        if ($metodo === null) {
            return [];
        }

        if ($metodo !== Venta::EFECTIVO) {
            return ['monto_recibido' => round($total, 2), 'vuelto' => 0.0];
        }

        $recibido = ($cabecera['monto_recibido'] ?? null) === null || $cabecera['monto_recibido'] === ''
            ? $total
            : (float) $cabecera['monto_recibido'];

        if (round($recibido, 2) < round($total, 2)) {
            throw new InvalidArgumentException('El monto recibido es menor que el total de la venta.');
        }

        return ['monto_recibido' => round($recibido, 2), 'vuelto' => round($recibido - $total, 2)];
    }
}
