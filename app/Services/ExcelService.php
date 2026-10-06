<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelService
{
    /**
     * Descarga un archivo .xlsx real (con encabezado en negrita) generado
     * en streaming, sin cargar todo el reporte en memoria.
     *
     * @param  array<int, string>  $encabezados
     * @param  iterable<int, array<int, string|int|float|null>>  $filas
     */
    public function descargar(string $archivo, array $encabezados, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $estiloEncabezado = (new Style)
                ->withFontBold(true)
                ->withFontColor(Color::WHITE)
                ->withBackgroundColor(Color::rgb(30, 61, 47));

            $writer->addRow(Row::fromValuesWithStyle($encabezados, $estiloEncabezado));

            foreach ($filas as $fila) {
                $writer->addRow(Row::fromValues($fila));
            }

            $writer->close();
        }, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
