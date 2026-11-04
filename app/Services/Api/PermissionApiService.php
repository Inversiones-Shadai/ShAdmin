<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

use function is_array;
use function is_string;

class PermissionApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function listForUser(int $userId, int $page = 1): PaginatedCollectionData
    {
        $response = $this->send('get', "/listarpermisos/{$userId}", [
            'page' => max(1, $page),
        ]);
        $meta = $response->meta;

        if ($meta === null && is_array($response->data) && ! array_is_list($response->data)) {
            $meta = is_array($response->data['meta'] ?? null)
                ? $response->data['meta']
                : $response->data;
        }

        return PaginatedCollectionData::fromApi(
            $this->collection($response->data),
            $meta,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function modules(): array
    {
        return $this->collection($this->send('get', '/listarmodulos')->data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function views(int $moduleId): array
    {
        return $this->collection($this->send('get', "/listarVistas/{$moduleId}")->data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(int $viewId): array
    {
        return $this->collection($this->send('get', "/listarAcciones/{$viewId}")->data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timeTypes(): array
    {
        return $this->collection($this->send('get', '/tipoTiempo')->data);
    }

    /**
     * @param  array<string, int|string>  $payload
     */
    public function create(array $payload): string
    {
        $response = $this->send('post', '/crearpermiso', $this->nestedPayload($payload));

        return $response->message;
    }

    public function delete(int $permissionId): string
    {
        return $this->send('delete', "/eliminarpermiso/{$permissionId}")->message;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload = []): ApiResponseData
    {
        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $request = $this->client
                ->request()
                ->withToken($token);

            $response = $this->dispatch($request, $method, $this->path($path), $payload);
        } catch (ApiConfigurationException $exception) {
            throw new ApiOperationException($exception->getMessage());
        } catch (ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor.');
        }

        $body = $response->json();
        $this->sessionGuard->ensureActive($response, $body);

        if (! is_array($body)) {
            throw new ApiOperationException('La API devolvió una respuesta inválida.', status: $response->status());
        }

        $envelope = ApiResponseData::fromApi($body);
        $code = $envelope->code > 0 ? $envelope->code : $response->status();

        if (! $response->successful() || $code < 200 || $code >= 300) {
            $errors = is_array(($body['errors'] ?? data_get($body, 'data.errors'))) ? ($body['errors'] ?? data_get($body, 'data.errors')) : [];

            throw new ApiOperationException(
                $envelope->message !== '' ? $envelope->message : 'La operación no pudo completarse.',
                $errors,
                $code,
            );
        }

        return $envelope;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(PendingRequest $request, string $method, string $path, array $payload): Response
    {
        return match ($method) {
            'get' => $request->get($path, $payload),
            'post' => $request->post($path, $payload),
            'delete' => $request->delete($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.permission_path', '/config/permisos');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collection(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, is_array(...)));
        }

        foreach (['data', 'items', 'results'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->collection($data[$key]);
            }
        }

        return [];
    }

    /**
     * @param  array<string, int|string>  $payload
     * @return array<string, array{id: int|string}>
     */
    private function nestedPayload(array $payload): array
    {
        $nested = [];

        foreach (['usuario', 'modulo', 'vista', 'actionvista', 'tipo_tiempo'] as $field) {
            $nested[$field] = ['id' => $payload[$field]];
        }

        return $nested;
    }
}
