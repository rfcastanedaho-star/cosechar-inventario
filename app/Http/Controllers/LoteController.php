<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class LoteController extends Controller
{
    public function consulta(Lote $lote): View
    {
        $lote->load('producto.categoria');

        return view('lotes.consulta', ['lote' => $lote]);
    }

    public function etiqueta(Lote $lote): Response
    {
        $lote->load('producto');

        $pdf = Pdf::loadView('lotes.etiqueta-qr', ['lote' => $lote])
            ->setPaper([0, 0, 288, 216]);

        return $pdf->stream("etiqueta-lote-{$lote->numero_lote}.pdf");
    }
}
