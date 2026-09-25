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
        .bajo { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Cosechar E.I.R.L. — Reporte de Stock (por producto y lote)</h1>
    <p class="subtitulo">Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Lote</th>
                <th>Vencimiento</th>
                <th>Cantidad</th>
                <th>Stock mínimo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lotes as $lote)
                <tr>
                    <td>{{ $lote->producto->codigo }} — {{ $lote->producto->nombre }}</td>
                    <td>{{ $lote->producto->categoria->nombre }}</td>
                    <td>{{ $lote->numero_lote }}</td>
                    <td>{{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}</td>
                    <td>{{ $lote->cantidad }}</td>
                    <td>{{ $lote->producto->stock_minimo }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
