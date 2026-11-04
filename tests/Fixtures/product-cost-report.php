<?php

$rows = [];
foreach ([10, 12, 9] as $index => $cost) {
    $rows[] = ['id' => $index + 1, 'factura' => 'COST-'.($index + 1), 'fecha' => '2026-01-0'.($index + 1).' 12:00:00',
        'proveedor' => 'Proveedor de prueba', 'estado' => 'pagada', 'cantidad' => 5, 'bonificaciones' => 0,
        'solo_bonificacion' => false, 'lotes' => ['L-'.($index + 1)], 'costo_unitario' => (float) $cost,
        'descuento_unitario' => 0.0, 'costo_final_unitario' => round($cost * 1.15, 4),
        'isv_estado' => 'si', 'isv_unitario' => round($cost * 0.15, 4),
        'variacion' => $index === 0 ? null : (float) ($cost - [10, 12][$index - 1]),
        'variacion_pct' => $index === 0 ? null : ($index === 1 ? 20.0 : -25.0),
        'tendencia' => $index === 0 ? 'sin_comparacion' : ($index === 1 ? 'sube' : 'baja')];
}

return ['producto' => ['id' => 4, 'codigo' => 'PR-4', 'descripcion' => 'Producto de prueba'], 'compras' => $rows,
    'resumen' => ['compras' => 3, 'comparables' => 3, 'costo_inicial' => 10.0, 'costo_reciente' => 9.0,
        'costo_minimo' => 9.0, 'costo_maximo' => 12.0, 'variacion' => -1.0, 'variacion_pct' => -10.0, 'bonificaciones' => 0]];
