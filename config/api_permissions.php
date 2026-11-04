<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Permisos de la API Shadai
    |--------------------------------------------------------------------------
    |
    | Cada capacidad usa los códigos reales almacenados en shadaidb:
    | módulo, vista y, cuando aplica, acción de la vista.
    |
    */
    'catalog' => [
        'pos' => [
            'label' => 'POS',
            'icon' => 'shopping-cart',
            'views' => [
                'dcp' => [
                    'label' => 'Reglas de descuentos', 'icon' => 'receipt-percent',
                    'route' => 'pos.descuentos', 'route-pattern' => 'pos.descuentos',
                    'ability' => 'discountspos.list',
                    'actions' => ['dcpbtn01', 'dcpbtn02', 'dcpbtn03', 'dcpbtn04', 'dcpbtn05', 'dcpbtn06'],
                ],
                'cpp' => [
                    'label' => 'Compras POS', 'icon' => 'document-text',
                    'route' => 'pos.compras', 'route-pattern' => 'pos.compras',
                    'ability' => 'purchasespos.list',
                    'actions' => ['cppbtn01', 'cppbtn02', 'cppbtn03'],
                ],
                'usp' => [
                    'label' => 'Usuarios POS',
                    'icon' => 'users',
                    'route' => 'pos.usuarios',
                    'route-pattern' => 'pos.usuarios',
                    'ability' => 'usuariospos.list',
                    'actions' => ['uspbtn01', 'uspbtn02', 'uspbtn03', 'uspbtn04', 'uspbtn05'],
                ],
                'rsp' => [
                    'label' => 'Roles POS',
                    'icon' => 'shield-check',
                    'route' => 'pos.roles',
                    'route-pattern' => 'pos.roles',
                    'ability' => 'rolespos.list',
                    'actions' => ['rspbtn01', 'rspbtn02', 'rspbtn03', 'rspbtn04', 'rspbtn05'],
                ],
                'cop' => [
                    'label' => 'Catálogos de operación POS',
                    'icon' => 'banknotes',
                    'route' => 'pos.catalogos',
                    'route-pattern' => 'pos.catalogos',
                    'ability' => 'catalogospos.list',
                    'actions' => ['copbtn01', 'copbtn02', 'copbtn03', 'copbtn04', 'copbtn05'],
                ],
                'clp' => [
                    'label' => 'Clientes POS',
                    'icon' => 'users',
                    'route' => 'pos.clientepos',
                    'route-pattern' => 'pos.clientepos',
                    'ability' => 'clientepos.list',
                    'actions' => ['clpbtn01', 'clpbtn02', 'clpbtn03', 'clpbtn04', 'clpbtn05'],
                ],
            ],
        ],
        'aht' => [
            'label' => 'Autenticación',
            'icon' => 'lock-closed',
            'views' => [
                'ssn' => [
                    'label' => 'Sesión',
                    'icon' => 'key',
                    'route' => null,
                    'actions' => ['ssnbtn01', 'ssnbt02'],
                ],
                'eswl' => [
                    'label' => 'Jornada laboral',
                    'icon' => 'clock',
                    'route' => null,
                    'actions' => ['eswlbtn01', 'eswlbtn02'],
                ],
            ],
        ],

        'cfg' => [
            'label' => 'Config',
            'icon' => 'ajustes',
            'views' => [
                'vs' => [
                    'label' => 'Vistas',
                    'icon' => 'vista',
                    'route' => 'config.views',
                    'route-pattern' => 'config.views',
                    'ability' => 'views.list',
                    'actions' => [
                        'vsbtn01', 'vsbtn02', 'vsbtn03', 'vsbtn04', 'vsbtn05',
                        'vsbtn06', 'vsbtn07', 'vsbtn08', 'vsbtn09', 'vsbtn10',
                        'vsbtn11',
                    ],
                ],
                'mdl' => [
                    'label' => 'Módulos',
                    'icon' => 'modulos',
                    'route' => 'config.modules',
                    'route-pattern' => 'config.modules',
                    'ability' => 'modules.list',
                    'actions' => [
                        'mdlbtn01', 'mdlbtn02', 'mdlbtn03',
                        'mdlbtn04', 'mdlbtn05', 'mdlbtn06',
                    ],
                ],
                'usr' => [
                    'label' => 'Usuarios',
                    'icon' => 'usuario',
                    'route' => 'config.users',
                    'route-pattern' => 'config.users',
                    'ability' => 'users.list',
                    'actions' => [
                        'usrbnt01', 'usrbnt02', 'usrbnt03', 'usrbnt04',
                        'usrbnt05', 'usrbtn06', 'usrbtn07', 'usrbtn08',
                        'usrbtn09', 'usrbtn10',
                    ],
                ],
                'prm' => [
                    'label' => 'Permisos',
                    'icon' => 'shield-check',
                    'route' => null,
                    'actions' => [
                        'prmbtn08', 'prmbtn09', 'prmbtn10', 'prmbtn11',
                        'prmbtn12', 'prmbtn13', 'prmbtn14',
                    ],
                ],
                'lic' => [
                    'label' => 'Licencias de escritorio', 'icon' => 'key',
                    'route' => 'config.licenses', 'route-pattern' => 'config.licenses',
                    'ability' => 'licenses.list', 'actions' => ['licbtn01', 'licbtn02', 'licbtn03', 'licbtn04', 'licbtn05', 'licbtn06'],
                ],
                'upd' => [
                    'label' => 'Actualizaciones de escritorio', 'icon' => 'device',
                    'route' => 'config.desktop-releases', 'route-pattern' => 'config.desktop-releases',
                    'ability' => 'desktop-releases.list',
                    'actions' => ['updbtn01', 'updbtn02', 'updbtn03', 'updbtn04', 'updbtn05', 'updbtn06', 'updbtn07', 'updbtn08'],
                ],
                'dvc' => [
                    'label' => 'Dispositivos',
                    'icon' => 'device',
                    'route' => 'config.devices',
                    'route-pattern' => 'config.devices',
                    'ability' => 'devices.list',
                    'actions' => [
                        'dvcbtn01', 'dvcbtn02', 'dvcbnt03', 'dvcbnt04',
                        'dvcbnt05', 'dvcbnt06', 'dvcbtn07',
                    ],
                ],
                'stk' => [
                    'label' => 'Stocks',
                    'icon' => 'stock',
                    'route' => 'config.stocks',
                    'route-pattern' => 'config.stocks',
                    'ability' => 'stocks.list',
                    'actions' => [
                        'stkbnt01', 'stkbnt02', 'stkbnt03',
                        'stkbnt04', 'stkbtn05', 'stkbtn06',
                    ],
                ],
            ],
        ],

        'srts' => [
            'label' => 'Sorteos',
            'icon' => 'sorteo',
            'views' => [
                'tks' => [
                    'label' => 'Generación de tickets',
                    'icon' => 'ticket',
                    'route' => 'sorteos.tickets',
                    'route-pattern' => 'sorteos.tickets',
                    'ability' => 'tickets.list',
                    'actions' => [
                        'tksbnt01', 'tksbnt02', 'tksbnt03', 'tksbnt04',
                        'tksbnt05', 'tksbnt06', 'tksbtn07', 'tksbnt08',
                        'tksbnt09', 'tksbtn10', 'tksbnt11',
                    ],
                ],
                'strl' => [
                    'label' => 'Lista de sorteos',
                    'icon' => 'sparkles',
                    'route' => 'sorteos.draws',
                    'route-pattern' => 'sorteos.draws',
                    'ability' => 'draws.list',
                    'actions' => [
                        'strlbnt01', 'strlbnt02', 'strlbnt03',
                        'strlbnt04', 'strlbnt05', 'strlbnt06',
                    ],
                ],
            ],
        ],

        'rpt' => [
            'label' => 'Reportes',
            'icon' => 'reportes',
            'views' => [
                'rptstr' => [
                    'label' => 'Reporte de sorteos',
                    'icon' => 'document-chart-bar',
                    'route' => 'reportes.sorteos',
                    'route-pattern' => 'reportes.sorteos',
                    'ability' => 'reports.draws.filter',
                    'actions' => [
                        'rptstrbtn01', 'rptstrbnt02', 'rptstrbtn03',
                        'rptstrbnt04', 'rptstrbnt05', 'rptstrbtn06',
                        'rptstrbnt07', 'rptstrbtn08', 'rptstrbtn09',
                        'rptstrbtn10',
                    ],
                ],
                'rptwl' => [
                    'label' => 'Jornada laboral',
                    'icon' => 'clock',
                    'route' => 'reportes.jornada-laboral',
                    'route-pattern' => 'reportes.jornada-laboral',
                    'ability' => 'reports.worklunch.filter',
                    'actions' => [
                        'rptwlbtn01', 'rptwlbtn02',
                        'rptwlbtn03', 'rptwlbtn04',
                    ],
                ],
            ],
        ],

        'frm' => [
            'label' => 'Farma',
            'icon' => 'farma',
            'groups' => [
                'stocks' => ['label' => 'Stocks', 'icon' => 'archive-box'],
                'products' => [
                    'label' => 'Productos',
                    'icon' => 'cube',
                ],
                'purchases' => [
                    'label' => 'Compras',
                    'icon' => 'shopping-cart',
                ],
                'reports' => ['label' => 'Reportes', 'icon' => 'document-chart-bar'],
            ],
            'views' => [
                'civ' => ['label' => 'Control de inventario', 'icon' => 'clipboard-document-check',
                    'route' => null, 'group' => 'stocks',
                    'ability' => 'inventory-control.list', 'actions' => ['civbtn01', 'civbtn02', 'civbtn03']],
                'miv' => ['label' => 'Matriz de inventario', 'icon' => 'table-cells',
                    'route' => 'farma.stocks.matriz-inventario', 'route-pattern' => 'farma.stocks.*',
                    'ability' => 'inventory-matrix.list', 'group' => 'stocks', 'actions' => ['mivbtn01', 'mivbtn02']],
                'tar' => ['label' => 'Tareas', 'icon' => 'clipboard-document-check', 'route' => 'farma.tareas', 'route-pattern' => 'farma.tareas',
                    'ability' => 'tasks.list', 'actions' => ['tarbtn01', 'tarbtn02', 'tarbtn03', 'tarbtn04', 'tarbtn05', 'tarbtn06']],
                'prv' => [
                    'label' => 'Proveedores',
                    'icon' => 'building-storefront',
                    'route' => 'farma.proveedores',
                    'route-pattern' => 'farma.proveedores',
                    'ability' => 'providers.list',
                    'actions' => [
                        'prvbtn01', 'prvbtn02', 'prvbtn03',
                        'prvbtn04', 'prvbtn05', 'prvbtn06',
                    ],
                ],
                'lbt' => [
                    'label' => 'Laboratorios',
                    'icon' => 'beaker',
                    'route' => 'farma.laboratorios',
                    'route-pattern' => 'farma.laboratorios',
                    'ability' => 'laboratories.list',
                    'actions' => [
                        'lbtbtn01', 'lbtbtn02', 'lbtbtn03',
                        'lbtbtn04', 'lbtbtn05', 'lbtbtn06',
                    ],
                ],
                'prd' => [
                    'label' => 'Productos',
                    'icon' => 'cube',
                    'route' => 'farma.productos',
                    'route-pattern' => 'farma.productos',
                    'ability' => 'products.list',
                    'group' => 'products',
                    'actions' => [
                        'prdbtn01', 'prdbtn02', 'prdbtn03', 'prdbtn04',
                        'prdbtn05', 'prdbtn06', 'prdbtn07', 'prdbtn08',
                        'prdbtn09', 'prdbtn10', 'prdbtn11', 'prdbtn12',
                        'prdbtn19',
                    ],
                ],
                'vnc' => [
                    'label' => 'Próximos a vencer', 'icon' => 'clock',
                    'route' => 'farma.vencimientos', 'route-pattern' => 'farma.vencimientos',
                    'ability' => 'expiry.list', 'group' => 'products',
                    'actions' => ['vncbtn01', 'vncbtn02', 'vncbtn03'],
                ],
                'rdp' => [
                    'label' => 'Reglas de proveedores', 'icon' => 'adjustments-horizontal',
                    'route' => 'farma.reglas-proveedores', 'route-pattern' => 'farma.reglas-proveedores',
                    'ability' => 'supplier-rules.list',
                    'actions' => ['rdpbtn01', 'rdpbtn02'],
                ],
                'lts' => [
                    'label' => 'Lotes',
                    'icon' => 'archive-box',
                    'route' => 'farma.lotes',
                    'route-pattern' => 'farma.lotes',
                    'ability' => 'lots.list',
                    'group' => 'products',
                    'actions' => ['ltsbtn01', 'ltsbtn02'],
                ],
                'dst' => [
                    'label' => 'Distribución',
                    'icon' => 'currency-dollar',
                    'route' => 'farma.distribucion',
                    'route-pattern' => 'farma.distribucion',
                    'ability' => 'distribution.list',
                    'group' => 'products',
                    'actions' => ['dstbtn01', 'dstbtn02', 'dstbtn03'],
                ],
                'tft' => [
                    'label' => 'Tipos de transferencia',
                    'icon' => 'arrows-right-left',
                    'route' => 'farma.transferencias.tipos',
                    'route-pattern' => 'farma.transferencias.tipos',
                    'ability' => 'transfer-types.list',
                    'actions' => ['tftbtn01', 'tftbtn02', 'tftbtn03', 'tftbtn04', 'tftbtn05'],
                ],
                'trf' => [
                    'label' => 'Transferencias',
                    'icon' => 'arrows-right-left',
                    'route' => 'farma.transferencias',
                    'route-pattern' => 'farma.transferencias',
                    'ability' => 'transfers.list',
                    'actions' => ['trfbtn01', 'trfbtn02', 'trfbtn03', 'trfbtn04', 'trfbtn05', 'trfbtn06', 'trfbtn07'],
                ],
                'pud' => [
                    'label' => 'Unidades',
                    'icon' => 'scale',
                    'route' => 'farma.productos.unidades',
                    'route-pattern' => 'farma.productos.unidades',
                    'ability' => 'product-catalogs.unidades.list',
                    'group' => 'products',
                    'actions' => [
                        'pudbtn01', 'pudbtn02', 'pudbtn03',
                        'pudbtn04', 'pudbtn05',
                    ],
                ],
                'pes' => [
                    'label' => 'Estados',
                    'icon' => 'check-circle',
                    'route' => 'farma.productos.estados',
                    'route-pattern' => 'farma.productos.estados',
                    'ability' => 'product-catalogs.estados.list',
                    'group' => 'products',
                    'actions' => [
                        'pesbtn01', 'pesbtn02', 'pesbtn03',
                        'pesbtn04', 'pesbtn05',
                    ],
                ],
                'pct' => [
                    'label' => 'Categorías',
                    'icon' => 'tag',
                    'route' => 'farma.productos.categorias',
                    'route-pattern' => 'farma.productos.categorias',
                    'ability' => 'product-catalogs.categorias.list',
                    'group' => 'products',
                    'actions' => [
                        'pctbtn01', 'pctbtn02', 'pctbtn03',
                        'pctbtn04', 'pctbtn05',
                    ],
                ],
                'fpr' => [
                    'label' => 'Presentaciones',
                    'icon' => 'archive-box',
                    'route' => 'farma.productos.presentaciones',
                    'route-pattern' => 'farma.productos.presentaciones',
                    'ability' => 'product-catalogs.presentaciones.list',
                    'group' => 'products',
                    'actions' => [
                        'fprbtn01', 'fprbtn02', 'fprbtn03',
                        'fprbtn04', 'fprbtn05',
                    ],
                ],
                'fad' => [
                    'label' => 'Vías de administración',
                    'icon' => 'arrow-right-circle',
                    'route' => 'farma.productos.administraciones',
                    'route-pattern' => 'farma.productos.administraciones',
                    'ability' => 'product-catalogs.administraciones.list',
                    'group' => 'products',
                    'actions' => [
                        'fadbtn01', 'fadbtn02', 'fadbtn03',
                        'fadbtn04', 'fadbtn05',
                    ],
                ],
                'fam' => [
                    'label' => 'Familias',
                    'icon' => 'rectangle-group',
                    'route' => 'farma.productos.familias',
                    'route-pattern' => 'farma.productos.familias',
                    'ability' => 'product-catalogs.familias.list',
                    'group' => 'products',
                    'actions' => [
                        'fambtn01', 'fambtn02', 'fambtn03',
                        'fambtn04', 'fambtn05',
                    ],
                ],
                'cnc' => [
                    'label' => 'Concentraciones',
                    'icon' => 'beaker',
                    'route' => 'farma.productos.concentraciones',
                    'route-pattern' => 'farma.productos.concentraciones',
                    'ability' => 'product-catalogs.concentraciones.list',
                    'group' => 'products',
                    'actions' => [
                        'cncbtn01', 'cncbtn02', 'cncbtn03',
                        'cncbtn04', 'cncbtn05',
                    ],
                ],
                'pac' => [
                    'label' => 'Principios activos',
                    'icon' => 'sparkles',
                    'route' => 'farma.productos.principios-activos',
                    'route-pattern' => 'farma.productos.principios-activos',
                    'ability' => 'product-catalogs.principios-activos.list',
                    'group' => 'products',
                    'actions' => [
                        'pacbtn01', 'pacbtn02', 'pacbtn03',
                        'pacbtn04', 'pacbtn05',
                    ],
                ],
                'cmp' => [
                    'label' => 'Compras',
                    'icon' => 'shopping-bag',
                    'route' => 'farma.compras',
                    'route-pattern' => 'farma.compras',
                    'ability' => 'purchases.list',
                    'group' => 'purchases',
                    'actions' => [
                        'cmpbtn01', 'cmpbtn02', 'cmpbtn03', 'cmpbtn04',
                        'cmpbtn05', 'cmpbtn06', 'cmpbtn07', 'cmpbtn08',
                        'cmpbtn09',
                    ],
                ],
                'rct' => [
                    'label' => 'Costeo de productos',
                    'icon' => 'chart-bar',
                    'route' => 'farma.reportes.costeo',
                    'route-pattern' => 'farma.reportes.costeo',
                    'ability' => 'cost-reports.list',
                    'group' => 'reports',
                    'actions' => ['rctbtn01', 'rctbtn02'],
                ],
                'rcp' => [
                    'label' => 'Reporte de compras',
                    'icon' => 'document-chart-bar',
                    'route' => 'farma.reportes.compras',
                    'route-pattern' => 'farma.reportes.compras',
                    'ability' => 'purchase-reports.list',
                    'group' => 'reports',
                    'actions' => ['rcpbtn01', 'rcpbtn02', 'rcpbtn03', 'rcpbtn04'],
                ],
                'ctp' => [
                    'label' => 'Tipos de compra',
                    'icon' => 'credit-card',
                    'route' => 'farma.compras.tipos-compra',
                    'route-pattern' => 'farma.compras.tipos-compra',
                    'ability' => 'purchase-catalogs.tipos-compra.list',
                    'group' => 'purchases',
                    'actions' => ['ctpbtn01', 'ctpbtn02', 'ctpbtn03', 'ctpbtn04', 'ctpbtn05'],
                ],
                'ctr' => [
                    'label' => 'Pagos y notas',
                    'icon' => 'banknotes',
                    'route' => 'farma.compras.transacciones',
                    'route-pattern' => 'farma.compras.transacciones',
                    'ability' => 'purchase-transactions.list',
                    'group' => 'purchases',
                    'actions' => [
                        'ctrbtn01', 'ctrbtn02', 'ctrbtn03', 'ctrbtn04',
                        'ctrbtn05', 'ctrbtn06', 'ctrbtn07',
                    ],
                ],
                'ttp' => [
                    'label' => 'Tipos de transacción',
                    'icon' => 'arrows-right-left',
                    'route' => 'farma.compras.tipos-transaccion',
                    'route-pattern' => 'farma.compras.tipos-transaccion',
                    'ability' => 'purchase-catalogs.tipos-transaccion.list',
                    'group' => 'purchases',
                    'actions' => ['ttpbtn01', 'ttpbtn02', 'ttpbtn03', 'ttpbtn04', 'ttpbtn05'],
                ],
                'drg' => [
                    'label' => 'Droguerías',
                    'icon' => 'building-storefront',
                    'route' => null,
                    'actions' => [
                        'drgbnt01', 'drgbnt02', 'drgbnt03',
                        'drgbnt04', 'drgbnt05',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capacidades implementadas
    |--------------------------------------------------------------------------
    |
    | Son alias legibles para las pantallas que ya existen. Cuando se
    | construya una vista futura se agrega aquí su mapa de operaciones.
    |
    */
    'abilities' => [
        'cost-reports' => [
            'view' => ['frm', 'rct'],
            'list' => ['frm', 'rct', 'rctbtn01'],
            'products' => ['frm', 'rct', 'rctbtn02'],
        ],
        'purchase-reports' => [
            'view' => ['frm', 'rcp'],
            'list' => ['frm', 'rcp', 'rcpbtn01'],
            'providers' => ['frm', 'rcp', 'rcpbtn02'],
            'payments' => ['frm', 'rcp', 'rcpbtn03'],
            'export' => ['frm', 'rcp', 'rcpbtn04'],
        ],
        'tasks' => ['list' => ['frm', 'tar', 'tarbtn01'], 'create' => ['frm', 'tar', 'tarbtn02'], 'show' => ['frm', 'tar', 'tarbtn03'],
            'update' => ['frm', 'tar', 'tarbtn04'], 'cancel' => ['frm', 'tar', 'tarbtn05'], 'filter' => ['frm', 'tar', 'tarbtn06']],
        'inventory-matrix' => [
            'view' => ['frm', 'miv'], 'list' => ['frm', 'miv', 'mivbtn01'],
            'filter' => ['frm', 'miv', 'mivbtn02'],
        ],
        'inventory-control' => [
            'view' => ['frm', 'civ'], 'list' => ['frm', 'civ', 'civbtn01'],
            'show' => ['frm', 'civ', 'civbtn02'], 'filter' => ['frm', 'civ', 'civbtn03'],
        ],
        'discountspos' => [
            'view' => ['pos', 'dcp'],
            'list' => ['pos', 'dcp', 'dcpbtn01'],
            'create' => ['pos', 'dcp', 'dcpbtn02'],
            'search' => ['pos', 'dcp', 'dcpbtn03'],
            'update' => ['pos', 'dcp', 'dcpbtn04'],
            'deactivate' => ['pos', 'dcp', 'dcpbtn05'],
            'delete' => ['pos', 'dcp', 'dcpbtn06'],
        ],
        'purchasespos' => [
            'view' => ['pos', 'cpp'],
            'list' => ['pos', 'cpp', 'cppbtn01'],
            'filter' => ['pos', 'cpp', 'cppbtn02'],
            'show' => ['pos', 'cpp', 'cppbtn03'],
        ],
        'usuariospos' => [
            'view' => ['pos', 'usp'],
            'list' => ['pos', 'usp', 'uspbtn01'],
            'create' => ['pos', 'usp', 'uspbtn02'],
            'search' => ['pos', 'usp', 'uspbtn03'],
            'update' => ['pos', 'usp', 'uspbtn04'],
            'deactivate' => ['pos', 'usp', 'uspbtn05'],
        ],
        'rolespos' => [
            'view' => ['pos', 'rsp'],
            'list' => ['pos', 'rsp', 'rspbtn01'],
            'create' => ['pos', 'rsp', 'rspbtn02'],
            'search' => ['pos', 'rsp', 'rspbtn03'],
            'update' => ['pos', 'rsp', 'rspbtn04'],
            'deactivate' => ['pos', 'rsp', 'rspbtn05'],
        ],
        'catalogospos' => [
            'view' => ['pos', 'cop'],
            'list' => ['pos', 'cop', 'copbtn01'],
            'create' => ['pos', 'cop', 'copbtn02'],
            'search' => ['pos', 'cop', 'copbtn03'],
            'update' => ['pos', 'cop', 'copbtn04'],
            'deactivate' => ['pos', 'cop', 'copbtn05'],
        ],
        'clientepos' => [
            'view' => ['pos', 'clp'],
            'list' => ['pos', 'clp', 'clpbtn01'],
            'create' => ['pos', 'clp', 'clpbtn02'],
            'search' => ['pos', 'clp', 'clpbtn03'],
            'update' => ['pos', 'clp', 'clpbtn04'],
            'delete' => ['pos', 'clp', 'clpbtn05'],
        ],
        'worklunch' => [
            'work' => ['aht', 'eswl', 'eswlbtn01'],
            'lunch' => ['aht', 'eswl', 'eswlbtn02'],
        ],

        'config' => [
            'module' => ['cfg'],
        ],

        'users' => [
            'view' => ['cfg', 'usr'],
            'create' => ['cfg', 'usr', 'usrbnt01'],
            'list' => ['cfg', 'usr', 'usrbnt02'],
            'search' => ['cfg', 'usr', 'usrbnt03'],
            'delete' => ['cfg', 'usr', 'usrbnt04'],
            'update' => ['cfg', 'usr', 'usrbnt05'],
            'roles' => ['cfg', 'usr', 'usrbtn06'],
            'states' => ['cfg', 'usr', 'usrbtn07'],
            'work-hours' => ['cfg', 'usr', 'usrbtn08'],
            'create-work-hours' => ['cfg', 'usr', 'usrbtn09'],
            'update-work-hours' => ['cfg', 'usr', 'usrbtn10'],
        ],

        'modules' => [
            'view' => ['cfg', 'mdl'],
            'create' => ['cfg', 'mdl', 'mdlbtn01'],
            'list' => ['cfg', 'mdl', 'mdlbtn02'],
            'search' => ['cfg', 'mdl', 'mdlbtn03'],
            'delete' => ['cfg', 'mdl', 'mdlbtn04'],
            'update' => ['cfg', 'mdl', 'mdlbtn05'],
            'states' => ['cfg', 'mdl', 'mdlbtn06'],
        ],

        'views' => [
            'view' => ['cfg', 'vs'],
            'create' => ['cfg', 'vs', 'vsbtn01'],
            'list' => ['cfg', 'vs', 'vsbtn02'],
            'update' => ['cfg', 'vs', 'vsbtn03'],
            'search' => ['cfg', 'vs', 'vsbtn04'],
            'actions' => ['cfg', 'vs', 'vsbtn05'],
            'create-action' => ['cfg', 'vs', 'vsbtn06'],
            'delete-action' => ['cfg', 'vs', 'vsbtn07'],
            'delete' => ['cfg', 'vs', 'vsbtn08'],
            'modules' => ['cfg', 'vs', 'vsbtn09'],
            'by-module' => ['cfg', 'vs', 'vsbtn10'],
            'states' => ['cfg', 'vs', 'vsbtn11'],
        ],

        'stocks' => [
            'view' => ['cfg', 'stk'],
            'create' => ['cfg', 'stk', 'stkbnt01'],
            'list' => ['cfg', 'stk', 'stkbnt02'],
            'search' => ['cfg', 'stk', 'stkbnt03'],
            'update' => ['cfg', 'stk', 'stkbnt04'],
            'delete' => ['cfg', 'stk', 'stkbtn05'],
            'states' => ['cfg', 'stk', 'stkbtn06'],
        ],

        'licenses' => [
            'list' => ['cfg', 'lic', 'licbtn01'], 'generate' => ['cfg', 'lic', 'licbtn02'],
            'update' => ['cfg', 'lic', 'licbtn03'], 'detail' => ['cfg', 'lic', 'licbtn04'], 'unlink' => ['cfg', 'lic', 'licbtn05'],
            'delete' => ['cfg', 'lic', 'licbtn06'],
        ],
        'desktop-releases' => [
            'list' => ['cfg', 'upd', 'updbtn01'], 'create' => ['cfg', 'upd', 'updbtn02'],
            'edit' => ['cfg', 'upd', 'updbtn03'], 'pilots' => ['cfg', 'upd', 'updbtn04'],
            'pilot' => ['cfg', 'upd', 'updbtn05'], 'stable' => ['cfg', 'upd', 'updbtn06'],
            'withdraw' => ['cfg', 'upd', 'updbtn07'], 'audit' => ['cfg', 'upd', 'updbtn08'],
        ],
        'devices' => [
            'view' => ['cfg', 'dvc'],
            'create' => ['cfg', 'dvc', 'dvcbtn01'],
            'list' => ['cfg', 'dvc', 'dvcbtn02'],
            'search' => ['cfg', 'dvc', 'dvcbnt03'],
            'update' => ['cfg', 'dvc', 'dvcbnt04'],
            'delete' => ['cfg', 'dvc', 'dvcbnt05'],
            'states' => ['cfg', 'dvc', 'dvcbnt06'],
            'stocks' => ['cfg', 'dvc', 'dvcbtn07'],
        ],

        'providers' => [
            'view' => ['frm', 'prv'],
            'list' => ['frm', 'prv', 'prvbtn01'],
            'create' => ['frm', 'prv', 'prvbtn02'],
            'search' => ['frm', 'prv', 'prvbtn03'],
            'update' => ['frm', 'prv', 'prvbtn04'],
            'delete' => ['frm', 'prv', 'prvbtn05'],
            'image' => ['frm', 'prv', 'prvbtn06'],
        ],

        'laboratories' => [
            'view' => ['frm', 'lbt'],
            'list' => ['frm', 'lbt', 'lbtbtn01'],
            'create' => ['frm', 'lbt', 'lbtbtn02'],
            'search' => ['frm', 'lbt', 'lbtbtn03'],
            'update' => ['frm', 'lbt', 'lbtbtn04'],
            'delete' => ['frm', 'lbt', 'lbtbtn05'],
            'image' => ['frm', 'lbt', 'lbtbtn06'],
        ],

        'products' => [
            'view' => ['frm', 'prd'],
            'list' => ['frm', 'prd', 'prdbtn01'],
            'create' => ['frm', 'prd', 'prdbtn02'],
            'create-child' => ['frm', 'prd', 'prdbtn19'],
            'search' => ['frm', 'prd', 'prdbtn03'],
            'update' => ['frm', 'prd', 'prdbtn04'],
            'delete' => ['frm', 'prd', 'prdbtn05'],
            'image' => ['frm', 'prd', 'prdbtn06'],
            'options' => ['frm', 'prd', 'prdbtn07'],
            'active-list' => ['frm', 'prd', 'prdbtn08'],
            'active-create' => ['frm', 'prd', 'prdbtn09'],
            'active-search' => ['frm', 'prd', 'prdbtn10'],
            'active-update' => ['frm', 'prd', 'prdbtn11'],
            'active-delete' => ['frm', 'prd', 'prdbtn12'],
        ],

        'product-catalogs' => [
            'unidades' => [
                'view' => ['frm', 'pud'],
                'list' => ['frm', 'pud', 'pudbtn01'],
                'create' => ['frm', 'pud', 'pudbtn02'],
                'search' => ['frm', 'pud', 'pudbtn03'],
                'update' => ['frm', 'pud', 'pudbtn04'],
                'delete' => ['frm', 'pud', 'pudbtn05'],
            ],
            'estados' => [
                'view' => ['frm', 'pes'],
                'list' => ['frm', 'pes', 'pesbtn01'],
                'create' => ['frm', 'pes', 'pesbtn02'],
                'search' => ['frm', 'pes', 'pesbtn03'],
                'update' => ['frm', 'pes', 'pesbtn04'],
                'delete' => ['frm', 'pes', 'pesbtn05'],
            ],
            'categorias' => [
                'view' => ['frm', 'pct'],
                'list' => ['frm', 'pct', 'pctbtn01'],
                'create' => ['frm', 'pct', 'pctbtn02'],
                'search' => ['frm', 'pct', 'pctbtn03'],
                'update' => ['frm', 'pct', 'pctbtn04'],
                'delete' => ['frm', 'pct', 'pctbtn05'],
            ],
            'presentaciones' => [
                'view' => ['frm', 'fpr'],
                'list' => ['frm', 'fpr', 'fprbtn01'],
                'create' => ['frm', 'fpr', 'fprbtn02'],
                'search' => ['frm', 'fpr', 'fprbtn03'],
                'update' => ['frm', 'fpr', 'fprbtn04'],
                'delete' => ['frm', 'fpr', 'fprbtn05'],
            ],
            'administraciones' => [
                'view' => ['frm', 'fad'],
                'list' => ['frm', 'fad', 'fadbtn01'],
                'create' => ['frm', 'fad', 'fadbtn02'],
                'search' => ['frm', 'fad', 'fadbtn03'],
                'update' => ['frm', 'fad', 'fadbtn04'],
                'delete' => ['frm', 'fad', 'fadbtn05'],
            ],
            'familias' => [
                'view' => ['frm', 'fam'],
                'list' => ['frm', 'fam', 'fambtn01'],
                'create' => ['frm', 'fam', 'fambtn02'],
                'search' => ['frm', 'fam', 'fambtn03'],
                'update' => ['frm', 'fam', 'fambtn04'],
                'delete' => ['frm', 'fam', 'fambtn05'],
            ],
            'concentraciones' => [
                'view' => ['frm', 'cnc'],
                'list' => ['frm', 'cnc', 'cncbtn01'],
                'create' => ['frm', 'cnc', 'cncbtn02'],
                'search' => ['frm', 'cnc', 'cncbtn03'],
                'update' => ['frm', 'cnc', 'cncbtn04'],
                'delete' => ['frm', 'cnc', 'cncbtn05'],
            ],
            'principios-activos' => [
                'view' => ['frm', 'pac'],
                'list' => ['frm', 'pac', 'pacbtn01'],
                'create' => ['frm', 'pac', 'pacbtn02'],
                'search' => ['frm', 'pac', 'pacbtn03'],
                'update' => ['frm', 'pac', 'pacbtn04'],
                'delete' => ['frm', 'pac', 'pacbtn05'],
            ],
        ],

        'purchases' => [
            'view' => ['frm', 'cmp'],
            'list' => ['frm', 'cmp', 'cmpbtn01'],
            'create' => ['frm', 'cmp', 'cmpbtn02'],
            'search' => ['frm', 'cmp', 'cmpbtn03'],
            'update' => ['frm', 'cmp', 'cmpbtn04'],
            'delete' => ['frm', 'cmp', 'cmpbtn05'],
            'document' => ['frm', 'cmp', 'cmpbtn06'],
            'options' => ['frm', 'cmp', 'cmpbtn07'],
            'report' => ['frm', 'cmp', 'cmpbtn08'],
            'kardex' => ['frm', 'cmp', 'cmpbtn09'],
        ],

        'expiry' => [
            'list' => ['frm', 'vnc', 'vncbtn01'],
            'lots' => ['frm', 'vnc', 'vncbtn02'],
            'verify' => ['frm', 'vnc', 'vncbtn03'],
        ],

        'supplier-rules' => [
            'list' => ['frm', 'rdp', 'rdpbtn01'],
            'save' => ['frm', 'rdp', 'rdpbtn02'],
        ],

        'lots' => [
            'view' => ['frm', 'lts'],
            'list' => ['frm', 'lts', 'ltsbtn01'],
            'detail' => ['frm', 'lts', 'ltsbtn02'],
        ],

        'distribution' => [
            'view' => ['frm', 'dst'],
            'list' => ['frm', 'dst', 'dstbtn01'],
            'detail' => ['frm', 'dst', 'dstbtn02'],
            'save' => ['frm', 'dst', 'dstbtn03'],
        ],

        'transfer-types' => [
            'view' => ['frm', 'tft'],
            'list' => ['frm', 'tft', 'tftbtn01'],
            'create' => ['frm', 'tft', 'tftbtn02'],
            'search' => ['frm', 'tft', 'tftbtn03'],
            'update' => ['frm', 'tft', 'tftbtn04'],
            'delete' => ['frm', 'tft', 'tftbtn05'],
        ],

        'transfers' => [
            'view' => ['frm', 'trf'],
            'list' => ['frm', 'trf', 'trfbtn01'],
            'options' => ['frm', 'trf', 'trfbtn02'],
            'lots' => ['frm', 'trf', 'trfbtn03'],
            'send' => ['frm', 'trf', 'trfbtn04'],
            'receive' => ['frm', 'trf', 'trfbtn05'],
            'search' => ['frm', 'trf', 'trfbtn06'],
            'filter-stocks' => ['frm', 'trf', 'trfbtn07'],
        ],

        'purchase-transactions' => [
            'view' => ['frm', 'ctr'],
            'list' => ['frm', 'ctr', 'ctrbtn01'],
            'create' => ['frm', 'ctr', 'ctrbtn02'],
            'search' => ['frm', 'ctr', 'ctrbtn03'],
            'update' => ['frm', 'ctr', 'ctrbtn04'],
            'delete' => ['frm', 'ctr', 'ctrbtn05'],
            'document' => ['frm', 'ctr', 'ctrbtn06'],
            'options' => ['frm', 'ctr', 'ctrbtn07'],
        ],

        'purchase-catalogs' => [
            'tipos-compra' => [
                'view' => ['frm', 'ctp'],
                'list' => ['frm', 'ctp', 'ctpbtn01'],
                'create' => ['frm', 'ctp', 'ctpbtn02'],
                'search' => ['frm', 'ctp', 'ctpbtn03'],
                'update' => ['frm', 'ctp', 'ctpbtn04'],
                'delete' => ['frm', 'ctp', 'ctpbtn05'],
            ],
            'tipos-transaccion' => [
                'view' => ['frm', 'ttp'],
                'list' => ['frm', 'ttp', 'ttpbtn01'],
                'create' => ['frm', 'ttp', 'ttpbtn02'],
                'search' => ['frm', 'ttp', 'ttpbtn03'],
                'update' => ['frm', 'ttp', 'ttpbtn04'],
                'delete' => ['frm', 'ttp', 'ttpbtn05'],
            ],
        ],

        'draws' => [
            'view' => ['srts', 'strl'],
            'create' => ['srts', 'strl', 'strlbnt01'],
            'list' => ['srts', 'strl', 'strlbnt02'],
            'search' => ['srts', 'strl', 'strlbnt03'],
            'update' => ['srts', 'strl', 'strlbnt04'],
            'delete' => ['srts', 'strl', 'strlbnt05'],
            'states' => ['srts', 'strl', 'strlbnt06'],
        ],

        'tickets' => [
            'view' => ['srts', 'tks'],
            'list' => ['srts', 'tks', 'tksbnt01'],
            'create' => ['srts', 'tks', 'tksbnt02'],
            'current-promotion' => ['srts', 'tks', 'tksbnt03'],
            'clients' => ['srts', 'tks', 'tksbnt04'],
            'search-clients' => ['srts', 'tks', 'tksbnt05'],
            'check-phone' => ['srts', 'tks', 'tksbnt06'],
            'create-client' => ['srts', 'tks', 'tksbtn07'],
            'update-phone' => ['srts', 'tks', 'tksbnt08'],
            'departments' => ['srts', 'tks', 'tksbnt09'],
            'municipalities' => ['srts', 'tks', 'tksbtn10'],
            'copies' => ['srts', 'tks', 'tksbnt11'],
        ],

        'reports' => [
            'draws' => [
                'view' => ['rpt', 'rptstr'],
                'filter' => ['rpt', 'rptstr', 'rptstrbtn01'],
                'filter-draws' => ['rpt', 'rptstr', 'rptstrbnt02'],
                'filter-clients' => ['rpt', 'rptstr', 'rptstrbtn03'],
                'filter-users' => ['rpt', 'rptstr', 'rptstrbnt04'],
                'filter-stocks' => ['rpt', 'rptstr', 'rptstrbnt05'],
                'list-stocks' => ['rpt', 'rptstr', 'rptstrbtn06'],
                'list-clients' => ['rpt', 'rptstr', 'rptstrbnt07'],
                'list-users' => ['rpt', 'rptstr', 'rptstrbtn08'],
                'list-draws' => ['rpt', 'rptstr', 'rptstrbtn09'],
                'export' => ['rpt', 'rptstr', 'rptstrbtn10'],
            ],
            'worklunch' => [
                'view' => ['rpt', 'rptwl'],
                'filter' => ['rpt', 'rptwl', 'rptwlbtn01'],
                'filter-users' => ['rpt', 'rptwl', 'rptwlbtn02'],
                'list-users' => ['rpt', 'rptwl', 'rptwlbtn03'],
                'export' => ['rpt', 'rptwl', 'rptwlbtn04'],
            ],
        ],

        'permissions' => [
            'view' => ['cfg', 'prm'],
            'list' => ['cfg', 'prm', 'prmbtn08'],
            'create' => ['cfg', 'prm', 'prmbtn09'],
            'delete' => ['cfg', 'prm', 'prmbtn10'],
            'modules' => ['cfg', 'prm', 'prmbtn11'],
            'views' => ['cfg', 'prm', 'prmbtn12'],
            'actions' => ['cfg', 'prm', 'prmbtn13'],
            'time-types' => ['cfg', 'prm', 'prmbtn14'],
        ],
    ],
];
