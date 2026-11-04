<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

use function is_array;
use function is_string;

class UserApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
        private readonly ApiValueHasher $hasher,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return $this->collection(
            $this->send('get', '/listar')->data,
            ['users', 'usuarios'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): string
    {
        $response = $this->send(
            'post',
            '/crear',
            $this->withNestedCatalogIds($this->withHashedCredentials($payload)),
        );

        return $response->message;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(array $payload): string
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id < 1) {
            throw new ApiOperationException('El identificador no es válido.');
        }

        $response = $this->send('put', "/actualizar/{$id}", $this->withNestedCatalogIds($payload));

        return $response->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function find(int $id): array
    {
        return $this->record(
            $this->send('get', "/buscar/{$id}")->data,
            ['user', 'usuario'],
        );
    }

    public function delete(int $id): string
    {
        $response = $this->send('delete', "/eliminar/{$id}");

        return $response->message;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roles(): array
    {
        return $this->collection(
            $this->send('get', '/roles')->data,
            ['roles'],
        );
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
        $prefix = (string) config('services.shadmin_api.user_path', '/config/user');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withNestedCatalogIds(array $payload): array
    {
        foreach (['rol', 'estado'] as $field) {
            if (array_key_exists($field, $payload) && ! is_array($payload[$field])) {
                $payload[$field] = ['id' => $payload[$field]];
            }
        }

        return $payload;
    }

    /**
     * Aplica la misma transformación utilizada por el login.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withHashedCredentials(array $payload): array
    {
        if (isset($payload['name']) && is_string($payload['name']) && $payload['name'] !== '') {
            $payload['name'] = $this->hasher->hash($payload['name']);
        }

        if (isset($payload['password']) && is_string($payload['password']) && $payload['password'] !== '') {
            $payload['password'] = $this->hasher->hash($payload['password']);

            if (array_key_exists('password_confirmation', $payload)) {
                $payload['password_confirmation'] = $payload['password'];
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
            throw new ApiOperationException('La API no devolvió los datos del usuario.');
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
