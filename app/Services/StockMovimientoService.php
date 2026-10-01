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
    public function registrarSalidaEnAlmacen(Producto $producto, int $almacenId, int $cantidad, User $responsable, ?string $motivo = null): Collection
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($producto, $almacenId, $cantidad, $responsable, $motivo) {
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
                ]));

                $restante -= $aDescontar;
            }

            return $movimientosCreados;
        });
    }

    public function registrarEntrada(Lote $lote, int $cantidad, User $responsable): StockMovimiento
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($lote, $cantidad, $responsable) {
            $this->lotes->incrementar($lote, $cantidad);

            return $this->movimientos->create([
                'lote_id' => $lote->id,
                'tipo' => 'entrada',
                'cantidad' => $cantidad,
                'fecha' => now(),
                'responsable_id' => $responsable->id,
                'motivo' => null,
            ]);
        });
    }
}
