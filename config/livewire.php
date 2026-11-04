<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payload Guards
    |--------------------------------------------------------------------------
    |
    | Las imágenes del catálogo se validan en 2 MiB. Durante el ciclo de
    | Livewire la versión saneada viaja en base64, por lo que necesita margen
    | adicional para la codificación y los metadatos del componente.
    |
    */
    'payload' => [
        'max_size' => (int) env('LIVEWIRE_PAYLOAD_MAX_SIZE', 5 * 1024 * 1024),
        'max_nesting_depth' => 10,
        'max_calls' => 50,
        'max_components' => 200,
    ],
];
