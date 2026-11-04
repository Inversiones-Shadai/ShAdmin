<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;

final class ProductCostChart
{
    public static function money(?float $value): string
    {
        return $value === null ? 'Sin dato' : 'L '.number_format($value, 4);
    }

    public static function taxLabel(string $state): string
    {
        return match ($state) {
            'si' => 'Sí', 'no' => 'No', 'mixto' => 'Mixto', default => 'Sin dato'
        };
    }

    public function build(array $purchases): array
    {
        $rows = array_values(array_filter($purchases, fn ($row): bool => ! ($row['solo_bonificacion'] ?? false)));
        $values = [];
        foreach ($rows as $row) {
            foreach (['costo_unitario', 'costo_final_unitario'] as $field) {
                if (isset($row[$field]) && is_numeric($row[$field]) && is_finite((float) $row[$field])) {
                    $values[] = (float) $row[$field];
                }
            }
        }
        if ($values === []) {
            return ['points' => [], 'series' => [], 'ticks' => [], 'labels' => []];
        }
        $low = min($values);
        $high = max($values);
        $pad = max(($high - $low) * 0.12, $high * 0.05, 0.01);
        $low = max(0, $low - $pad);
        $high += $pad;
        $y = fn (float $value): float => round(276 - ($value - $low) / ($high - $low) * 244, 2);
        $x = fn (int $index): float => count($rows) === 1 ? 516 : round(84 + $index / (count($rows) - 1) * 844, 2);
        $series = [];
        foreach (['costo_unitario', 'costo_final_unitario'] as $field) {
            $segments = [];
            $segment = [];
            foreach ($rows as $index => $row) {
                if (! isset($row[$field]) || ! is_numeric($row[$field])) {
                    if ($segment !== []) {
                        $segments[] = implode(' ', $segment);
                    }
                    $segment = [];

                    continue;
                }
                $segment[] = $x($index).','.$y((float) $row[$field]);
            }
            if ($segment !== []) {
                $segments[] = implode(' ', $segment);
            }
            $series[$field] = $segments;
        }
        $points = [];
        foreach ($rows as $index => $row) {
            if (! isset($row['costo_unitario']) && ! isset($row['costo_final_unitario'])) {
                continue;
            }
            $points[] = ['x' => $x($index), 'y' => isset($row['costo_unitario']) ? $y((float) $row['costo_unitario']) : null,
                'final_y' => isset($row['costo_final_unitario']) ? $y((float) $row['costo_final_unitario']) : null,
                'invoice' => (string) $row['factura'], 'date' => empty($row['fecha']) ? 'Sin fecha' : Carbon::parse($row['fecha'])->format('d/m/Y H:i'),
                'provider' => $row['proveedor'] ?? 'Sin proveedor', 'cost' => self::money($row['costo_unitario'] ?? null),
                'final' => self::money($row['costo_final_unitario'] ?? null), 'tax' => self::taxLabel($row['isv_estado'] ?? 'sin_dato'),
                'change' => isset($row['variacion']) ? ($row['variacion'] > 0 ? '+' : '').number_format($row['variacion'], 4).' HNL' : 'Sin comparación'];
        }
        $ticks = [];
        for ($i = 0; $i <= 4; $i++) {
            $value = $low + ($high - $low) * $i / 4;
            $ticks[] = ['y' => $y($value), 'label' => number_format($value, 2)];
        }
        $labels = [];
        foreach (array_unique([0, (int) floor((count($rows) - 1) / 3), (int) floor((count($rows) - 1) * 2 / 3), count($rows) - 1]) as $index) {
            $labels[] = ['x' => $x($index), 'label' => empty($rows[$index]['fecha']) ? 'Sin fecha' : Carbon::parse($rows[$index]['fecha'])->format('d/m/Y')];
        }

        return compact('points', 'series', 'ticks', 'labels');
    }
}
