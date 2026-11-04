<?php

namespace App\Services\Api;

use App\Exceptions\ApiConfigurationException;

use function is_string;

class ApiValueHasher
{
    public function hash(string $value): string
    {
        $secret = config('services.shadmin_api.device_header_secret');

        if (! is_string($secret) || $secret === '') {
            throw new ApiConfigurationException('Configura SHADMIN_DEVICE_HEADER_SECRET con la clave compartida de la API.');
        }

        return hash_hmac('sha256', $value, $secret);
    }
}
