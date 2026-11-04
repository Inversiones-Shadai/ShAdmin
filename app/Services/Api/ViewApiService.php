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

class ViewApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function list(int $page = 1, string $search = ''): PaginatedCollectionData
    {
        return $this->paginated('/listar', $page, $search);
    }

    public function listByModule(int $moduleId, string $search = ''): PaginatedCollectionData
    {
        return $this->paginated("/findbymodule/{$moduleId}", 1, $search);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): string
    {
        return $this->send('post', '/crear', $this->withNestedCatalogs($payload))->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function find(int $id): array
    {
        return $this->record(
            $this->send('get', "/buscar/{$id}")->data,
            ['view', 'vista'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(array $payload): string
    {
        $id = (int) ($payload['id'] ?? 0);

        if ($id < 1) {
            throw new ApiOperationException('No se indicó la vista que se desea actualizar.');
        }

        return $this->send(
            'put',
            "/actualizar/{$id}",
            $this->withNestedCatalogs($payload),
        )->message;
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function modules(): array
    {
        return $this->collection(
            $this->send('get', '/listarModulos')->data,
            ['modules', 'modulos'],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(int $viewId): array
    {
        return $this->collection(
            $this->send('get', "/acciones/{$viewId}")->data,
            ['actions', 'acciones'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createAction(array $payload): string
    {
        if (array_key_exists('vista', $payload) && ! is_array($payload['vista'])) {
            $payload['vista'] = ['id' => $payload['vista']];
        }

        return $this->send('post', '/createaction', $payload)->message;
    }

    public function deleteAction(int $id): string
    {
        return $this->send('delete', "/deleteaction/{$id}")->message;
    }

    private function paginated(string $path, int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $response = $this->send('get', $path, [
            'page' => max(1, $page),
            ...(trim($search) === '' ? [] : ['search' => trim($search)]),
        ]);
        $meta = $response->meta;

        if ($meta === null && is_array($response->data) && ! array_is_list($response->data)) {
            $meta = is_array($response->data['meta'] ?? null)
                ? $response->data['meta']
                : $response->data;
        }

        return PaginatedCollectionData::fromApi(
            $this->collection($response->data, ['views', 'vistas']),
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
            'put' => $request->put($path, $payload),
            'delete' => $request->delete($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.view_path', '/config/vista');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withNestedCatalogs(array $payload): array
    {
        foreach (['modulo', 'estado'] as $field) {
            if (array_key_exists($field, $payload) && ! is_array($payload[$field])) {
                $payload[$field] = ['id' => $payload[$field]];
            }
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
            throw new ApiOperationException('La API no devolvió los datos de la vista.');
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
