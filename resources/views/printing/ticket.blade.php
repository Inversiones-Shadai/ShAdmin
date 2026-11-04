<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: 90mm 205mm;
            margin: 0;
        }

        body {
            width: 70mm;
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 1mm;
            background: white;
        }

        .ticket-container {
            break-after: page;
            border: 2px dashed #333;
            padding: 8px;
            background: white;
            max-width: 100%;
        }

        .ticket-container:last-child { break-after: auto; }

        .logo {
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #000;
        }

        .logo img {
            width: 150px;
            max-width: 90%;
            height: auto;
        }

        .ticket-number {
            text-align: center;
            margin: 12px 0;
        }

        .ticket-box {
            border: 3px double #000;
            border-radius: 8px;
            padding: 10px 18px;
            display: inline-block;
            font-weight: bold;
            font-size: 20px;
            background: #f9f9f9;
            letter-spacing: 1px;
        }

        .ticket-value {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin-top: 6px;
            color: #000;
        }

        .promo-section {
            text-align: center;
            margin: 12px 0;
            padding: 8px;
            background: #f0f0f0;
            border-radius: 5px;
        }

        .promo-section p {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }

        .info-section {
            margin: 10px 0;
            padding: 8px 0;
            border-top: 1px dashed #666;
            border-bottom: 1px dashed #666;
        }

        .info-row {
            margin: 5px 0;
            font-size: 12px;
            word-wrap: break-word;
        }

        .info-label {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            color: #000;
            display: inline-block;
            min-width: auto;
        }

        .info-value {
            font-weight: bold;
            color: #000;
        }

        .footer {
            text-align: center;
            margin-top: 12px;
            padding-top: 8px;
            border-top: 2px solid #000;
        }

        .footer p {
            font-size: 16px;
            margin: 2px 0;
            color: #000;
        }
    </style>
</head>

<body>
    @for ($copy = 0; $copy < $copies; $copy++)
    <div class="ticket-container">
        <div class="logo">
            <img src="{{ $logoDataUri }}" alt="Logo">
        </div>

        <div class="ticket-number">
            <div class="ticket-box">
                BOLETO# {{ $ticket->number }}
            </div>

            @if ($ticket->value > 0)
                <div class="ticket-value">
                    VALOR: L.{{ $ticket->value }}
                </div>
            @endif
        </div>

        <div class="promo-section">
            <p>{{ $ticket->promotionName }}</p>
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="info-label">CLIENTE:</span>
                <span class="info-value">{{ $ticket->clientName }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">IDENTIDAD:</span>
                <span class="info-value">{{ $ticket->formattedDocument() }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">TELÉFONO:</span>
                <span class="info-value">{{ $ticket->clientPhone }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">PUNTO:</span>
                <span class="info-value">{{ $ticket->stockName }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">FECHA:</span>
                <span class="info-value">{{ $ticket->formattedIssuedAt() }}</span>
            </div>
        </div>

        <div class="footer">
            <p>Conserve este ticket</p>
            <p>Válido para el sorteo</p>
        </div>
    </div>
    @endfor
</body>

</html>
