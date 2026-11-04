<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Transferencia {{ $transfer['id'] }} — Térmica</title>
    <style>
        @page { size: 80mm 297mm; margin: 4mm; }
        * { box-sizing: border-box; }
        body { width: 72mm; margin: 0 auto; color: #000; background: #fff; font: 11px Arial, sans-serif; }
        .logo { display: block; width: 100%; height: auto; filter: grayscale(1); margin-bottom: 4mm; }
        h1 { font-size: 15px; text-align: center; margin: 2mm 0; }
        .meta { margin: 1.5mm 0; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { font-size: 10px; font-weight: bold; border-bottom: 1px dashed #000; padding: 3mm 0 2mm; }
        td { padding: 1mm 0; vertical-align: top; }
        .description { padding-top: 3mm; overflow-wrap: anywhere; }
        .code { border-bottom: 1px solid #000; padding-bottom: 2mm; overflow-wrap: anywhere; }
        .number { text-align: right; font-variant-numeric: tabular-nums; }
        .center { text-align: center; }
        tbody { break-inside: avoid; }
        .totals { width: 100%; margin: 3mm 0; border-bottom: 1px solid #000; padding-bottom: 3mm; }
        .total { font-weight: bold; font-size: 13px; }
        .signature { padding-top: 9mm; border-bottom: 1px solid #000; margin-bottom: 2mm; }
        footer { text-align: center; margin: 5mm 0 2mm; font-size: 10px; }
    </style>
</head>
<body data-print-paper="thermal">
    <img class="logo" src="{{ $logoDataUri }}" alt="Farmacias Shadai">
    <h1>TRANSFERENCIA #{{ $transfer['id'] }}</h1>
    <p class="meta"><strong>Tipo:</strong> {{ $transfer['tipo']['descripcion'] ?? 'Transferencia' }}</p>
    <p class="meta"><strong>Fecha de envío:</strong> {{ $sentAt }}</p>
    <p class="meta"><strong>Origen:</strong> {{ $transfer['stock_de']['descripcion'] ?? '—' }}</p>
    <p class="meta"><strong>Destino:</strong> {{ $transfer['stock_para']['descripcion'] ?? '—' }}</p>
    <p class="meta"><strong>Usuario:</strong> {{ $transfer['usuario_envia']['nombre'] ?? '—' }}</p>
    <table aria-label="Productos transferidos">
        <colgroup><col style="width: 26%"><col style="width: 24%"><col style="width: 12%"><col style="width: 15%"><col style="width: 23%"></colgroup>
        <thead><tr><th>Descripción</th><th class="number">Costo</th><th class="center">ISV</th><th class="number">Cant.</th><th class="number">Total</th></tr></thead>
        @foreach ($lines as $line)
            <tbody>
                <tr><td colspan="5" class="description">{{ $line['product']['descripcion'] ?? 'Producto' }}</td></tr>
                <tr><td></td><td class="number">{{ number_format($line['cost'], 2) }}</td><td class="center">{{ $line['isv'] > 0 ? 'Sí' : 'No' }}</td><td class="number">{{ number_format($line['quantity']) }}</td><td class="number">{{ number_format($line['total'], 2) }}</td></tr>
                <tr><td colspan="5" class="code">{{ $line['product']['codigo'] ?? '—' }}</td></tr>
            </tbody>
        @endforeach
    </table>
    <div class="totals">
        <table>
            <tr><td>Subtotal</td><td class="number">L {{ number_format($totals['subtotal'], 2) }}</td></tr>
            <tr><td>Subtotal ISV</td><td class="number">L {{ number_format($totals['isv'], 2) }}</td></tr>
            <tr class="total"><td>Total</td><td class="number">L {{ number_format($totals['total'], 2) }}</td></tr>
        </table>
    </div>
    <p>{{ count($lines) }} lotes · {{ number_format(array_sum(array_column($lines, 'quantity'))) }} unidades</p>
    <div class="signature"></div><div>Envía: {{ $transfer['usuario_envia']['nombre'] ?? '' }}</div>
    <div class="signature"></div><div>Recibe: {{ $transfer['usuario_recibe']['nombre'] ?? '' }}</div>
    <footer>Comprobante de transferencia · Importes en lempiras</footer>
</body>
</html>
