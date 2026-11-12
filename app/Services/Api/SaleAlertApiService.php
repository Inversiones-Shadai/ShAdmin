<?php

namespace App\Services\Api;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

final class SaleAlertApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    public function page(int $page): PaginatedCollectionData
    {
        $body = $this->send('GET', '', ['page' => $page]);

        return PaginatedCollectionData::fromApi($body['data'], $body['meta'] ?? null);
    }

    public function save(?string $id, array $payload): void
    {
        $this->send($id === null ? 'POST' : 'PUT', $id === null ? '' : '/'.$id, $payload);
    }

    public function remove(string $id, string $revision): void
    {
        $this->send('DELETE', '/'.$id, ['revision' => $revision]);
    }

    private function send(string $method, string $path, array $data): array
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente.', status: 401);
        }
        try {
            $request = $this->client->request()->withToken($token)
                ->withHeader('Action-Btn', $method === 'GET' ? 'avtbtn01' : 'avtbtn02');
            if ($method !== 'GET') {
                $request = $request->withHeader('Idempotency-Key', (string) Str::uuid());
            }
            $response = $request->send($method, '/farma/alertas-venta'.$path, [$method === 'GET' ? 'query' : 'json' => $data]);
        } catch (ApiConfigurationException|ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor de alertas.');
        }
        $body = $response->json();
        $this->sessions->ensureActive($response, $body);
        if (! is_array($body) || ! $response->successful() || (int) ($body['code'] ?? 200) >= 300 || ! is_array($body['data'] ?? null)) {
            $errors = $body['errors'] ?? data_get($body, 'data.errors');
            throw new ApiOperationException($body['message'] ?? 'No se pudo completar la operación de alertas.', is_array($errors) ? $errors : [], $response->status());
        }

        return $body;
    }
}
