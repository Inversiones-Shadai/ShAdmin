<?php

return [
    'App\\Livewire\\Config\\Devices' => [
        'device-stocks' => [
            'cache' => 'stocks',
        ],
        'device-states' => [
            'cache' => 'states',
        ],
    ],
    'App\\Livewire\\Config\\Modules' => [
        'module-states' => [
            'cache' => 'states',
        ],
    ],
    'App\\Livewire\\Config\\Stocks' => [
        'stock-states' => [
            'cache' => 'states',
        ],
    ],
    'App\\Livewire\\Config\\Views' => [
        'view-modules' => [
            'cache' => 'modules',
        ],
        'view-states' => [
            'cache' => 'states',
        ],
    ],
    'App\\Livewire\\Config\\Users' => [
        'user-roles' => [
            'cache' => 'roles',
        ],
        'user-states' => [
            'cache' => 'states',
        ],
        'permission-modules' => [
            'cache' => 'permissionModules',
        ],
        'permission-views' => [
            'cache' => 'permissionViews',
            'parent' => 'permissionModule',
        ],
        'permission-actions' => [
            'cache' => 'permissionActions',
            'parent' => 'permissionView',
        ],
        'permission-times' => [
            'cache' => 'permissionTimeTypes',
        ],
    ],
    'App\\Livewire\\Sorteos\\Draws' => [
        'draw-states' => [
            'cache' => 'states',
        ],
    ],
    'App\\Livewire\\Sorteos\\Tickets' => [
        'ticket-departments' => [
            'cache' => 'departments',
        ],
        'ticket-municipalities' => [
            'cache' => 'municipalities',
            'parent' => 'newDepartment',
        ],
    ],
    'App\\Livewire\\Reportes\\JornadaLaboral' => [
        'report-users' => [
            'cache' => 'users',
        ],
    ],
    'App\\Livewire\\Shared\\AdvancedProductSearch' => [
        'product-laboratories' => [
            'cache' => 'laboratories',
        ],
    ],
    'App\\Livewire\\Farma\\Productos' => [
        'product-categories' => [
            'cache' => 'options.categorias',
        ],
        'product-states' => [
            'cache' => 'options.estados',
        ],
        'product-units' => [
            'cache' => 'options.unidades',
        ],
    ],
    'App\\Livewire\\Farma\\ProductCatalogManager' => [
        'family-presentations' => [
            'cache' => 'options.presentacion',
        ],
        'family-administrations' => [
            'cache' => 'options.administracion',
        ],
        'active-concentrations' => [
            'cache' => 'options.concentracion',
        ],
    ],
    'App\\Livewire\\Farma\\Compras' => [
        'purchase-types' => [
            'cache' => 'options.tipos',
        ],
        'payment-types' => [
            'cache' => 'paymentOptions.tipos',
        ],
    ],
    'App\\Livewire\\Farma\\CompraTransacciones' => [
        'payment-types' => [
            'cache' => 'options.tipos',
        ],
        'payment-purchases' => [
            'cache' => 'options.compras',
        ],
    ],
    'App\\Livewire\\Farma\\Transferencias' => [
        'transfer-filter-stocks' => [
            'cache' => 'transferStocks',
        ],
        'transfer-types' => [
            'cache' => 'options.tipos',
        ],
        'transfer-stocks' => [
            'cache' => 'options.stocks_destino',
        ],
        'transfer-children' => [
            'cache' => 'conversionOptions.hijos',
            'parent' => 'conversionOptions.lote',
        ],
        'transfer-filter-types' => [
            'cache' => 'transferTypes',
        ],
    ],
    'App\\Livewire\\Farma\\Tareas' => [
        'task-stocks' => [
            'cache' => 'options.stocks',
        ],
        'task-users' => [
            'cache' => 'options.usuarios',
        ],
    ],
    'App\\Livewire\\Farma\\MatrizInventario' => [
        'inventory-stocks' => [
            'cache' => 'stocks',
        ],
    ],
    'App\\Livewire\\Pos\\Administracion' => [
        'pos-roles' => [
            'cache' => 'roles',
        ],
        'pos-permission-modules' => [
            'cache' => 'permissionModules',
        ],
        'pos-permission-views' => [
            'cache' => 'permissionViews',
            'parent' => 'permissionModule',
        ],
        'pos-permission-actions' => [
            'cache' => 'permissionActions',
            'parent' => 'permissionView',
        ],
        'pos-permission-times' => [
            'cache' => 'permissionTimes',
        ],
    ],
    'App\\Livewire\\Pos\\Catalogos' => [
        'pos-stocks' => [
            'cache' => 'stocks',
        ],
    ],
    'App\\Livewire\\Pos\\Compras' => [
        'pos-purchase-stocks' => [
            'cache' => 'choices.stocks',
        ],
        'pos-purchase-suppliers' => [
            'cache' => 'choices.suppliers',
        ],
        'pos-purchase-products' => [
            'cache' => 'choices.products',
        ],
    ],
    'App\\Livewire\\Pos\\Descuentos' => [
        'discount-categories' => [
            'cache' => 'references',
        ],
    ],
    'App\\Livewire\\Farma\\Distribucion' => [
        'distribution-stocks' => [
            'cache' => 'promotionStocks',
        ],
        'distribution-lots' => [
            'cache' => 'lots',
            'product' => 'selectedProduct.producto_id',
        ],
    ],
];
