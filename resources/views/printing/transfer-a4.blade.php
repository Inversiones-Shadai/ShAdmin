<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Transferencia {{ $transfer['id'] }} — A4</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17202a; background: #fff; font: 10px Arial, sans-serif; }
        header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 4mm; border-bottom: 2px solid #222; }
        .logo { width: 65mm; height: auto; }
        h1 { font-size: 19px; margin: 0 0 2mm; }
        h2 { font-size: 11px; margin: 0 0 2mm; text-transform: uppercase; }
        p { margin: 1mm 0; overflow-wrap: anywhere; }
        .document { text-align: right; }
        .metadata { display: flex; gap: 6mm; margin: 4mm 0; }
        .metadata section { flex: 1; min-width: 0; border: 1px solid #bbb; padding: 3mm; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th { background: #eee; border-top: 1px solid #999; border-bottom: 1px solid #999; padding: 2mm 1mm; text-align: left; }
        td { padding: 2mm 1mm; vertical-align: top; overflow-wrap: anywhere; }
        .products { font-size: 8px; }
        .products th { padding: 2mm 0.5mm; white-space: nowrap; }
        .products td { padding: 1.5mm 0.5mm; border-bottom: 1px solid #ccc; white-space: nowrap; }
        .products tr { break-inside: avoid; }
        .cell-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .product-name { font-weight: bold; }
        .number { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .totals { margin: 4mm 0 0 auto; width: 65mm; break-inside: avoid; }
        .totals td { padding: 1.5mm; }
        .grand-total { border-top: 2px solid #333; font-size: 13px; font-weight: bold; }
        .closing { break-inside: avoid; }
        .audit { margin-top: 4mm; border-top: 1px solid #bbb; padding-top: 3mm; }
        .signatures { display: flex; justify-content: space-between; gap: 15mm; margin-top: 14mm; }
        .signature { width: 45%; border-top: 1px solid #555; padding-top: 2mm; text-align: center; }
        footer { margin-top: 5mm; text-align: center; color: #555; font-size: 9px; }
    </style>
</head>
<body>
    <header>
        <img class="logo" src="{{ $logoDataUri }}" alt="Farmacias Shadai">
        <div class="document">
            <h1>Transferencia #{{ $transfer['id'] }}</h1>
            <p>{{ $transfer['tipo']['descripcion'] ?? 'Transferencia entre stocks' }}</p>
            <p><strong>Enviada:</strong> {{ $sentAt }}</p>
            <p><strong>Estado de recepción:</strong> {{ $transfer['estado_recepcion_label'] ?? 'Pendiente' }}</p>
        </div>
    </header>
    <div class="metadata">
        <section>
            <h2>Origen</h2>
            <p><strong>{{ $transfer['stock_de']['descripcion'] ?? '—' }}</strong> · Stock #{{ $transfer['stock_de_id'] ?? $transfer['stock_de']['id'] ?? '—' }}</p>
            <p>{{ $transfer['stock_de']['ubicacion'] ?? '—' }}</p>
            <p>Teléfono: {{ $transfer['stock_de']['telefono'] ?? '—' }}</p>
            <p>Envía: {{ $transfer['usuario_envia']['nombre'] ?? '—' }}</p>
        </section>
        <section>
            <h2>Destino</h2>
            <p><strong>{{ $transfer['stock_para']['descripcion'] ?? '—' }}</strong> · Stock #{{ $transfer['stock_para_id'] ?? $transfer['stock_para']['id'] ?? '—' }}</p>
            <p>{{ $transfer['stock_para']['ubicacion'] ?? '—' }}</p>
            <p>Teléfono: {{ $transfer['stock_para']['telefono'] ?? '—' }}</p>
            <p>Recibe: {{ $transfer['usuario_recibe']['nombre'] ?? 'Pendiente' }}</p>
        </section>
    </div>
    <table class="products" aria-label="Detalle de productos de la transferencia">
        <colgroup><col style="width: 7%"><col style="width: 10%"><col style="width: 21%"><col style="width: 9%"><col style="width: 8%"><col style="width: 8%"><col style="width: 9%"><col style="width: 5%"><col style="width: 7%"><col style="width: 6%"><col style="width: 10%"></colgroup>
        <thead><tr><th>Código</th><th>Cód. barras</th><th>Descripción</th><th>Lote</th><th>Fecha elab.</th><th>Fecha exp.</th><th>Categoría</th><th class="number">Cant.</th><th class="number">Costo</th><th class="number">ISV</th><th class="number">Subtotal</th></tr></thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td><div class="cell-text" title="{{ $line['product']['codigo'] ?? '—' }}">{{ $line['product']['codigo'] ?? '—' }}</div></td>
                    <td><div class="cell-text" title="{{ $line['product']['codigobar'] ?? '—' }}">{{ $line['product']['codigobar'] ?? '—' }}</div></td>
                    <td><div class="cell-text product-name" title="{{ $line['product']['descripcion'] ?? 'Producto' }}">{{ $line['product']['descripcion'] ?? 'Producto' }}</div></td>
                    <td><div class="cell-text" title="{{ $line['lot']['codigo'] ?? '—' }}">{{ $line['lot']['codigo'] ?? '—' }}</div></td>
                    <td><div class="cell-text">{{ $line['lot']['fecha_elaboracion'] ?? '—' }}</div></td>
                    <td><div class="cell-text">{{ $line['lot']['fecha_expiracion'] ?? '—' }}</div></td>
                    <td><div class="cell-text" title="{{ $line['product']['categoria'] ?? '—' }}">{{ $line['product']['categoria'] ?? '—' }}</div></td>
                    <td class="number">{{ number_format($line['quantity']) }}</td>
                    <td class="number">{{ number_format($line['cost'], 2) }}</td>
                    <td class="number">{{ number_format($line['isv'], 2) }}</td>
                    <td class="number">{{ number_format($line['subtotal'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="closing">
        <table class="totals">
            <tr><td>Subtotal general</td><td class="number">L {{ number_format($totals['subtotal'], 2) }}</td></tr>
            <tr><td>Subtotal ISV</td><td class="number">L {{ number_format($totals['isv'], 2) }}</td></tr>
            <tr class="grand-total"><td>Total</td><td class="number">L {{ number_format($totals['total'], 2) }}</td></tr>
        </table>
        <div class="audit">
            <p><strong>Resumen:</strong> {{ count($lines) }} lotes · {{ number_format(array_sum(array_column($lines, 'quantity'))) }} unidades transferidas.</p>
            <p><strong>Recepción:</strong> {{ $receivedAt }} · {{ $transfer['usuario_recibe']['nombre'] ?? 'Pendiente de recepción' }}</p>
        </div>
        <div class="signatures">
            <div class="signature">Firma de quien envía<p>{{ $transfer['usuario_envia']['nombre'] ?? '' }}</p></div>
            <div class="signature">Firma de quien recibe<p>{{ $transfer['usuario_recibe']['nombre'] ?? '' }}</p></div>
        </div>
        <footer>Farmacias Shadai · Comprobante de transferencia · Importes en lempiras (L)</footer>
    </div>
</body>
</html>
