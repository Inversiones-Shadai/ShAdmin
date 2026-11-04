<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Gate;

use function is_array;
use function is_string;

class TransferApiService
{
    private const ACTIONS = [
        'list' => 'trfbtn01',
        'options' => 'trfbtn02',
        'lots' => 'trfbtn03',
        'send' => 'trfbtn04',
        'receive' => 'trfbtn05',
        'search' => 'trfbtn06',
    ];

    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessionGuard) {}

    public function list(int $page = 1, string $search = '', string $direction = 'todas', ?int $type = null, ?int $stockId = null, ?string $dateFrom = null, ?string $dateTo = null): PaginatedCollectionData
    {
        $query = [
            'page' => max(1, $page),
            'compact' => 1,
            'direccion' => in_array($direction, ['todas', 'enviadas', 'recibidas'], true) ? $direction : 'todas',
        ];
        $search = trim($search);

        if ($type !== null && $type > 0) {
            $query['tipo'] = $type;
        }

        if ($search !== '') {
            $query['search'] = mb_substr($search, 0, 100);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $query['fecha_desde'] = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $query['fecha_hasta'] = $dateTo;
        }

        return $this->page($this->send('get', '/listar', $this->withStockFilter($query, $stockId), 'list'));
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        $data = $this->send('get', '/opciones', ['compact' => 1], 'options')->data;

        if (! is_array($data) || array_is_list($data)) {
            throw new ApiOperationException('La API no devolvió las opciones de transferencias.');
        }

        return $data;
    }

    public function lots(int $page = 1, string $search = '', ?int $productId = null): PaginatedCollectionData
    {
        $query = ['page' => max(1, $page), 'per_page' => 10];
        $search = trim($search);

        if ($search !== '') {
            $query['search'] = mb_substr($search, 0, 100);
        }

        if ($productId !== null && $productId > 0) {
            $query['producto_id'] = $productId;
        }

        return $this->page($this->send('get', '/lotes', $query, 'lots'));
    }

    /** @param array{tipo: int, stock_para: int, detalles?: array<int, array{lote: int, cantidad: int}>} $payload
     * @return array<string, mixed>
     */
    public function sendTransfer(array $payload): array
    {
        return $this->record($this->send('post', '/enviar', $payload, 'send')->data);
    }

    /** @return array<string, mixed> */
    public function conversionOptions(int $lotId): array
    {
        return $this->record($this->send('get', '/conversion/'.$this->id($lotId), ['compact' => 1], 'send')->data);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function convertProduct(array $payload): array
    {
        return $this->record($this->send('post', '/convertir', $payload, 'send')->data);
    }

    /** @return array<string, mixed> */
    public function find(int $id, ?int $stockId = null): array
    {
        return $this->record($this->send('get', '/buscar/'.$this->id($id), $this->withStockFilter([], $stockId), 'search')->data);
    }

    /** @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function withStockFilter(array $query, ?int $stockId): array
    {
        if ($stockId !== null) {
            Gate::authorize(CurrentUserPermissions::GATE, 'transfers.filter-stocks');

            if ($stockId < 1) {
                throw new ApiOperationException('Selecciona un stock válido.');
            }

            $query['stock_id'] = $stockId;
        }

        return $query;
    }

    public function receive(int $id): string
    {
        return $this->send('post', '/recibir/'.$this->id($id), [], 'receive')->message;
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
            'post' => $request->post($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        return '/'.trim((string) config('services.shadmin_api.transfer_path', '/farma/transferencias'), '/').'/'.ltrim($suffix, '/');
    }

    private function id(int $id): int
    {
        if ($id < 1) {
            throw new ApiOperationException('El identificador de la transferencia no es válido.');
        }

        return $id;
    }

    private function page(ApiResponseData $response): PaginatedCollectionData
    {
        $meta = $response->meta;
        if ($meta === null && is_array($response->data) && ! array_is_list($response->data)) {
            $meta = is_array($response->data['meta'] ?? null) ? $response->data['meta'] : $response->data;
        }

        return PaginatedCollectionData::fromApi($this->collection($response->data), $meta);
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

        foreach (['data', 'items', 'results', 'transferencias', 'lotes'] as $key) {
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
            throw new ApiOperationException('La API no devolvió los datos de la transferencia.');
        }

        foreach (['data', 'record', 'registro', 'transferencia'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key]);
            }
        }

        return $data;
    }
}
