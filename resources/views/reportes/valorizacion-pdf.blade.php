<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1b1b18; }
        h1 { font-size: 16px; color: #166534; margin-bottom: 2px; }
        .subtitulo { color: #666; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #166534; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e5e5; }
        tr:nth-child(even) td { background: #f5f5f4; }
        .num { text-align: right; }
        .total td { background: #e7f0e9; font-weight: bold; border-top: 2px solid #166534; }
        .aviso { margin-top: 12px; padding: 8px 10px; background: #fef3c7; color: #92400e; font-size: 10px; }
        .pie { margin-top: 14px; color: #666; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Cosechar E.I.R.L. — Valorización del inventario</h1>
    <p class="subtitulo">Generado el {{ now()->format('d/m/Y H:i') }} · Almacén: {{ $filtro }}</p>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Almacén</th>
                <th class="num">Unidades</th>
                <th class="num">Costo promedio</th>
                <th class="num">Valor a costo</th>
                <th class="num">Valor a precio de venta</th>
                <th class="num">Utilidad potencial</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td>{{ $fila->producto->codigo }} — {{ $fila->producto->nombre }}</td>
                    <td>{{ $fila->almacen?->nombre ?? 'Sin almacén' }}</td>
                    <td class="num">{{ $fila->unidades }}</td>
                    <td class="num">{{ $fila->costoPromedio !== null ? 'S/ '.number_format($fila->costoPromedio, 2) : 'sin costo' }}</td>
                    <td class="num">S/ {{ number_format($fila->valorCosto, 2) }}</td>
                    <td class="num">S/ {{ number_format($fila->valorVenta, 2) }}</td>
                    <td class="num">S/ {{ number_format($fila->utilidad, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>TOTAL ({{ $totales['productos'] }} producto(s))</td>
                <td></td>
                <td class="num">{{ $totales['unidades'] }}</td>
                <td></td>
                <td class="num">S/ {{ number_format($totales['valorCosto'], 2) }}</td>
                <td class="num">S/ {{ number_format($totales['valorVenta'], 2) }}</td>
                <td class="num">S/ {{ number_format($totales['utilidad'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    @unless ($totales['completo'])
        <p class="aviso">
            Hay {{ $totales['unidadesSinCosto'] }} unidades sin costo registrado: no suman al valor a costo,
            por lo que el costo total está incompleto.
        </p>
    @endunless

    <p class="pie">
        Valor a costo: unidades × costo de compra de cada lote. Valor a precio de venta: unidades × precio actual del producto.
        Utilidad potencial: lo que se ganaría vendiendo el stock con costo a su precio actual.
    </p>
</body>
</html>