<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1b1b18; }
        h1 { font-size: 16px; color: #166534; margin-bottom: 2px; }
        .subtitulo { color: #666; font-size: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #166534; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e5e5; }
        tr:nth-child(even) td { background: #f5f5f4; }
        .derecha { text-align: right; }
        .anulada td { color: #999; text-decoration: line-through; }
        .total { margin-top: 12px; text-align: right; font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Cosechar E.I.R.L. — Reporte de Ventas</h1>
    <p class="subtitulo">Período: {{ $filtros }}. Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Almacén</th>
                <th>Comprobante</th>
                <th class="derecha">Total (S/)</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ventas as $venta)
                <tr class="{{ $venta->anulada_at ? 'anulada' : '' }}">
                    <td>{{ $venta->fecha->format('d/m/Y') }}</td>
                    <td>{{ $venta->cliente_nombre }}</td>
                    <td>{{ $venta->almacen->nombre }}</td>
                    <td>{{ $venta->numero_comprobante ?: '—' }}</td>
                    <td class="derecha">{{ number_format($venta->total, 2) }}</td>
                    <td>{{ $venta->anulada_at ? 'Anulada' : 'Vigente' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="total">Total de ventas vigentes: S/ {{ number_format($total, 2) }}</p>
</body>
</html>
