<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

class PurchaseReportApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessionGuard) {}

    public function list(int $page = 1, string $search = '', string $provider = '', string $type = ''): PaginatedCollectionData
    {
        $query = ['page' => max(1, $page), 'search' => mb_substr(trim($search), 0, 100)];
        if ($provider !== '') {
            $query['proveedor'] = (int) $provider;
        }
        if ($type !== '') {
            $query['tipo'] = (int) $type;
        }
        $response = $this->get('listar', $query, 'rcpbtn01');
        $data = $response->data;
        if (! is_array($data) || ! is_array($data['items'] ?? null) || ! is_array($data['meta'] ?? null) || ! is_array($data['totals'] ?? null)) {
            throw new ApiOperationException('La API no devolvió el reporte completo.');
        }

        return PaginatedCollectionData::fromApi(array_values(array_filter($data['items'], is_array(...))), [...$data['meta'], 'totals' => $data['totals']]);
    }

    public function providers(int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $response = $this->get('proveedores', ['page' => max(1, $page), 'search' => mb_substr(trim($search), 0, 100)], 'rcpbtn02');

        return PaginatedCollectionData::fromApi(is_array($response->data) ? array_values(array_filter($response->data, is_array(...))) : [], $response->meta);
    }

    public function payments(int $purchase, int $page = 1): PaginatedCollectionData
    {
        if ($purchase < 1) {
            throw new ApiOperationException('La compra no es válida.');
        }
        $data = $this->get('pagos/'.$purchase, ['page' => max(1, $page)], 'rcpbtn03')->data;
        if (! is_array($data) || ! is_array($data['items'] ?? null) || ! is_array($data['meta'] ?? null) || ! is_array($data['purchase'] ?? null)) {
            throw new ApiOperationException('La API no devolvió los pagos de la compra.');
        }

        return PaginatedCollectionData::fromApi(array_values(array_filter($data['items'], is_array(...))), [...$data['meta'], 'purchase' => $data['purchase']]);
    }

    /** @return array<int, array<string, mixed>> */
    public function exportRows(string $search = '', string $provider = '', string $type = ''): array
    {
        $query = ['search' => mb_substr(trim($search), 0, 100)];
        if ($provider !== '') {
            $query['proveedor'] = (int) $provider;
        }
        if ($type !== '') {
            $query['tipo'] = (int) $type;
        }
        $rows = [];
        $cursor = null;
        do {
            if ($cursor !== null) {
                $query['antes_de'] = $cursor;
            }
            $data = $this->get('exportar', $query, 'rcpbtn04')->data;
            if (! is_array($data) || ! is_array($data['items'] ?? null) || ! array_key_exists('next_cursor', $data)) {
                throw new ApiOperationException('La API no devolvió la exportación completa.');
            }
            foreach ($data['items'] as $row) {
                if (! is_array($row) || ! is_array($row['pagos'] ?? null) || (int) ($row['id'] ?? 0) < 1
                    || isset($rows[(int) $row['id']])) {
                    throw new ApiOperationException('El reporte cambió durante la exportación. Vuelve a consultar e inténtalo nuevamente.');
                }
                $rows[(int) $row['id']] = $row;
            }
            $next = $data['next_cursor'];
            if ($next !== null && (! is_int($next) || $next < 1 || ($cursor !== null && $next >= $cursor) || $data['items'] === [])) {
                throw new ApiOperationException('La API devolvió una continuación inválida del reporte.');
            }
            $cursor = $next;
        } while ($cursor !== null);

        return array_values($rows);
    }

    /** @param array<string, int|string> $query */
    private function get(string $path, array $query, string $action): ApiResponseData
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }
        try {
            $response = $this->client->request()->withToken($token)->withHeader('Action-Btn', $action)
                ->get('/farma/reporte-compras/'.$path, $query);
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
            throw new ApiOperationException($envelope->message ?: 'No se pudo consultar el reporte.', status: $code);
        }

        return $envelope;
    }
}
