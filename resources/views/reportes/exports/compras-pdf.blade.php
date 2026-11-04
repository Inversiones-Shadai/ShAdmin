<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Reporte de compras</title>
    <style>
        @page { margin: 35px 34px 48px; }
        body { font-family: "DejaVu Sans", sans-serif; color: #24354c; font-size: 9px; line-height: 1.45; }
        h1 { margin: 0 0 4px; font-size: 21px; color: #173653; }
        .muted { color: #667588; }
        .report-head { border-bottom: 2px solid #173653; padding-bottom: 12px; margin-bottom: 18px; }
        .invoice { margin-top: 18px; }
        .invoice-head { page-break-inside: avoid; page-break-after: avoid; }
        .title { background: #173653; color: white; padding: 7px 9px; font-size: 12px; font-weight: bold; }
        .metadata { padding: 7px 0; overflow-wrap: break-word; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 6px 7px; vertical-align: top; overflow-wrap: break-word; }
        th { text-align: left; background: #e8edf5; font-weight: bold; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .money { text-align: right; white-space: nowrap; }
        .amounts td { background: #f3f6fa; font-size: 11px; font-weight: bold; }
        .payments { margin-top: 10px; }
        .payments td { border-bottom: 1px solid #e3e8ef; }
        .payment-title { background: #f3f6fa; color: #173653; }
        .invoice-footer { margin-top: 7px; page-break-inside: avoid; }
        .invoice-footer td { background: #eff5f3; }
        .grand { margin-top: 24px; page-break-inside: avoid; }
        .grand td { border-bottom: 1px solid #e3e8ef; }
        .grand-total td { font-size: 12px; font-weight: bold; background: #e8edf5; color: #173653; }
        .footer { position: fixed; bottom: -24px; left: 0; font-size: 8px; color: #667588; }
    </style>
</head>
<body>
    <div class="footer">Shadmin · Farma · Importes expresados en lempiras (HNL)</div>
    <div class="report-head"><h1>Reporte de compras</h1><div class="muted">Generado: {{ $generatedAt }} · {{ count($purchases) }} compras</div><div>{{ implode(' | ', $criteria) }}</div></div>
    @foreach ($purchases as $purchase)
        <div class="invoice">
            <div class="invoice-head">
                <div class="title">Factura {{ $purchase['nro'] }} · {{ $purchase['estado_label'] }}</div>
                <div class="metadata"><strong>Proveedor:</strong> {{ $purchase['supplier'] }}<br><strong>Tipo:</strong> {{ $purchase['type_label'] }} · <strong>Plazo:</strong> {{ $purchase['plazo'] }} días · <strong>Fecha:</strong> {{ $purchase['date'] }}</div>
                <table class="amounts"><thead><tr><th>Subtotal</th><th>ISV</th><th>Descuento</th><th>Total de compra</th></tr></thead><tbody><tr>
                    @foreach (['subtotal', 'isv', 'descuento', 'total'] as $key)<td class="money">L {{ number_format((float) $purchase[$key], 2) }}</td>@endforeach
                </tr></tbody></table>
            </div>
            <table class="payments">
                <thead><tr><th colspan="4" class="payment-title">Pagos realizados · Factura {{ $purchase['nro'] }}</th></tr><tr><th style="width:22%">Fecha</th><th style="width:26%">Tipo</th><th style="width:30%">Comprobante</th><th style="width:22%" class="money">Valor</th></tr></thead>
                <tbody>
                @forelse ($purchase['pagos'] as $payment)
                    <tr><td>{{ $payment['date'] }}</td><td>{{ $payment['type_label'] }}</td><td>{{ $payment['nro'] }}</td><td class="money">L {{ number_format((float) $payment['valor'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="muted">Sin pagos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
            <table class="invoice-footer"><tr><td>Abonos<br><strong>L {{ number_format((float) $purchase['total_abonos'], 2) }}</strong></td><td>Total de pagos aplicados<br><strong>L {{ number_format((float) $purchase['total_aplicado'], 2) }}</strong></td><td>Saldo pendiente<br><strong>L {{ number_format((float) $purchase['saldo'], 2) }}</strong></td></tr></table>
        </div>
    @endforeach
    <div class="grand">
        <div class="title">Grandes totales · {{ count($purchases) }} compras</div>
        <table>
            @foreach (['subtotal' => 'Subtotal general', 'isv' => 'ISV general', 'descuento' => 'Descuentos', 'total' => 'Gran total de compras', 'abonos' => 'Total de abonos', 'aplicado' => 'Total de pagos aplicados', 'saldo' => 'Saldo pendiente general'] as $key => $label)
                <tr class="{{ $key === 'total' ? 'grand-total' : '' }}"><td>{{ $label }}</td><td class="money">L {{ number_format((float) $totals[$key], 2) }}</td></tr>
            @endforeach
        </table>
    </div>
</body>
</html>
