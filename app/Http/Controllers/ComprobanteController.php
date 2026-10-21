<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ComprobanteController extends Controller
{
    public function compra(Compra $compra): Response
    {
        $compra->load('proveedor', 'almacen', 'responsable', 'anuladaPor', 'detalles.lote.producto');

        return Pdf::loadView('comprobantes.compra', ['compra' => $compra])
            ->stream('compra-'.str_pad((string) $compra->id, 6, '0', STR_PAD_LEFT).'.pdf');
    }

    public function venta(Venta $venta): Response
    {
        $venta->load('almacen', 'responsable', 'anuladaPor', 'detalles.producto');

        return Pdf::loadView('comprobantes.venta', ['venta' => $venta])
            ->stream('venta-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT).'.pdf');
    }
}
