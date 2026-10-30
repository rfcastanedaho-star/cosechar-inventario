<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\ProveedorRepository;
use Illuminate\Support\Facades\DB;

class CompraService
{
    public function __construct(
        private LoteRepository $lotes,
        private StockMovimientoService $movimientos,
        private ProveedorRepository $proveedores = new ProveedorRepository,
    ) {}

    /**
     * Registra una compra: crea la cabecera, un Lote por cada línea (en el
     * almacén de destino) y el movimiento de entrada correspondiente,
     * reutilizando la misma lógica de stock que Movimientos > Entrada.
     *
     * El proveedor viene elegido (proveedor_id) o se escribe (proveedor_nombre, y opcionalmente
     * proveedor_ruc, proveedor_telefono, proveedor_correo y proveedor_direccion): en ese caso se registra, o se reutiliza si ya existe,
     * dentro de la misma transacción que la compra.
     *
     * @param  array{proveedor_id?: ?int, proveedor_nombre?: ?string, proveedor_ruc?: ?string, proveedor_telefono?: ?string, proveedor_correo?: ?string, proveedor_direccion?: ?string, almacen_id: int, fecha: string, numero_comprobante: ?string}  $cabecera
     * @param  array<int, array{producto_id: int, numero_lote: string, fecha_vencimiento: ?string, cantidad: int, costo_unitario: float|string}>  $lineas
     */
    public function registrar(array $cabecera, array $lineas, User $responsable): Compra
    {
        return DB::transaction(function () use ($cabecera, $lineas, $responsable) {
            $proveedorId = $cabecera['proveedor_id'] ?? null;

            if ($proveedorId === null) {
                $proveedorId = $this->proveedores->registrarOReutilizar(
                    $cabecera['proveedor_nombre'],
                    $cabecera['proveedor_ruc'] ?? null,
                    $cabecera['proveedor_telefono'] ?? null,
                    $cabecera['proveedor_correo'] ?? null,
                    $cabecera['proveedor_direccion'] ?? null,
                )->id;
            }

            $compra = Compra::create([
                'proveedor_id' => $proveedorId,
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
                    'costo_unitario' => $linea['costo_unitario'],
                ]);

                $this->movimientos->registrarEntrada($lote, (int) $linea['cantidad'], $responsable, 'compra');

                $compra->detalles()->create([
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
