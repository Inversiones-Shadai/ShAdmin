<?php

return [
    'disk' => env('IMAGE_STORAGE_DISK', 's3'),
    'prefix' => trim(env('IMAGE_STORAGE_PREFIX', 'shadmin/images'), '/'),
    'url_minutes' => (int) env('IMAGE_STORAGE_URL_MINUTES', 720),
    'logos' => [
        'navigation' => env('IMAGE_LOGO_NAVIGATION_UUID'),
        'ticket' => env('IMAGE_LOGO_TICKET_UUID'),
        'transfer' => env('IMAGE_LOGO_TRANSFER_UUID'),
    ],
];
