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

    <h1>Comprobante interno de compra</h1>
    <div class="numero">N.º {{ str_pad((string) $compra->id, 6, '0', STR_PAD_LEFT) }}</div>

    @if ($compra->anulada_at)
        <div class="anulado">
            ANULADO
            <span>el {{ $compra->anulada_at->format('d/m/Y') }} por {{ $compra->anuladaPor?->name ?? '—' }}: {{ $compra->motivo_anulacion }}</span>
        </div>
    @endif

    <table class="datos">
        <tr><td class="etiqueta">Fecha</td><td>{{ $compra->fecha->format('d/m/Y') }}</td></tr>
        <tr><td class="etiqueta">Proveedor</td><td>{{ $compra->proveedor->nombre }}</td></tr>
        <tr><td class="etiqueta">RUC</td><td>{{ $compra->proveedor->ruc ?: '—' }}</td></tr>
        <tr><td class="etiqueta">Comprobante del proveedor</td><td>{{ $compra->numero_comprobante ?: '—' }}</td></tr>
        <tr><td class="etiqueta">Almacén de destino</td><td>{{ $compra->almacen->nombre }}</td></tr>
    </table>

    <table class="detalle">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Lote</th>
                <th>Vencimiento</th>
                <th class="num">Cantidad</th>
                <th class="num">Costo unitario</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($compra->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->lote->producto->codigo }} — {{ $detalle->lote->producto->nombre }}</td>
                    <td>{{ $detalle->lote->etiqueta }}</td>
                    <td>{{ $detalle->lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}</td>
                    <td class="num">{{ $detalle->cantidad }}</td>
                    <td class="num">S/ {{ number_format($detalle->costo_unitario, 2) }}</td>
                    <td class="num">S/ {{ number_format($detalle->cantidad * $detalle->costo_unitario, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="5" class="num">TOTAL</td>
                <td class="num">S/ {{ number_format($compra->total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="pie">
        Registrado por {{ $compra->responsable?->name ?? '—' }} · Generado el {{ now()->format('d/m/Y H:i') }}.<br>
        Documento de control interno del sistema de inventario: no reemplaza la factura ni ningún comprobante de pago electrónico autorizado por la SUNAT.
    </div>
</body>
</html>