<?php

namespace App\Services;

use App\Exceptions\StockInsuficienteException;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockMovimientoService
{
    public function __construct(
        private LoteRepository $lotes,
        private StockMovimientoRepository $movimientos,
    ) {}

    /**
     * Descuenta stock de un producto aplicando FEFO (si maneja vencimiento)
     * o FIFO (si no lo maneja), dividiendo la salida entre varios lotes
     * cuando uno solo no alcanza para cubrir la cantidad solicitada.
     *
     * @return Collection<int, StockMovimiento>
     */
    public function registrarSalida(Producto $producto, int $cantidad, User $responsable, ?string $motivo = null): Collection
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($producto, $cantidad, $responsable, $motivo) {
            $lotesDisponibles = $this->lotes->ordenadosParaSalida($producto->id, $producto->maneja_vencimiento);
            $disponible = $lotesDisponibles->sum('cantidad');

            if ($disponible < $cantidad) {
                throw StockInsuficienteException::paraProducto($producto, $cantidad, $disponible);
            }

            $restante = $cantidad;
            $movimientosCreados = new Collection;

            foreach ($lotesDisponibles as $lote) {
                if ($restante <= 0) {
                    break;
                }

                $aDescontar = min($lote->cantidad, $restante);

                $this->lotes->decrementar($lote, $aDescontar);

                $movimientosCreados->push($this->movimientos->create([
                    'lote_id' => $lote->id,
                    'tipo' => 'salida',
                    'cantidad' => $aDescontar,
                    'fecha' => now(),
                    'responsable_id' => $responsable->id,
                    'motivo' => $motivo,
                ]));

                $restante -= $aDescontar;
            }

            return $movimientosCreados;
        });
    }

    /**
     * Igual que registrarSalida(), pero restringe FEFO/FIFO a los lotes de
     * un almacén específico (usado por Ventas, que vende desde un origen
     * determinado en vez de mezclar stock de todos los almacenes).
     *
     * @return Collection<int, StockMovimiento>
     */
    public function registrarSalidaEnAlmacen(Producto $producto, int $almacenId, int $cantidad, User $responsable, ?string $motivo = null, ?int $ventaId = null): Collection
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($producto, $almacenId, $cantidad, $responsable, $motivo, $ventaId) {
            $lotesDisponibles = $this->lotes->ordenadosParaSalidaEnAlmacen($producto->id, $almacenId, $producto->maneja_vencimiento);
            $disponible = $lotesDisponibles->sum('cantidad');

            if ($disponible < $cantidad) {
                throw StockInsuficienteException::paraProducto($producto, $cantidad, $disponible);
            }

            $restante = $cantidad;
            $movimientosCreados = new Collection;

            foreach ($lotesDisponibles as $lote) {
                if ($restante <= 0) {
                    break;
                }

                $aDescontar = min($lote->cantidad, $restante);

                $this->lotes->decrementar($lote, $aDescontar);

                $movimientosCreados->push($this->movimientos->create([
                    'lote_id' => $lote->id,
                    'tipo' => 'salida',
                    'cantidad' => $aDescontar,
                    'fecha' => now(),
                    'responsable_id' => $responsable->id,
                    'motivo' => $motivo,
                    'venta_id' => $ventaId,
                ]));

                $restante -= $aDescontar;
            }

            return $movimientosCreados;
        });
    }

    public function registrarEntrada(Lote $lote, int $cantidad, User $responsable, ?string $motivo = null): StockMovimiento
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($lote, $cantidad, $responsable, $motivo) {
            $this->lotes->incrementar($lote, $cantidad);

            return $this->movimientos->create([
                'lote_id' => $lote->id,
                'tipo' => 'entrada',
                'cantidad' => $cantidad,
                'fecha' => now(),
                'responsable_id' => $responsable->id,
                'motivo' => $motivo,
            ]);
        });
    }

    /**
     * Mueve stock de un almacén a otro: descuenta del origen con FEFO/FIFO
     * y, por cada lote tocado, suma la misma cantidad en el lote del destino
     * (con el mismo número y fechas; se crea si no existe). Todo en una sola
     * transacción: si falla algo, no cambia nada.
     *
     * @return Collection<int, StockMovimiento> salidas y entradas creadas
     */
    public function transferir(Producto $producto, int $almacenOrigenId, int $almacenDestinoId, int $cantidad, User $responsable): Collection
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        if ($almacenOrigenId === $almacenDestinoId) {
            throw new InvalidArgumentException('El almacén de destino debe ser distinto del de origen.');
        }

        return DB::transaction(function () use ($producto, $almacenOrigenId, $almacenDestinoId, $cantidad, $responsable) {
            $lotesDisponibles = $this->lotes->ordenadosParaSalidaEnAlmacen($producto->id, $almacenOrigenId, $producto->maneja_vencimiento);
            $disponible = $lotesDisponibles->sum('cantidad');

            if ($disponible < $cantidad) {
                throw StockInsuficienteException::paraProducto($producto, $cantidad, $disponible);
            }

            $restante = $cantidad;
            $movimientosCreados = new Collection;

            foreach ($lotesDisponibles as $loteOrigen) {
                if ($restante <= 0) {
                    break;
                }

                $aMover = min($loteOrigen->cantidad, $restante);

                $this->lotes->decrementar($loteOrigen, $aMover);

                $movimientosCreados->push($this->movimientos->create([
                    'lote_id' => $loteOrigen->id,
                    'tipo' => 'salida',
                    'cantidad' => $aMover,
                    'fecha' => now(),
                    'responsable_id' => $responsable->id,
                    'motivo' => 'transferencia',
                ]));

                $loteDestino = $this->lotes->buscarEnAlmacen($producto->id, $almacenDestinoId, $loteOrigen->numero_lote)
                    ?? $this->lotes->create([
                        'producto_id' => $producto->id,
                        'almacen_id' => $almacenDestinoId,
                        'numero_lote' => $loteOrigen->numero_lote,
                        'fecha_ingreso' => $loteOrigen->fecha_ingreso,
                        'fecha_vencimiento' => $loteOrigen->fecha_vencimiento,
                        'cantidad' => 0,
                    ]);

                $this->lotes->incrementar($loteDestino, $aMover);

                $movimientosCreados->push($this->movimientos->create([
                    'lote_id' => $loteDestino->id,
                    'tipo' => 'entrada',
                    'cantidad' => $aMover,
                    'fecha' => now(),
                    'responsable_id' => $responsable->id,
                    'motivo' => 'transferencia',
                ]));

                $restante -= $aMover;
            }

            return $movimientosCreados;
        });
    }
}
