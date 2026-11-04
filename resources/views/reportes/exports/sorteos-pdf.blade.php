<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 34px 32px 42px; }
        body {
            color: #0f172a;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            line-height: 1.35;
        }
        h1 {
            color: #1d4ed8;
            font-size: 20px;
            margin: 0 0 4px;
        }
        .meta {
            color: #64748b;
            margin-bottom: 12px;
        }
        .criteria {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            color: #1e3a8a;
            margin-bottom: 14px;
            padding: 7px 9px;
        }
        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th {
            background: #334155;
            border: 1px solid #475569;
            color: #ffffff;
            font-size: 8px;
            padding: 7px 5px;
            text-align: left;
            text-transform: uppercase;
        }
        td {
            border: 1px solid #cbd5e1;
            padding: 6px 5px;
            vertical-align: top;
            word-wrap: break-word;
        }
        tbody tr:nth-child(even) { background: #eff6ff; }
        .ticket { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; }
        .empty {
            color: #64748b;
            padding: 28px;
            text-align: center;
        }
        .w-ticket { width: 9%; }
        .w-draw { width: 19%; }
        .w-client { width: 21%; }
        .w-document { width: 14%; }
        .w-user { width: 14%; }
        .w-stock { width: 14%; }
        .w-date { width: 13%; }
    </style>
</head>
<body>
    <h1>Reporte de sorteos</h1>
    <div class="meta">
        Generado: {{ $generatedAt }} · Registros: {{ number_format(count($rows)) }}
    </div>
    <div class="criteria">
        <strong>Filtros:</strong> {{ $criteria === [] ? 'Ninguno' : implode(' | ', $criteria) }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="w-ticket">Ticket</th>
                <th class="w-draw">Sorteo</th>
                <th class="w-client">Cliente</th>
                <th class="w-document">Identidad</th>
                <th class="w-user">Usuario</th>
                <th class="w-stock">Stock</th>
                <th class="w-date">Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="ticket">#{{ $row['number'] ?: '—' }}</td>
                    <td>{{ $row['promotion'] ?: '—' }}</td>
                    <td>{{ $row['client'] ?: '—' }}</td>
                    <td>{{ $row['document'] ?: '—' }}</td>
                    <td>{{ $row['user'] ?: '—' }}</td>
                    <td>{{ $row['stock'] ?: '—' }}</td>
                    <td>{{ $row['created_at'] ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">No hay registros para exportar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
