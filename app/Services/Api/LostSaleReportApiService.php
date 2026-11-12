<?php

namespace App\Services\Api;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

final class LostSaleReportApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    public function page(int $page, array $filters): PaginatedCollectionData
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente.', status: 401);
        }

        try {
            $response = $this->client->request()->withToken($token)->withHeader('Action-Btn', 'vpdbtn01')
                ->get('/farma/reportes/ventas-perdidas', ['page' => $page, ...$filters]);
        } catch (ApiConfigurationException|ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor.');
        }

        $body = $response->json();
        $this->sessions->ensureActive($response, $body);
        if (! $response->successful() || ! is_array($body['data'] ?? null)) {
            throw new ApiOperationException('No se pudo consultar el reporte de ventas perdidas.', status: $response->status());
        }

        return PaginatedCollectionData::fromApi($body['data'], $body['meta'] ?? null);
    }
}
