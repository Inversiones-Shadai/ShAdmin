<?php

return [
    'tipos-compra' => [
        'label' => 'Tipos de compra',
        'description' => 'Define si una compra se registra al contado, a crédito u otra modalidad.',
        'route' => 'farma.compras.tipos-compra',
        'actions' => [
            'list' => 'ctpbtn01', 'create' => 'ctpbtn02', 'search' => 'ctpbtn03',
            'update' => 'ctpbtn04', 'delete' => 'ctpbtn05',
        ],
    ],
    'tipos-transaccion' => [
        'label' => 'Tipos de transacción',
        'description' => 'Clasifica pagos al contado, abonos, notas de crédito y otros movimientos.',
        'route' => 'farma.compras.tipos-transaccion',
        'actions' => [
            'list' => 'ttpbtn01', 'create' => 'ttpbtn02', 'search' => 'ttpbtn03',
            'update' => 'ttpbtn04', 'delete' => 'ttpbtn05',
        ],
    ],
];
