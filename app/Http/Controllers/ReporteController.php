<?php

namespace App\Http\Controllers;

use App\Repositories\ProductoRepository;
use App\Repositories\StockMovimientoRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function stockPdf(ProductoRepository $productos): Response
    {
        $pdf = Pdf::loadView('reportes.stock-pdf', [
            'productos' => $productos->conStockActual()->sortBy('nombre')->values(),
        ]);

        return $pdf->stream('reporte-stock.pdf');
    }

    public function stockCsv(ProductoRepository $productos): StreamedResponse
    {
        $filas = $productos->conStockActual()->sortBy('nombre')->values();

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Código', 'Producto', 'Categoría', 'Stock actual', 'Stock mínimo']);

            foreach ($filas as $producto) {
                fputcsv($out, [
                    $producto->codigo,
                    $producto->nombre,
                    $producto->categoria->nombre,
                    (int) ($producto->stock_actual ?? 0),
                    $producto->stock_minimo,
                ]);
            }

            fclose($out);
        }, 'reporte-stock.csv');
    }

    public function movimientosPdf(Request $request, StockMovimientoRepository $movimientos): Response
    {
        $pdf = Pdf::loadView('reportes.movimientos-pdf', [
            'movimientos' => $movimientos->porFiltros(
                $request->query('fecha') ?: null,
                $request->integer('producto_id') ?: null,
                $request->query('tipo') ?: null,
            ),
        ]);

        return $pdf->stream('reporte-movimientos.pdf');
    }

    public function movimientosCsv(Request $request, StockMovimientoRepository $movimientos): StreamedResponse
    {
        $filas = $movimientos->porFiltros(
            $request->query('fecha') ?: null,
            $request->integer('producto_id') ?: null,
            $request->query('tipo') ?: null,
        );

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Fecha', 'Producto', 'Lote', 'Tipo', 'Cantidad', 'Motivo']);

            foreach ($filas as $movimiento) {
                fputcsv($out, [
                    $movimiento->fecha->format('d/m/Y H:i'),
                    $movimiento->lote->producto->nombre,
                    $movimiento->lote->numero_lote,
                    $movimiento->tipo,
                    $movimiento->cantidad,
                    $movimiento->motivo,
                ]);
            }

            fclose($out);
        }, 'reporte-movimientos.csv');
    }
}
