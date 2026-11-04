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

class LotApiService
{
    /** @var array<string, string> */
    private const ACTIONS = [
        'list' => 'ltsbtn01',
        'detail' => 'ltsbtn02',
    ];

    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function list(int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $query = ['page' => max(1, $page)];
        $search = trim($search);

        if ($search !== '') {
            $query['search'] = mb_substr($search, 0, 100);
        }

        return $this->page($this->send('get', '/listar', $query, 'list'));
    }

    /** @return array<int, array<string, mixed>> */
    public function productLots(int $productId): array
    {
        if ($productId < 1) {
            throw new ApiOperationException('El identificador del producto no es válido.');
        }

        return $this->collection($this->send('get', '/producto/'.$productId, [], 'detail')->data);
    }

    /** @param array<string, mixed> $payload */
    private function send(string $method, string $path, array $payload, string $action): ApiResponseData
    {
        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $request = $this->client->request()->withToken($token)->withHeader('Action-Btn', self::ACTIONS[$action]);
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
            throw new ApiOperationException($envelope->message ?: 'La operación no pudo completarse.', is_array(($body['errors'] ?? data_get($body, 'data.errors'))) ? ($body['errors'] ?? data_get($body, 'data.errors')) : [], $code);
        }

        return $envelope;
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(PendingRequest $request, string $method, string $path, array $payload): Response
    {
        return match ($method) {
            'get' => $request->get($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        return '/'.trim((string) config('services.shadmin_api.lot_path', '/farma/lotes'), '/').'/'.ltrim($suffix, '/');
    }

    private function page(ApiResponseData $response): PaginatedCollectionData
    {
        return PaginatedCollectionData::fromApi($this->collection($response->data), $response->meta);
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

        foreach (['data', 'items', 'results', 'productos', 'lotes'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->collection($data[$key]);
            }
        }

        return [];
    }
}
