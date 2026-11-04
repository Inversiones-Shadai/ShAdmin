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

class DrawApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function list(int $page = 1): PaginatedCollectionData
    {
        return $this->paginated(
            $this->send('get', '/listar', ['page' => max(1, $page)]),
        );
    }

    public function filter(string $term, int $page = 1): PaginatedCollectionData
    {
        $term = trim($term);
        $likeTerm = "%{$term}%";

        return $this->paginated(
            $this->send('get', '/filter', [
                'name' => 'lista-sorteos',
                'filterItems' => [
                    [
                        'key' => 'nombre',
                        'value' => $likeTerm,
                        'operator' => 'LIKE',
                        'logicalOperator' => 'OR',
                    ],
                    [
                        'key' => 'descripcion',
                        'value' => $likeTerm,
                        'operator' => 'LIKE',
                        'logicalOperator' => 'OR',
                    ],
                ],
                'logicalOperator' => 'OR',
                'page' => max(1, $page),
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): string
    {
        return $this->send('post', '/crear', $this->withNestedState($payload))->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function find(int $id): array
    {
        return $this->record(
            $this->send('get', "/buscar/{$id}")->data,
            ['draw', 'sorteo', 'promotion', 'promocion'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(array $payload): string
    {
        return $this->send('put', '/actualizar', $this->withNestedState($payload))->message;
    }

    public function delete(int $id): string
    {
        return $this->send('delete', "/eliminar/{$id}")->message;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function states(): array
    {
        return $this->collection(
            $this->send('get', '/estados')->data,
            ['states', 'estados'],
        );
    }

    private function paginated(ApiResponseData $response): PaginatedCollectionData
    {
        $meta = $response->meta;

        if ($meta === null && is_array($response->data) && ! array_is_list($response->data)) {
            $meta = is_array($response->data['meta'] ?? null)
                ? $response->data['meta']
                : $response->data;
        }

        return PaginatedCollectionData::fromApi(
            $this->collection($response->data, ['draws', 'sorteos', 'promotions', 'promociones']),
            $meta,
        );
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
            $request = $this->client->request()->withToken($token);
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
            'put' => $request->put($path, $payload),
            'delete' => $request->delete($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.draw_path', '/sorteos/sorteo');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withNestedState(array $payload): array
    {
        if (array_key_exists('estado', $payload) && ! is_array($payload['estado'])) {
            $payload['estado'] = ['id' => $payload['estado']];
        }

        return $payload;
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

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function record(mixed $data, array $keys): array
    {
        if (! is_array($data)) {
            throw new ApiOperationException('La API no devolvió los datos del sorteo.');
        }

        if (array_is_list($data)) {
            $record = $data[0] ?? null;

            if (is_array($record)) {
                return $record;
            }
        }

        foreach ([...$keys, 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key], $keys);
            }
        }

        return $data;
    }
}
