<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Lote;
use App\Models\Producto;
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
     * Registra una compra: crea la cabecera, el lote de cada línea (en el almacén de destino, ver
     * {@see loteParaLinea}) y el movimiento de entrada correspondiente.
     *
     * El proveedor viene elegido (proveedor_id) o se escribe (proveedor_nombre, y opcionalmente
     * proveedor_ruc, proveedor_telefono, proveedor_correo y proveedor_direccion): en ese caso se registra, o se reutiliza si ya existe,
     * dentro de la misma transacción que la compra.
     *
     * @param  array{proveedor_id?: ?int, proveedor_nombre?: ?string, proveedor_ruc?: ?string, proveedor_telefono?: ?string, proveedor_correo?: ?string, proveedor_direccion?: ?string, almacen_id: int, fecha: string, numero_comprobante: ?string}  $cabecera
     * @param  array<int, array{producto_id: int, cantidad: int, costo_unitario: float|string}>  $lineas
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

            foreach (array_values($lineas) as $posicion => $linea) {
                $lote = $this->loteParaLinea($compra, $posicion + 1, $cabecera, $linea);

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

    /**
     * La compra no pide número de lote ni vencimiento (eso se define en Productos). Un producto que no
     * usa lotes acumula en su lote automático «SIN-LOTE» (costo promedio ponderado); uno que sí los usa
     * recibe un lote nuevo por compra, «C{compra}-{línea}», al que luego se le completa el vencimiento en Lotes.
     *
     * @param  array{almacen_id: int, fecha: string}  $cabecera
     * @param  array{producto_id: int, cantidad: int, costo_unitario: float|string}  $linea
     */
    private function loteParaLinea(Compra $compra, int $posicion, array $cabecera, array $linea): Lote
    {
        $producto = Producto::findOrFail($linea['producto_id']);

        if ($producto->maneja_vencimiento) {
            return $this->lotes->create([
                'producto_id' => $producto->id,
                'almacen_id' => $cabecera['almacen_id'],
                'numero_lote' => "C{$compra->id}-{$posicion}",
                'fecha_ingreso' => $cabecera['fecha'],
                'fecha_vencimiento' => null,
                'cantidad' => 0,
                'costo_unitario' => $linea['costo_unitario'],
            ]);
        }

        $lote = $this->lotes->buscarEnAlmacen($producto->id, $cabecera['almacen_id'], Lote::SIN_LOTE);

        if ($lote === null) {
            return $this->lotes->create([
                'producto_id' => $producto->id,
                'almacen_id' => $cabecera['almacen_id'],
                'numero_lote' => Lote::SIN_LOTE,
                'fecha_ingreso' => $cabecera['fecha'],
                'fecha_vencimiento' => null,
                'cantidad' => 0,
                'costo_unitario' => $linea['costo_unitario'],
            ]);
        }

        $existencia = max(0, (int) $lote->cantidad);
        $nueva = (int) $linea['cantidad'];
        $costo = $lote->costo_unitario === null || $existencia === 0
            ? (float) $linea['costo_unitario']
            : ($existencia * (float) $lote->costo_unitario + $nueva * (float) $linea['costo_unitario']) / ($existencia + $nueva);

        $lote->update(['costo_unitario' => round($costo, 2)]);

        return $lote;
    }
}
