<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;

use function is_array;
use function is_string;

class PurchaseCatalogApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessionGuard) {}

    public function list(string $catalog, int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $response = $this->send($catalog, 'get', '/listar', ['page' => max(1, $page)] + (trim($search) === '' ? [] : ['search' => trim($search)]), 'list');
        $meta = $response->meta;

        if ($meta === null && is_array($response->data) && ! array_is_list($response->data)) {
            $meta = is_array($response->data['meta'] ?? null) ? $response->data['meta'] : $response->data;
        }

        return PaginatedCollectionData::fromApi($this->collection($response->data), $meta);
    }

    public function create(string $catalog, string $description): string
    {
        return $this->send($catalog, 'post', '/crear', ['descripcion' => $description], 'create')->message;
    }

    /** @return array<string, mixed> */
    public function find(string $catalog, int $id): array
    {
        return $this->record($this->send($catalog, 'get', '/buscar/'.$this->id($id), [], 'search')->data);
    }

    public function update(string $catalog, int $id, string $description): string
    {
        return $this->send($catalog, 'put', '/actualizar', ['id' => $this->id($id), 'descripcion' => $description], 'update')->message;
    }

    public function delete(string $catalog, int $id): string
    {
        return $this->send($catalog, 'delete', '/eliminar/'.$this->id($id), [], 'delete')->message;
    }

    /** @param array<string, mixed> $payload */
    private function send(string $catalog, string $method, string $suffix, array $payload, string $action): ApiResponseData
    {
        $definition = config('farma_purchase_catalogs.'.$catalog);

        if (! is_array($definition) || ! is_string($definition['actions'][$action] ?? null)) {
            throw new InvalidArgumentException('El catálogo de compras no está configurado.');
        }

        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $request = $this->client->request()->withToken($token)->withHeader('Action-Btn', $definition['actions'][$action]);
            $response = $this->dispatch($request, $method, $this->path($catalog, $suffix), $payload);
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
            throw new ApiOperationException($envelope->message ?: 'La operación no pudo completarse.', is_array(($body['errors'] ?? data_get($body, 'data.errors'))) ? ($body['errors'] ?? data_get($body, 'data.errors')) : [], $code);
        }

        return $envelope;
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(PendingRequest $request, string $method, string $path, array $payload): Response
    {
        return match ($method) {
            'get' => $request->get($path, $payload), 'post' => $request->post($path, $payload),
            'put' => $request->put($path, $payload), 'delete' => $request->delete($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $catalog, string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.purchase_catalog_path', '/farma/compras/catalogos');

        return '/'.trim($prefix, '/').'/'.$catalog.'/'.ltrim($suffix, '/');
    }

    /** @return array<int, array<string, mixed>> */
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

    /** @return array<string, mixed> */
    private function record(mixed $data): array
    {
        if (! is_array($data)) {
            throw new ApiOperationException('La API no devolvió el registro.');
        }
        foreach (['data', 'record', 'registro'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key]);
            }
        }

        return $data;
    }

    private function id(int $id): int
    {
        if ($id < 1) {
            throw new ApiOperationException('El identificador no es válido.');
        }

        return $id;
    }
}
