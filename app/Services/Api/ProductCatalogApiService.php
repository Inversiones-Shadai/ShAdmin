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

class ProductCatalogApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function list(string $catalog, int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $response = $this->send(
            $catalog,
            'get',
            '/listar',
            ['page' => max(1, $page)] + (trim($search) === '' ? [] : ['search' => trim($search)]),
            'list',
        );
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

    /** @return array<int, array<string, mixed>> */
    public function listAll(string $catalog): array
    {
        $records = [];
        $page = 1;

        do {
            $result = $this->list($catalog, $page);
            $records = [...$records, ...$result->items];
            $page++;
        } while ($page <= $result->lastPage && $page <= 100);

        return $records;
    }

    /** @param array<string, mixed> $payload */
    public function create(string $catalog, array $payload): string
    {
        return $this->send($catalog, 'post', '/crear', $payload, 'create')->message;
    }

    /** @return array<string, mixed> */
    public function find(string $catalog, int $id): array
    {
        if ($id < 1) {
            throw new ApiOperationException('El identificador no es válido.');
        }

        return $this->record(
            $this->send($catalog, 'get', "/buscar/{$id}", [], 'search')->data,
        );
    }

    /** @param array<string, mixed> $payload */
    public function update(string $catalog, int $id, array $payload): string
    {
        if ($id < 1) {
            throw new ApiOperationException('El identificador no es válido.');
        }

        $payload['id'] = $id;

        $operation = in_array($catalog, ['familias', 'concentraciones', 'principios-activos'], true) ? 'update' : 'actualizar';

        return $this->send($catalog, 'put', "/{$operation}/{$id}", $payload, 'update')->message;
    }

    public function delete(string $catalog, int $id): string
    {
        if ($id < 1) {
            throw new ApiOperationException('El identificador no es válido.');
        }

        return $this->send($catalog, 'delete', "/eliminar/{$id}", [], 'delete')->message;
    }

    /** @param array<string, mixed> $payload */
    private function send(
        string $catalog,
        string $method,
        string $path,
        array $payload,
        string $action,
    ): ApiResponseData {
        $definition = $this->definition($catalog);
        $actionCode = $definition['actions'][$action] ?? null;

        if (! is_string($actionCode) || $actionCode === '') {
            throw new InvalidArgumentException('La acción del catálogo no está configurada.');
        }

        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $request = $this->client->request()
                ->withToken($token)
                ->withHeader('Action-Btn', $actionCode);
            $response = $this->dispatch(
                $request,
                $method,
                $this->path($catalog, $path),
                $payload,
            );
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

    private function path(string $catalog, string $suffix): string
    {
        $prefix = (string) config(
            'services.shadmin_api.product_catalog_path',
            '/farma/productos',
        );

        // The API currently registers concentrations under this nested prefix.
        $segment = $catalog === 'concentraciones' ? 'productos/catalogos/concentraciones' : $catalog;

        return '/'.trim($prefix, '/').'/'.$segment.'/'.ltrim($suffix, '/');
    }

    /** @return array<string, mixed> */
    private function definition(string $catalog): array
    {
        $definition = config("farma_product_catalogs.{$catalog}");

        if (! is_array($definition)) {
            throw new InvalidArgumentException('El catálogo de productos no está configurado.');
        }

        return $definition;
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

        foreach (['data', 'items', 'results', 'catalogs', 'catalogos'] as $key) {
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
            throw new ApiOperationException('La API no devolvió los datos del registro.');
        }

        foreach (['data', 'record', 'registro', 'catalog'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key]);
            }
        }

        return $data;
    }
}
