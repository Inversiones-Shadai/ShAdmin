<?php

namespace App\Services\Api;

use App\Exceptions\ApiConfigurationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

use function is_string;

class ApiClient
{
    public function __construct(private readonly DeviceHeaderFactory $headers) {}

    public function request(): PendingRequest
    {
        $baseUrl = config('services.shadmin_api.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new ApiConfigurationException('Configura SHADMIN_API_URL antes de conectar con la API.');
        }

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->connectTimeout((int) config('services.shadmin_api.connect_timeout', 5))
            ->timeout((int) config('services.shadmin_api.timeout', 30))
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->headers->headers());
    }
}
