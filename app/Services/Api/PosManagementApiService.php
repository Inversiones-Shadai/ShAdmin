<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

class PosManagementApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessionGuard) {}

    public function list(string $resource, int $page = 1): PaginatedCollectionData
    {
        $response = $this->send($resource, 'get', '/listar', ['page' => max(1, $page)], 1);
        $data = $response->data;
        $items = is_array($data) ? ($data['data'] ?? $data) : [];

        return PaginatedCollectionData::fromApi(is_array($items) ? array_values(array_filter($items, is_array(...))) : [], $response->meta);
    }

    public function discountChoices(string $type, string $search = '', int $page = 1): PaginatedCollectionData
    {
        $response = $this->send('descuentos', 'get', '/opciones', ['tipo' => $type, 'buscar' => $search, 'page' => max(1, $page)], 2);

        return PaginatedCollectionData::fromApi(is_array($response->data) ? $response->data : [], $response->meta);
    }

    /** @return array<string, mixed> */
    public function find(string $resource, int $id): array
    {
        $data = $this->send($resource, 'get', '/buscar/'.$id, [], 3)->data;
        if (! is_array($data)) {
            throw new ApiOperationException('La API no devolvió el registro POS.');
        }

        return $data;
    }

    /** @param array<string, mixed> $payload */
    public function save(string $resource, ?int $id, array $payload, string $requestId): string
    {
        return $this->send($resource, $id === null ? 'post' : 'put', $id === null ? '/crear' : '/actualizar/'.$id, $payload, $id === null ? 2 : 4, $requestId)->message;
    }

    public function deactivate(string $resource, int $id, string $requestId, array $payload = []): string
    {
        return $this->send($resource, 'delete', '/desactivar/'.$id, $payload, 5, $requestId)->message;
    }

    public function delete(string $resource, int $id, string $requestId, array $payload = []): string
    {
        return $this->send($resource, 'delete', '/eliminar/'.$id, $payload, 6, $requestId)->message;
    }

    /** @return array<int, array<string, mixed>> */
    public function userPermissions(int $user): array
    {
        return $this->permissionChoices('listarpermisos/'.$user);
    }

    /** @return array<int, array<string, mixed>> */
    public function permissionChoices(string $path): array
    {
        $data = $this->send('permisos', 'get', '/'.$path, [], 3)->data;

        return is_array($data) ? array_values(array_filter($data, is_array(...))) : [];
    }

    /** @param array<string, mixed> $payload */
    public function createPermission(array $payload, string $requestId): string
    {
        return $this->send('permisos', 'post', '/crearpermiso', $payload, 4, $requestId)->message;
    }

    public function deletePermission(int $id, string $requestId): string
    {
        return $this->send('permisos', 'delete', '/eliminarpermiso/'.$id, [], 4, $requestId)->message;
    }

    /** @param array<string, mixed> $payload */
    private function send(string $resource, string $method, string $suffix, array $payload, int $action, ?string $requestId = null): ApiResponseData
    {
        $view = match ($resource) {
            'usuarios', 'permisos' => 'usp', 'roles' => 'rsp', 'catalogos' => 'cop', 'descuentos' => 'dcp',
            default => throw new ApiOperationException('Recurso POS no válido.', status: 400),
        };
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente en ShAdmin.', status: 401);
        }
        if ($resource === 'usuarios'
            && (isset($payload['password']) || isset($payload['usuario']))
            && ! app()->environment(['local', 'testing'])
            && parse_url((string) config('services.shadmin_api.base_url'), PHP_URL_SCHEME) !== 'https') {
            throw new ApiOperationException('Configura una URL HTTPS de la API para enviar credenciales POS.', status: 400);
        }
        try {
            $request = $this->client->request()->withToken($token)->withHeader('Action-Btn', $view.'btn0'.$action);
            if ($requestId !== null) {
                $request = $request->withHeader('Idempotency-Key', $requestId);
            }
            $path = '/pos/'.$resource.$suffix;
            $response = match ($method) {
                'get' => $request->get($path, $payload),
                'post' => $request->post($path, $payload),
                'put' => $request->put($path, $payload),
                'delete' => $request->delete($path, $payload),
                default => throw new ApiOperationException('Método no válido.', status: 400),
            };
        } catch (ApiConfigurationException $exception) {
            throw new ApiOperationException($exception->getMessage(), status: 400);
        } catch (ConnectionException) {
            throw new ApiOperationException('No se recibió confirmación del servidor. Reintenta con los mismos datos y contraseña.');
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
            throw new ApiOperationException($envelope->message ?: 'No fue posible completar la operación POS.', is_array($errors) ? $errors : [], $code);
        }

        return $envelope;
    }
}
