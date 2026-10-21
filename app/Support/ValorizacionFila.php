<?php

namespace App\Support;

use App\Models\Almacen;
use App\Models\Producto;

/** Una línea del reporte de valorización: un producto en un almacén. */
final readonly class ValorizacionFila
{
    public function __construct(
        public Producto $producto,
        public ?Almacen $almacen,
        public int $unidades,
        public int $unidadesSinCosto,
        public ?float $costoPromedio,
        public float $valorCosto,
        public float $valorVenta,
        public float $utilidad,
    ) {}
}
