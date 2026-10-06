<?php

namespace Tests\Concerns;

use OpenSpout\Reader\XLSX\Reader;

trait LeeExcel
{
    /**
     * Convierte el contenido binario de un .xlsx descargado en un arreglo de filas.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function filasDeExcel(string $contenido): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, $contenido);

        $reader = new Reader;
        $reader->open($ruta);

        $filas = [];
        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = $fila->toArray();
            }
        }

        $reader->close();
        unlink($ruta);

        return $filas;
    }
}
