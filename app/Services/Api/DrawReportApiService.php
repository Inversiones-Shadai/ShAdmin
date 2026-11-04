<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

use function is_array;
use function is_string;

class DrawReportApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    /**
     * @param  array<int, array{key: string, value: string|int, operator?: string}>  $filters
     */
    public function filterTickets(array $filters, int $page = 1): PaginatedCollectionData
    {
        return $this->paginated($this->send('/filter', [
            'name' => 'reporte-sorteos',
            'filterItems' => $this->filterItems($filters),
            'page' => max(1, $page),
        ]));
    }

    /**
     * @param  array<int, array{key: string, value: string|int, operator?: string}>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function allFilteredTickets(array $filters, int $maximumRows = 10000): array
    {
        $firstPage = $this->filterTickets($filters);

        if ($firstPage->total > $maximumRows) {
            throw new ApiOperationException(
                "El reporte contiene más de {$maximumRows} registros. Agrega más filtros antes de exportar.",
            );
        }

        $items = $firstPage->items;

        for ($page = 2; $page <= $firstPage->lastPage; $page++) {
            $items = [...$items, ...$this->filterTickets($filters, $page)->items];
        }

        return $items;
    }

    public function listDraws(int $page = 1): PaginatedCollectionData
    {
        return $this->paginated($this->send('/list_sorteos', ['page' => max(1, $page)]));
    }

    public function filterDraws(string $term, int $page = 1): PaginatedCollectionData
    {
        return $this->catalogFilter('/filter_sorteos', 'nombre', $term, $page, wrap: true);
    }

    public function listClients(int $page = 1): PaginatedCollectionData
    {
        return $this->paginated($this->send('/list_clientes', ['page' => max(1, $page)]));
    }

    public function filterClients(string $term, int $page = 1): PaginatedCollectionData
    {
        $term = trim($term);

        return $this->paginated($this->send('/filter_clientes', [
            'name' => 'reporte-clientes',
            'filterItems' => $this->filterItems([
                ['key' => 'docid', 'value' => $term, 'operator' => 'LIKE'],
                ['key' => 'pnombre', 'value' => $term, 'operator' => 'LIKE'],
            ], 'OR'),
            'page' => max(1, $page),
        ]));
    }

    public function listUsers(int $page = 1): PaginatedCollectionData
    {
        return $this->paginated($this->send('/list_usuarios', ['page' => max(1, $page)]));
    }

    public function filterUsers(string $term, int $page = 1): PaginatedCollectionData
    {
        return $this->catalogFilter('/filter_usuarios', 'nombre', $term, $page);
    }

    public function listStocks(int $page = 1): PaginatedCollectionData
    {
        return $this->paginated($this->send('/list_stocks', ['page' => max(1, $page)]));
    }

    public function filterStocks(string $term, int $page = 1): PaginatedCollectionData
    {
        return $this->catalogFilter('/filter_stocks', 'descripcion', $term, $page);
    }

    private function catalogFilter(
        string $path,
        string $key,
        string $term,
        int $page,
        bool $wrap = false,
    ): PaginatedCollectionData {
        $term = trim($term);

        return $this->paginated($this->send($path, [
            'name' => 'reporte-sorteos',
            'filterItems' => $this->filterItems([[
                'key' => $key,
                'value' => $wrap ? "%{$term}%" : $term,
                'operator' => 'LIKE',
            ]]),
            'page' => max(1, $page),
        ]));
    }

    /**
     * @param  array<int, array{key: string, value: string|int, operator?: string}>  $filters
     * @return array<int, array{key: string, value: string, operator: string, logicalOperator: string}>
     */
    private function filterItems(array $filters, string $logicalOperator = 'AND'): array
    {
        return array_map(
            fn (array $filter): array => [
                'key' => $filter['key'],
                'value' => (string) $filter['value'],
                'operator' => $filter['operator'] ?? '=',
                'logicalOperator' => $logicalOperator,
            ],
            $filters,
        );
    }

    private function paginated(ApiResponseData $response): PaginatedCollectionData
    {
        $meta = $response->meta;

        if ($meta === null && is_array($response->data) && ! array_is_list($response->data)) {
            $meta = is_array($response->data['meta'] ?? null)
                ? $response->data['meta']
                : null;
        }

        return PaginatedCollectionData::fromApi(
            $this->collection($response->data),
            $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $path, array $payload): ApiResponseData
    {
        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $response = $this->client
                ->request()
                ->withToken($token)
                ->get($this->path($path), $payload);
        } catch (ApiConfigurationException $exception) {
            throw new ApiOperationException($exception->getMessage());
        } catch (ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor.');
        }

        return $this->response($response);
    }

    private function response(Response $response): ApiResponseData
    {
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

    private function path(string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.report_draw_path', '/reportes/sorteos');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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
}
