<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('comprobantes.estilos')
</head>
<body>
    <div class="encabezado">
        <div class="empresa">COSECHAR E.I.R.L.</div>
        <div class="lema">Cultivando Seguridad, Cosechando Bienestar</div>
    </div>

    <h1>Comprobante interno de venta</h1>
    <div class="numero">N.º {{ str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT) }}</div>

    @if ($venta->anulada_at)
        <div class="anulado">
            ANULADO
            <span>el {{ $venta->anulada_at->format('d/m/Y') }} por {{ $venta->anuladaPor?->name ?? '—' }}: {{ $venta->motivo_anulacion }}</span>
        </div>
    @endif

    <table class="datos">
        <tr><td class="etiqueta">Fecha</td><td>{{ $venta->fecha->format('d/m/Y') }}</td></tr>
        <tr><td class="etiqueta">Cliente</td><td>{{ $venta->cliente_nombre }}</td></tr>
        <tr><td class="etiqueta">Documento</td><td>{{ $venta->cliente_documento ?: '—' }}</td></tr>
        <tr><td class="etiqueta">Comprobante</td><td>{{ $venta->numero_comprobante ?: '—' }}</td></tr>
        <tr><td class="etiqueta">Almacén de origen</td><td>{{ $venta->almacen->nombre }}</td></tr>
    </table>

    <table class="detalle">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="num">Cantidad</th>
                <th class="num">Precio unitario</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->producto->codigo }} — {{ $detalle->producto->nombre }}@if ($detalle->por_mayor) (por mayor)@endif</td>
                    <td class="num">{{ $detalle->cantidad }}</td>
                    <td class="num">S/ {{ number_format($detalle->precio_unitario, 2) }}</td>
                    <td class="num">S/ {{ number_format($detalle->cantidad * $detalle->precio_unitario, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3" class="num">TOTAL</td>
                <td class="num">S/ {{ number_format($venta->total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="pie">
        Registrado por {{ $venta->responsable?->name ?? '—' }} · Generado el {{ now()->format('d/m/Y H:i') }}.<br>
        Documento de control interno del sistema de inventario: no reemplaza la boleta, la factura ni ningún comprobante de pago electrónico autorizado por la SUNAT.
    </div>
</body>
</html>