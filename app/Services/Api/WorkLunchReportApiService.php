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

class WorkLunchReportApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function filter(
        int $userId,
        string $fromDate,
        string $toDate,
        int $page = 1,
    ): PaginatedCollectionData {
        return $this->paginated($this->send('/filter', [
            'usuario' => $userId,
            'work_date' => [$fromDate, $toDate],
            'page' => max(1, $page),
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function users(): array
    {
        return $this->collection($this->send('/listUsuarios', [])->data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allFiltered(
        int $userId,
        string $fromDate,
        string $toDate,
        int $maximumRows = 10000,
    ): array {
        $firstPage = $this->filter($userId, $fromDate, $toDate);

        if ($firstPage->total > $maximumRows) {
            throw new ApiOperationException(
                "El reporte contiene más de {$maximumRows} registros. Reduce el rango de fechas antes de exportar.",
            );
        }

        $items = $firstPage->items;

        for ($page = 2; $page <= $firstPage->lastPage; $page++) {
            $items = [...$items, ...$this->filter($userId, $fromDate, $toDate, $page)->items];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $suffix, array $payload): ApiResponseData
    {
        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $response = $this->client
                ->request()
                ->withToken($token)
                ->get($this->path($suffix), $payload);
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
                $envelope->message !== '' ? $envelope->message : 'No se pudo consultar el reporte de jornada laboral.',
                $errors,
                $code,
            );
        }

        return $envelope;
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

    private function path(string $suffix): string
    {
        $prefix = (string) config(
            'services.shadmin_api.report_work_lunch_path',
            '/reportes/worklunch',
        );

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
