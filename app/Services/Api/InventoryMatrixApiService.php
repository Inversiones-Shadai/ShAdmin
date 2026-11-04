<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

class InventoryMatrixApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessionGuard) {}

    public function list(int $page = 1, array $filters = []): PaginatedCollectionData
    {
        $response = $this->send('/listar', ['page' => max(1, $page), ...$filters], 'mivbtn01');

        return PaginatedCollectionData::fromApi(is_array($response->data) ? $response->data : [], $response->meta);
    }

    public function stocks(): array
    {
        $data = $this->send('/stocks', [], 'mivbtn01')->data;

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $payload */
    private function send(string $path, array $payload, string $action): ApiResponseData
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente en ShAdmin.', status: 401);
        }
        try {
            $response = $this->client->request()->withToken($token)->withHeader('Action-Btn', $action)->get('/farma/stocks/matriz-inventario'.$path, $payload);
        } catch (ApiConfigurationException $exception) {
            throw new ApiOperationException($exception->getMessage());
        } catch (ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor.');
        }
        $body = $response->json();
        $this->sessionGuard->ensureActive($response, $body);
        if (! is_array($body)) {
            throw new ApiOperationException('La API devolvió una respuesta inválida.');
        }
        $envelope = ApiResponseData::fromApi($body);
        $code = $envelope->code > 0 ? $envelope->code : $response->status();
        if (! $response->successful() || $code < 200 || $code >= 300) {
            $errors = $body['errors'] ?? data_get($body, 'data.errors');
            throw new ApiOperationException($envelope->message ?: 'No fue posible consultar la matriz de inventario.', is_array($errors) ? $errors : [], $code);
        }

        return $envelope;
    }
}
