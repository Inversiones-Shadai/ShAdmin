<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

class PosPurchasesApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessionGuard) {}

    /** @param array<string, string> $filters */
    public function list(int $page = 1, array $filters = []): PaginatedCollectionData
    {
        $response = $this->send($filters === [] ? '/listar' : '/filtrar', ['page' => max(1, $page), ...$filters], $filters === [] ? 'cppbtn01' : 'cppbtn02');

        return PaginatedCollectionData::fromApi($this->collection($response->data), $response->meta);
    }

    /** @return array<string, mixed> */
    public function find(string $id): array
    {
        $data = $this->send('/buscar/'.rawurlencode($id), [], 'cppbtn03')->data;
        if (! is_array($data)) {
            throw new ApiOperationException('No se recibió la compra POS.');
        }

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    public function options(string $kind, string $search = ''): array
    {
        if (! in_array($kind, ['stocks', 'suppliers', 'products'], true)) {
            throw new ApiOperationException('Filtro de compra no válido.');
        }

        return $this->collection($this->send('/opciones/'.$kind, ['search' => $search], 'cppbtn02')->data);
    }

    /** @return array<int, array<string, mixed>> */
    private function collection(mixed $data): array
    {
        return is_array($data) ? array_values(array_filter($data, is_array(...))) : [];
    }

    /** @param array<string, mixed> $payload */
    private function send(string $path, array $payload, string $action): ApiResponseData
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente en ShAdmin.', status: 401);
        }
        try {
            $response = $this->client->request()->withToken($token)->withHeader('Action-Btn', $action)->get('/pos/compras'.$path, $payload);
        } catch (ApiConfigurationException $exception) {
            throw new ApiOperationException($exception->getMessage());
        } catch (ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor.');
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
            throw new ApiOperationException($envelope->message ?: 'No fue posible consultar las compras POS.', is_array($errors) ? $errors : [], $code);
        }

        return $envelope;
    }
}
