<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

class TaskApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    /** @param array<string, mixed> $filters */
    public function list(int $page, array $filters): PaginatedCollectionData
    {
        $result = $this->send('GET', '/listar', ['page' => $page, ...$filters], 'tarbtn01');

        return PaginatedCollectionData::fromApi(is_array($result->data) ? $result->data : [], $result->meta);
    }

    /** @return array<string, mixed> */
    public function options(string $action): array
    {
        $result = $this->send('GET', '/opciones', [], $action);

        return is_array($result->data) ? $result->data : [];
    }

    /** @return array<string, mixed> */
    public function find(int $id, string $action): array
    {
        $result = $this->send('GET', '/buscar/'.$id, [], $action);
        if (! is_array($result->data)) {
            throw new ApiOperationException('No se pudo consultar el detalle de la tarea.', status: 503);
        }

        return $result->data;
    }

    /** @param array<string, mixed> $data */
    public function mutate(string $action, ?int $id, array $data): void
    {
        $permission = match ($action) {
            'crear' => 'tarbtn02', 'modificar' => 'tarbtn04', 'cancelar' => 'tarbtn05', default => throw new ApiOperationException('Acción de tarea inválida.', status: 422)
        };
        $this->send('POST', '/'.$action.($id === null ? '' : '/'.$id), $data, $permission);
    }

    /** @param array<string, mixed> $payload */
    private function send(string $method, string $path, array $payload, string $action): ApiResponseData
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente en ShAdmin.', status: 401);
        }
        try {
            $request = $this->client->request()->withToken($token)->withHeader('Action-Btn', $action);
            $url = '/farma/tareas'.$path;
            $response = $method === 'GET' ? $request->get($url, $payload) : $request->post($url, $payload);
        } catch (ApiConfigurationException|ConnectionException $error) {
            throw new ApiOperationException('No se pudo conectar con el servidor. Reintenta la misma solicitud.', status: 503);
        }
        $body = $response->json();
        $this->sessions->ensureActive($response, $body);
        if (! is_array($body)) {
            throw new ApiOperationException('La API devolvió una respuesta inválida.', status: 503);
        }
        $result = ApiResponseData::fromApi($body);
        if (! $response->successful() || ($result->code > 0 && ($result->code < 200 || $result->code >= 300))) {
            throw new ApiOperationException($result->message ?: 'No se pudo completar la operación.', status: $result->code > 0 ? $result->code : $response->status());
        }

        return $result;
    }
}
