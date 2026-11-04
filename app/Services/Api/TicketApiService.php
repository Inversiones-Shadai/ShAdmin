<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Data\Api\TicketPrintData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

use function is_array;
use function is_string;

class TicketApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function list(int $page = 1): PaginatedCollectionData
    {
        return $this->paginated(
            $this->send('get', '/all', ['page' => max(1, $page)]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function currentPromotion(): array
    {
        return $this->record($this->send('get', '/sorteoactual')->data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function clients(int $page = 1): array
    {
        return $this->collection(
            $this->send('get', '/clientes', ['page' => max(1, $page)])->data,
            ['clients', 'clientes'],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchClients(string $term): array
    {
        $term = trim($term);

        return $this->collection(
            $this->send('get', '/buscarcliente', [
                'name' => 'clientes-ticket',
                'filterItems' => [
                    [
                        'key' => 'docid',
                        'value' => $term,
                        'operator' => 'LIKE_ALL',
                        'logicalOperator' => 'OR',
                    ],
                    [
                        'key' => 'pnombre',
                        'value' => $term,
                        'operator' => 'LIKE_ALL',
                        'logicalOperator' => 'OR',
                    ],
                    [
                        'key' => 'papellido',
                        'value' => $term,
                        'operator' => 'LIKE_ALL',
                        'logicalOperator' => 'OR',
                    ],
                ],
                'logicalOperator' => 'OR',
            ])->data,
            ['clients', 'clientes'],
        );
    }

    public function isPhoneActive(int $clientId): bool
    {
        return (bool) $this->send('get', "/activephone/{$clientId}")->data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createClient(array $payload): array
    {
        return $this->record($this->send('post', '/creacliente', $payload)->data);
    }

    public function updatePhone(int $clientId, string $phone): string
    {
        return $this->send('post', '/updatephone', [
            'id' => $clientId,
            'telefono' => trim($phone),
        ])->message;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function departments(): array
    {
        return $this->collection(
            $this->send('get', '/departamentos')->data,
            ['departments', 'departamentos'],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function municipalities(int $departmentId): array
    {
        return $this->collection(
            $this->send('get', "/municipios/{$departmentId}")->data,
            ['municipalities', 'municipios'],
        );
    }

    public function createTicket(int $promotionId, int $clientId, int $stockId): TicketPrintData
    {
        $response = $this->send('post', '/create', [
            'promocion' => ['id' => $promotionId],
            'cliente' => ['id' => $clientId],
            'stock' => ['id' => $stockId],
        ]);

        if (! is_array($response->data)) {
            throw new ApiOperationException('El servidor no devolvió los datos de impresión del ticket.');
        }

        return TicketPrintData::fromApi($response->data);
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
            $this->collection($response->data, ['tickets', 'tikets']),
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
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.ticket_path', '/sorteos/ticket');

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

    /**
     * @return array<string, mixed>
     */
    private function record(mixed $data): array
    {
        if (! is_array($data)) {
            throw new ApiOperationException('La API no devolvió los datos solicitados.');
        }

        if (array_is_list($data)) {
            $record = $data[0] ?? null;

            if (is_array($record)) {
                return $record;
            }
        }

        foreach (['data', 'item', 'result', 'client', 'cliente', 'promotion', 'promocion'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key]);
            }
        }

        return $data;
    }
}
