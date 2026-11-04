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

class PurchaseApiService
{
    private const ACTIONS = [
        'list' => 'cmpbtn01', 'create' => 'cmpbtn02', 'search' => 'cmpbtn03',
        'update' => 'cmpbtn04', 'delete' => 'cmpbtn05', 'document' => 'cmpbtn06', 'options' => 'cmpbtn07',
        'draft-create' => 'cmpbtn02', 'draft-sync' => 'cmpbtn04', 'detail-sync' => 'cmpbtn04',
        'detail-delete' => 'cmpbtn04', 'finalize' => 'cmpbtn04',
        'provider-image' => 'cmpbtn07',
        'report' => 'cmpbtn08',
        'kardex' => 'cmpbtn09',
        'transaction-list' => 'ctrbtn01', 'transaction-create' => 'ctrbtn02', 'transaction-search' => 'ctrbtn03',
        'transaction-update' => 'ctrbtn04', 'transaction-delete' => 'ctrbtn05',
        'transaction-document' => 'ctrbtn06', 'transaction-options' => 'ctrbtn07',
    ];

    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessionGuard) {}

    public function list(int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $query = ['page' => max(1, $page)];
        $search = trim($search);

        if ($search !== '') {
            $query['search'] = mb_substr($search, 0, 100);
        }

        return $this->page($this->send('get', '/listar', $query, 'list'));
    }

    /** @return array<string, mixed> */
    public function options(bool $compact = false): array
    {
        return $this->optionsFrom($this->send('get', '/opciones', $compact ? ['compact' => 1] : [], 'options')->data);
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, ?string>
     */
    public function latestCosts(array $productIds, ?int $excludePurchase = null): array
    {
        if ($productIds === []) {
            return [];
        }

        $productIds = array_values(array_unique(array_map(fn (int $id): int => $this->id($id), $productIds)));
        if (count($productIds) > 200) {
            throw new ApiOperationException('Consulta como máximo 200 productos a la vez.');
        }

        $query = ['productos' => $productIds];
        if ($excludePurchase !== null) {
            $query['excluir_compra'] = $this->id($excludePurchase);
        }

        $data = $this->send('get', '/ultimos-costos', $query, 'options')->data;
        if (! is_array($data) || ! array_is_list($data)) {
            throw new ApiOperationException('La API no devolvió los últimos costos.');
        }

        $costs = array_fill_keys($productIds, null);
        foreach ($data as $item) {
            if (! is_array($item) || ! array_key_exists('costo', $item)
                || ! array_key_exists((int) ($item['producto_id'] ?? 0), $costs)) {
                throw new ApiOperationException('La API devolvió un costo inválido.');
            }

            $cost = $item['costo'];
            if ($cost !== null && (! is_numeric($cost) || ! is_finite((float) $cost) || (float) $cost <= 0)) {
                throw new ApiOperationException('La API devolvió un costo inválido.');
            }

            $costs[(int) $item['producto_id']] = $cost === null ? null : number_format((float) $cost, 2, '.', '');
        }

        return $costs;
    }

    public function providers(int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $query = ['page' => max(1, $page), 'per_page' => 10];
        if (trim($search) !== '') {
            $query['search'] = mb_substr(trim($search), 0, 100);
        }

        return $this->page($this->send('get', '/proveedores', $query, 'options'));
    }

    public function providerImage(int $id): string
    {
        return $this->image($this->send('get', '/proveedor-imagen/'.$this->id($id), [], 'provider-image')->data);
    }

    /** @return array<string, mixed> */
    public function report(int $id): array
    {
        return $this->record($this->send('get', '/reporte/'.$this->id($id), [], 'report')->data);
    }

    /** @return array<string, mixed> */
    public function sendToKardex(int $id): array
    {
        return $this->record($this->send('post', '/kardex/'.$this->id($id), [], 'kardex')->data);
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): string
    {
        return $this->send('post', '/crear', $payload, 'create')->message;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createDraft(array $payload): array
    {
        return $this->record($this->send('post', '/borrador', $payload, 'draft-create')->data);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function syncDraft(int $id, array $payload): array
    {
        return $this->record($this->send('patch', '/borrador/'.$this->id($id), $payload, 'draft-sync')->data);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function syncDetail(int $purchaseId, array $payload): array
    {
        return $this->record($this->send('put', '/borrador/'.$this->id($purchaseId).'/detalle', $payload, 'detail-sync')->data);
    }

    /** @return array<string, mixed> */
    public function deleteDetail(int $purchaseId, int $detailId): array
    {
        return $this->record($this->send('delete', '/borrador/'.$this->id($purchaseId).'/detalle/'.$this->id($detailId), [], 'detail-delete')->data);
    }

    /** @return array<string, mixed> */
    public function finalize(int $id): array
    {
        return $this->record($this->send('post', '/finalizar/'.$this->id($id), [], 'finalize')->data);
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        return $this->record($this->send('get', '/buscar/'.$this->id($id), [], 'search')->data);
    }

    /** @param array<string, mixed> $payload */
    public function update(int $id, array $payload): string
    {
        $payload['id'] = $this->id($id);

        return $this->send('put', '/actualizar', $payload, 'update')->message;
    }

    public function delete(int $id): string
    {
        return $this->send('delete', '/eliminar/'.$this->id($id), [], 'delete')->message;
    }

    public function document(int $id): string
    {
        return $this->image($this->send('get', '/documento/'.$this->id($id), [], 'document')->data);
    }

    public function listTransactions(int $page = 1, ?int $purchase = null, string $search = ''): PaginatedCollectionData
    {
        $query = ['page' => max(1, $page)] + (trim($search) === '' ? [] : ['search' => trim($search)]);

        if ($purchase !== null) {
            $query['compra'] = $this->id($purchase);
        }

        return $this->page($this->send('get', '/transacciones/listar', $query, 'transaction-list'));
    }

    /** @return array<string, mixed> */
    public function transactionOptions(): array
    {
        return $this->optionsFrom($this->send('get', '/transacciones/opciones', [], 'transaction-options')->data);
    }

    /** @param array<string, mixed> $payload */
    public function createTransaction(array $payload): string
    {
        return $this->send('post', '/transacciones/crear', $payload, 'transaction-create')->message;
    }

    /** @return array<string, mixed> */
    public function findTransaction(int $id): array
    {
        return $this->record($this->send('get', '/transacciones/buscar/'.$this->id($id), [], 'transaction-search')->data);
    }

    /** @param array<string, mixed> $payload */
    public function updateTransaction(int $id, array $payload): string
    {
        $payload['id'] = $this->id($id);

        return $this->send('put', '/transacciones/actualizar', $payload, 'transaction-update')->message;
    }

    public function deleteTransaction(int $id): string
    {
        return $this->send('delete', '/transacciones/eliminar/'.$this->id($id), [], 'transaction-delete')->message;
    }

    public function transactionDocument(int $id): string
    {
        return $this->image($this->send('get', '/transacciones/documento/'.$this->id($id), [], 'transaction-document')->data);
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
            'get' => $request->get($path, $payload), 'post' => $request->post($path, $payload),
            'put' => $request->put($path, $payload), 'patch' => $request->patch($path, $payload),
            'delete' => $request->delete($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        return '/'.trim((string) config('services.shadmin_api.purchase_path', '/farma/compras'), '/').'/'.ltrim($suffix, '/');
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
        foreach (['data', 'items', 'results', 'compras', 'transacciones'] as $key) {
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
            throw new ApiOperationException('La API no devolvió los datos del registro.');
        }
        foreach (['data', 'record', 'registro', 'compra', 'transaccion'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key]);
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function optionsFrom(mixed $data): array
    {
        if (! is_array($data) || array_is_list($data)) {
            throw new ApiOperationException('La API no devolvió las opciones requeridas.');
        }

        return $data;
    }

    private function image(mixed $data): string
    {
        if (is_array($data) && is_string($data['image'] ?? null)) {
            return $data['image'];
        }
        throw new ApiOperationException('La API no devolvió un documento válido.');
    }

    private function id(int $id): int
    {
        if ($id < 1) {
            throw new ApiOperationException('El identificador no es válido.');
        }

        return $id;
    }
}
