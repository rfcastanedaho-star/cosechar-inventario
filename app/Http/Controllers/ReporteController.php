<?php

namespace App\Http\Controllers;

use App\Repositories\CompraRepository;
use App\Repositories\LoteRepository;
use App\Repositories\StockMovimientoRepository;
use App\Repositories\VentaRepository;
use App\Services\ExcelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function __construct(private ExcelService $excel) {}

    public function stockPdf(Request $request, LoteRepository $lotes): Response
    {
        $pdf = Pdf::loadView('reportes.stock-pdf', [
            'lotes' => $lotes->conStock($request->integer('almacen_id') ?: null),
        ]);

        return $pdf->stream('reporte-stock.pdf');
    }

    public function stockExcel(Request $request, LoteRepository $lotes): StreamedResponse
    {
        $filas = $lotes->conStock($request->integer('almacen_id') ?: null)->map(fn ($lote) => [
            $lote->producto->codigo,
            $lote->producto->nombre,
            $lote->producto->categoria->nombre,
            $lote->almacen?->nombre ?? 'Sin almacén',
            $lote->numero_lote,
            $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica',
            $lote->cantidad,
            $lote->producto->stock_minimo,
        ]);

        return $this->excel->descargar(
            'reporte-stock.xlsx',
            ['Código', 'Producto', 'Categoría', 'Almacén', 'Lote', 'Vencimiento', 'Cantidad', 'Stock mínimo del producto'],
            $filas,
        );
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

    public function movimientosExcel(Request $request, StockMovimientoRepository $movimientos): StreamedResponse
    {
        $filas = $movimientos->porFiltros(
            $request->query('fecha') ?: null,
            $request->integer('producto_id') ?: null,
            $request->query('tipo') ?: null,
        )->map(fn ($movimiento) => [
            $movimiento->fecha->format('d/m/Y H:i'),
            $movimiento->lote->producto->nombre,
            $movimiento->lote->numero_lote,
            $movimiento->tipo,
            $movimiento->cantidad,
            $movimiento->motivo,
        ]);

        return $this->excel->descargar(
            'reporte-movimientos.xlsx',
            ['Fecha', 'Producto', 'Lote', 'Tipo', 'Cantidad', 'Motivo'],
            $filas,
        );
    }

    public function comprasPdf(Request $request, CompraRepository $compras): Response
    {
        $filas = $this->comprasFiltradas($request, $compras);

        return Pdf::loadView('reportes.compras-pdf', [
            'compras' => $filas,
            'total' => $filas->whereNull('anulada_at')->sum('total'),
            'filtros' => $this->textoFiltros($request),
        ])->stream('reporte-compras.pdf');
    }

    public function comprasExcel(Request $request, CompraRepository $compras): StreamedResponse
    {
        $filas = $this->comprasFiltradas($request, $compras)->map(fn ($compra) => [
            $compra->fecha->format('d/m/Y'),
            $compra->proveedor->nombre,
            $compra->almacen->nombre,
            $compra->numero_comprobante,
            (float) $compra->total,
            $compra->anulada_at ? 'Anulada' : 'Vigente',
        ]);

        return $this->excel->descargar(
            'reporte-compras.xlsx',
            ['Fecha', 'Proveedor', 'Almacén', 'Comprobante', 'Total (S/)', 'Estado'],
            $filas,
        );
    }

    public function ventasPdf(Request $request, VentaRepository $ventas): Response
    {
        $filas = $this->ventasFiltradas($request, $ventas);

        return Pdf::loadView('reportes.ventas-pdf', [
            'ventas' => $filas,
            'total' => $filas->whereNull('anulada_at')->sum('total'),
            'filtros' => $this->textoFiltros($request),
        ])->stream('reporte-ventas.pdf');
    }

    public function ventasExcel(Request $request, VentaRepository $ventas): StreamedResponse
    {
        $filas = $this->ventasFiltradas($request, $ventas)->map(fn ($venta) => [
            $venta->fecha->format('d/m/Y'),
            $venta->cliente_nombre,
            $venta->cliente_documento,
            $venta->almacen->nombre,
            $venta->numero_comprobante,
            (float) $venta->total,
            $venta->anulada_at ? 'Anulada' : 'Vigente',
        ]);

        return $this->excel->descargar(
            'reporte-ventas.xlsx',
            ['Fecha', 'Cliente', 'Documento', 'Almacén', 'Comprobante', 'Total (S/)', 'Estado'],
            $filas,
        );
    }

    private function comprasFiltradas(Request $request, CompraRepository $compras): Collection
    {
        return $compras->porFiltros(
            $request->query('desde') ?: null,
            $request->query('hasta') ?: null,
            $request->integer('almacen_id') ?: null,
        );
    }

    private function ventasFiltradas(Request $request, VentaRepository $ventas): Collection
    {
        return $ventas->porFiltros(
            $request->query('desde') ?: null,
            $request->query('hasta') ?: null,
            $request->integer('almacen_id') ?: null,
        );
    }

    private function textoFiltros(Request $request): string
    {
        $partes = [];

        if ($desde = $request->query('desde')) {
            $partes[] = 'desde '.date('d/m/Y', strtotime($desde));
        }

        if ($hasta = $request->query('hasta')) {
            $partes[] = 'hasta '.date('d/m/Y', strtotime($hasta));
        }

        return $partes ? implode(' ', $partes) : 'todas las fechas';
    }
}
