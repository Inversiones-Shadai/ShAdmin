<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

class AppConfigApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    public function page(int $page = 1): PaginatedCollectionData
    {
        $response = $this->send('GET', '', ['page' => max(1, $page)], 'acfbtn01');

        return PaginatedCollectionData::fromApi($response->data, $response->meta);
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        return $this->send('GET', '/'.$id, [], 'acfbtn03')->data;
    }

    /** @param array<string, mixed> $payload */
    public function save(?int $id, array $payload): string
    {
        return $this->send($id === null ? 'POST' : 'PUT', $id === null ? '' : '/'.$id, $payload, $id === null ? 'acfbtn02' : 'acfbtn03')->message;
    }

    /** @param array<string, mixed> $payload */
    private function send(string $method, string $path, array $payload, string $action): ApiResponseData
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $request = $this->client->request()->withToken($token)->withHeader('Action-Btn', $action);
            if ($method !== 'GET') {
                $request = $request->withHeader('Idempotency-Key', (string) Str::uuid());
            }
            $response = $request->send($method, '/config/appconfig'.$path, [$method === 'GET' ? 'query' : 'json' => $payload]);
        } catch (ApiConfigurationException $exception) {
            throw new ApiOperationException($exception->getMessage());
        } catch (ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor de configuración.');
        }

        $body = $response->json();
        $this->sessions->ensureActive($response, $body);
        if (! is_array($body)) {
            throw new ApiOperationException('La API devolvió una respuesta inválida.', status: $response->status());
        }

        $result = ApiResponseData::fromApi($body);
        $code = $result->code ?: $response->status();
        if (! $response->successful() || $code < 200 || $code >= 300 || ! is_array($result->data)) {
            $errors = $body['errors'] ?? data_get($body, 'data.errors');
            throw new ApiOperationException($result->message ?: 'No se pudo completar la operación de configuración.', is_array($errors) ? $errors : [], $code);
        }

        return $result;
    }
}
