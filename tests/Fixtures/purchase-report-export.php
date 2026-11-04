<?php

return [
    ['id' => 1, 'nro' => 'F-001', 'plazo' => 30, 'estado_label' => 'Pendiente',
        'proveedor' => ['nombre' => 'Droguería de prueba'], 'tipo' => ['descripcion' => 'Crédito'],
        'created_at' => '2026-10-17T16:00:00Z', 'subtotal' => 100, 'isv' => 15, 'descuento' => 5, 'total' => 110,
        'pagos' => [
            ['id' => 1, 'tipo_id' => 2, 'nro' => 'AB-001', 'valor' => 20, 'tipo' => ['descripcion' => 'Abono'], 'created_at' => '2026-10-17T17:00:00Z'],
            ['id' => 2, 'tipo_id' => 3, 'nro' => 'NC-001', 'valor' => 5, 'tipo' => ['descripcion' => 'Nota de crédito'], 'created_at' => '2026-10-17T18:00:00Z'],
        ]],
    ['id' => 2, 'nro' => 'F-002', 'plazo' => 0, 'estado_label' => 'Pagada',
        'proveedor' => ['nombre' => '=HYPERLINK("https://example.test", "Texto literal")'], 'tipo' => ['descripcion' => 'Contado'],
        'created_at' => '2026-10-17T16:00:00Z', 'subtotal' => 200, 'isv' => 0, 'descuento' => 0, 'total' => 200,
        'pagos' => [['id' => 3, 'tipo_id' => 1, 'nro' => '=1+1', 'valor' => 200, 'tipo' => ['descripcion' => 'Pago al contado'], 'created_at' => '2026-10-17T17:00:00Z']]],
    ['id' => 3, 'nro' => 'F-003', 'plazo' => 30, 'estado_label' => 'Pendiente',
        'proveedor' => ['nombre' => 'Proveedor sin pagos'], 'tipo' => ['descripcion' => 'Crédito'],
        'created_at' => '2026-10-17T16:00:00Z', 'subtotal' => 50, 'isv' => 0, 'descuento' => 0, 'total' => 50, 'pagos' => []],
];
