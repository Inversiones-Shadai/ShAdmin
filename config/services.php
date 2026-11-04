<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'shadmin_api' => [
        'base_url' => env('SHADMIN_API_URL'),
        'app_version' => env('SHADMIN_APP_VERSION', '1.0.0'),
        'connect_timeout' => (int) env('SHADMIN_API_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('SHADMIN_API_TIMEOUT', 30),
        'auth_login_path' => env('SHADMIN_AUTH_LOGIN_PATH', '/auth/session/login'),
        'auth_logout_path' => env('SHADMIN_AUTH_LOGOUT_PATH', '/auth/session/logout'),
        'user_path' => env('SHADMIN_USER_PATH', '/config/user'),
        'module_path' => env('SHADMIN_MODULE_PATH', '/config/modulo'),
        'view_path' => env('SHADMIN_VIEW_PATH', '/config/vista'),
        'stock_path' => env('SHADMIN_STOCK_PATH', '/config/stock'),
        'device_path' => env('SHADMIN_DEVICE_PATH', '/config/device'),
        'draw_path' => env('SHADMIN_DRAW_PATH', '/sorteos/sorteo'),
        'ticket_path' => env('SHADMIN_TICKET_PATH', '/sorteos/ticket'),
        'report_draw_path' => env('SHADMIN_REPORT_DRAW_PATH', '/reportes/sorteos'),
        'report_work_lunch_path' => env('SHADMIN_REPORT_WORK_LUNCH_PATH', '/reportes/worklunch'),
        'work_lunch_path' => env('SHADMIN_WORK_LUNCH_PATH', '/auth/worklunch'),
        'work_hours_path' => env('SHADMIN_WORK_HOURS_PATH', '/config/horas-lab'),
        'permission_path' => env('SHADMIN_PERMISSION_PATH', '/config/permisos'),
        'provider_path' => env('SHADMIN_PROVIDER_PATH', '/farma/proveedores'),
        'laboratory_path' => env('SHADMIN_LABORATORY_PATH', '/farma/laboratorios'),
        'clientepos_path' => env('SHADMIN_CLIENTEPOS_PATH', '/pos/clientepos'),
        'product_path' => env('SHADMIN_PRODUCT_PATH', '/farma/productos/producto'),
        'product_lookup_path' => env('SHADMIN_PRODUCT_LOOKUP_PATH', '/farma/productos/producto'),
        'product_catalog_path' => env('SHADMIN_PRODUCT_CATALOG_PATH', '/farma/productos'),
        'purchase_path' => env('SHADMIN_PURCHASE_PATH', '/farma/compras'),
        'lot_path' => env('SHADMIN_LOT_PATH', '/farma/lotes'),
        'distribution_path' => env('SHADMIN_DISTRIBUTION_PATH', '/farma/distribucion'),
        'transfer_path' => env('SHADMIN_TRANSFER_PATH', '/farma/transferencias'),
        'transfer_type_path' => env('SHADMIN_TRANSFER_TYPE_PATH', '/farma/transferencias/tipos'),
        'purchase_catalog_path' => env('SHADMIN_PURCHASE_CATALOG_PATH', '/farma/compras/catalogos'),
        'device_header_secret' => env('SHADMIN_DEVICE_HEADER_SECRET'),
        'device_name' => env('SHADMIN_DEVICE_NAME'),
        'device_ip' => env('SHADMIN_DEVICE_IP'),
    ],

];
