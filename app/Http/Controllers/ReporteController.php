<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Repositories\StockMovimientoRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function stockPdf(): Response
    {
        $pdf = Pdf::loadView('reportes.stock-pdf', [
            'lotes' => $this->stockPorLote(),
        ]);

        return $pdf->stream('reporte-stock.pdf');
    }

    public function stockCsv(): StreamedResponse
    {
        $filas = $this->stockPorLote();

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Código', 'Producto', 'Categoría', 'Lote', 'Vencimiento', 'Cantidad', 'Stock mínimo del producto']);

            foreach ($filas as $lote) {
                fputcsv($out, [
                    $lote->producto->codigo,
                    $lote->producto->nombre,
                    $lote->producto->categoria->nombre,
                    $lote->numero_lote,
                    $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica',
                    $lote->cantidad,
                    $lote->producto->stock_minimo,
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

    /**
     * Stock actual desagregado por producto y lote (RF-08).
     *
     * @return Collection<int, Lote>
     */
    private function stockPorLote(): Collection
    {
        return Lote::with('producto.categoria')
            ->where('cantidad', '>', 0)
            ->get()
            ->sortBy([['producto.nombre', 'asc'], ['fecha_vencimiento', 'asc']])
            ->values();
    }
}
