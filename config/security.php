<?php

return [
    'rate_limits' => [
        'requests_per_minute' => max(1, (int) env('HTTP_RATE_LIMIT_PER_MINUTE', 300)),
        'login_per_minute' => max(1, (int) env('LOGIN_RATE_LIMIT_PER_MINUTE', 5)),
        'login_ip_per_minute' => max(1, (int) env('LOGIN_IP_RATE_LIMIT_PER_MINUTE', 20)),
    ],
    'headers' => [
        'frame_options' => 'SAMEORIGIN',
        'permissions_policy' => 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',
        'content_security_policy' => "base-uri 'self'; object-src 'none'; frame-ancestors 'self'",
        'hsts_max_age' => max(0, (int) env('HSTS_MAX_AGE', 31536000)),
    ],
];
