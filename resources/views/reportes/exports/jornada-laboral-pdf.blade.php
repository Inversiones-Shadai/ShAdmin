<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 32px 28px 42px; }
        body {
            color: #0f172a;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7px;
            line-height: 1.3;
        }
        h1 {
            color: #1d4ed8;
            font-size: 18px;
            margin: 0 0 4px;
        }
        .meta {
            color: #64748b;
            margin-bottom: 10px;
        }
        .criteria {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            margin-bottom: 12px;
            padding: 6px 8px;
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
            font-size: 6.5px;
            padding: 6px 3px;
            text-align: left;
            text-transform: uppercase;
        }
        td {
            border-bottom: 1px solid #cbd5e1;
            padding: 5px 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .muted { color: #64748b; font-size: 6px; }
        .duration { font-weight: bold; text-align: center; }
        .status { font-weight: bold; }
        .status-short { color: #991b1b; }
        .status-exceeded { color: #92400e; }
        .status-compliant { color: #166534; }
        .status-pending { color: #1e40af; }
        .status-unconfigured,
        .status-not_taken { color: #475569; }
        .empty {
            color: #64748b;
            padding: 28px;
            text-align: center;
        }
        .w-date { width: 6%; }
        .w-worker { width: 11%; }
        .w-location { width: 12%; }
        .w-time { width: 7%; }
        .w-duration { width: 8%; }
        .w-work-status { width: 12%; }
        .w-lunch { width: 13%; }
        .w-allowed { width: 7%; }
        .w-lunch-status { width: 9%; }
    </style>
</head>
<body>
    <h1>Reporte de jornada laboral</h1>
    <div class="meta">
        Generado: {{ $generatedAt }} | Registros: {{ number_format(count($rows)) }}
    </div>
    <div class="criteria">
        <strong>Filtros:</strong> {{ $criteria === [] ? 'Ninguno' : implode(' | ', $criteria) }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="w-date">Fecha</th>
                <th class="w-worker">Trabajador</th>
                <th class="w-location">Ubicación</th>
                <th class="w-time">Entrada</th>
                <th class="w-time">Salida</th>
                <th class="w-duration">Jornada neta</th>
                <th class="w-duration">Esperada</th>
                <th class="w-work-status">Estado jornada</th>
                <th class="w-lunch">Almuerzo</th>
                <th class="w-allowed">Permitido</th>
                <th class="w-lunch-status">Estado almuerzo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['date'] ?: '-' }}</td>
                    <td>{{ $row['user'] ?: '-' }}</td>
                    <td>
                        {{ $row['stock'] ?: '-' }}
                        @if ($row['device'])
                            <div class="muted">{{ $row['device'] }}</div>
                        @endif
                    </td>
                    <td>{{ $row['work_start'] ?: '-' }}</td>
                    <td>{{ $row['work_end'] ?: '-' }}</td>
                    <td class="duration">{{ $row['net_work_duration'] ?: '-' }}</td>
                    <td class="duration">{{ $row['work_expected_duration'] ?: '-' }}</td>
                    <td class="status status-{{ $row['work_status'] }}">{{ $row['work_status_label'] }}</td>
                    <td>
                        {{ $row['lunch_start'] ?: '-' }} - {{ $row['lunch_end'] ?: '-' }}
                        <div class="muted">Duración: {{ $row['lunch_duration'] ?: '-' }}</div>
                    </td>
                    <td class="duration">{{ $row['lunch_expected_duration'] ?: '-' }}</td>
                    <td class="status status-{{ $row['lunch_status'] }}">{{ $row['lunch_status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="empty">No hay registros para exportar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
