<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8px; }
        body { font-family: Helvetica, Arial, sans-serif; margin: 0; padding: 0; }
        .etiqueta { width: 100%; }
        .fila { width: 100%; }
        .qr { width: 90px; text-align: center; vertical-align: top; }
        .info { vertical-align: top; padding-left: 8px; }
        .empresa { font-size: 9px; color: #4b6b3a; font-weight: bold; text-transform: uppercase; }
        .producto { font-size: 13px; font-weight: bold; color: #1b1b18; margin: 2px 0; }
        .dato { font-size: 10px; color: #333; margin: 1px 0; }
        .etiqueta-dato { color: #777; }
    </style>
</head>
<body>
    <table class="etiqueta">
        <tr>
            <td class="qr">
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(90)->generate($lote->codigo_qr) !!}
            </td>
            <td class="info">
                <div class="empresa">Cosechar E.I.R.L.</div>
                <div class="producto">{{ $lote->producto->nombre }}</div>
                <div class="dato"><span class="etiqueta-dato">Código:</span> {{ $lote->producto->codigo }}</div>
                <div class="dato"><span class="etiqueta-dato">Lote:</span> {{ $lote->numero_lote }}</div>
                <div class="dato"><span class="etiqueta-dato">Ingreso:</span> {{ $lote->fecha_ingreso->format('d/m/Y') }}</div>
                <div class="dato">
                    <span class="etiqueta-dato">Vence:</span>
                    {{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}
                </div>
                <div class="dato"><span class="etiqueta-dato">Cantidad:</span> {{ $lote->cantidad }} {{ $lote->producto->unidad_medida }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
