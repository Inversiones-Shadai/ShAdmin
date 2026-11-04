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

class ProviderApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function list(int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $response = $this->send('get', '/listar', ['page' => max(1, $page)] + (trim($search) === '' ? [] : ['search' => trim($search)]), 'prvbtn01');
        $meta = $response->meta;

        if ($meta === null && is_array($response->data) && ! array_is_list($response->data)) {
            $meta = is_array($response->data['meta'] ?? null)
                ? $response->data['meta']
                : $response->data;
        }

        return PaginatedCollectionData::fromApi(
            $this->collection($response->data, ['providers', 'proveedores']),
            $meta,
        );
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): string
    {
        return $this->send('post', '/crear', $payload, 'prvbtn02')->message;
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        return $this->record($this->send('get', "/buscar/{$id}", [], 'prvbtn03')->data);
    }

    /** @param array<string, mixed> $payload */
    public function update(int $id, array $payload): string
    {
        if ($id < 1) {
            throw new ApiOperationException('El identificador del proveedor no es válido.');
        }

        $payload['id'] = $id;

        return $this->send('put', "/actualizar/{$id}", $payload, 'prvbtn04')->message;
    }

    public function delete(int $id): string
    {
        return $this->send('delete', "/eliminar/{$id}", [], 'prvbtn05')->message;
    }

    public function image(int $id): string
    {
        $data = $this->send('get', "/imagen/{$id}", [], 'prvbtn06')->data;

        if (is_array($data) && is_string($data['image'] ?? null)) {
            return $data['image'];
        }

        throw new ApiOperationException('La API no devolvió una imagen válida.');
    }

    /** @param array<string, mixed> $payload */
    private function send(string $method, string $path, array $payload, string $action): ApiResponseData
    {
        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $request = $this->client->request()
                ->withToken($token)
                ->withHeader('Action-Btn', $action);
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
            throw new ApiOperationException(
                $envelope->message !== '' ? $envelope->message : 'La operación no pudo completarse.',
                is_array(($body['errors'] ?? data_get($body, 'data.errors'))) ? ($body['errors'] ?? data_get($body, 'data.errors')) : [],
                $code,
            );
        }

        return $envelope;
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(PendingRequest $request, string $method, string $path, array $payload): Response
    {
        return match ($method) {
            'get' => $request->get($path, $payload),
            'post' => $request->post($path, $payload),
            'put' => $request->put($path, $payload),
            'delete' => $request->delete($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.provider_path', '/farma/proveedores');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function collection(mixed $data, array $keys): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, is_array(...)));
        }

        foreach ([...$keys, 'data', 'items', 'results'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->collection($data[$key], $keys);
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function record(mixed $data): array
    {
        if (! is_array($data)) {
            throw new ApiOperationException('La API no devolvió los datos del proveedor.');
        }

        foreach (['provider', 'proveedor', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key]);
            }
        }

        return $data;
    }
}
