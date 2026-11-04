<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

class ExpiryApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    public function products(int $page, string $search, string $months): PaginatedCollectionData
    {
        $query = ['page' => max(1, $page), 'search' => mb_substr(trim($search), 0, 100)];
        if ($months !== '') {
            $query['meses'] = (int) $months;
        }

        return $this->page($this->send('GET', '/vencimientos/productos', $query, 'vncbtn01'));
    }

    public function lots(int $product, int $page = 1, string $months = ''): PaginatedCollectionData
    {
        $query = ['page' => max(1, $page)];
        if ($months !== '') {
            $query['meses'] = (int) $months;
        }

        return $this->page($this->send('GET', '/vencimientos/producto/'.$product.'/lotes', $query, 'vncbtn02'));
    }

    /** @param array<int, array{id: int}> $lots
     * @return array<string, mixed>
     */
    public function verify(array $lots): array
    {
        $data = $this->record($this->send('POST', '/vencimientos/verificar', ['lotes' => $lots], 'vncbtn03'));
        if (! is_array($data['resultados'] ?? null) || ! is_string($data['verificado_el'] ?? null)) {
            throw new ApiOperationException('La API no devolvió la verificación completa.');
        }

        return $data;
    }

    public function providers(int $page, string $search): PaginatedCollectionData
    {
        return $this->page($this->send('GET', '/reglas-proveedores/listar', ['page' => max(1, $page), 'search' => mb_substr(trim($search), 0, 100)], 'rdpbtn01'));
    }

    /** @return array<string, mixed> */
    public function policy(int $provider): array
    {
        return $this->record($this->send('GET', '/reglas-proveedores/'.$provider, [], 'rdpbtn01'));
    }

    /** @param array<string, mixed> $rule */
    public function savePolicy(int $provider, array $rule): void
    {
        $this->send('PUT', '/reglas-proveedores/'.$provider, $rule, 'rdpbtn02');
    }

    /** @param array<string, mixed> $payload */
    private function send(string $method, string $path, array $payload, string $action): ApiResponseData
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente en ShAdmin.', status: 401);
        }
        try {
            $request = $this->client->request()->withToken($token)->withHeader('Action-Btn', $action);
            $response = match ($method) {
                'GET' => $request->get('/farma'.$path, $payload),
                'PUT' => $request->put('/farma'.$path, $payload),
                default => $request->post('/farma'.$path, $payload),
            };
        } catch (ApiConfigurationException|ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor. Inténtalo nuevamente.', status: 503);
        }
        $body = $response->json();
        $this->sessions->ensureActive($response, $body);
        if (! is_array($body)) {
            throw new ApiOperationException('La API devolvió una respuesta inválida.', status: $response->status());
        }
        $result = ApiResponseData::fromApi($body);
        $code = $result->code > 0 ? $result->code : $response->status();
        if (! $response->successful() || $code < 200 || $code >= 300) {
            $errors = data_get($body, 'data.errors', []);
            throw new ApiOperationException($result->message ?: 'No se pudo completar la operación.', is_array($errors) ? $errors : [], $code);
        }

        return $result;
    }

    private function page(ApiResponseData $result): PaginatedCollectionData
    {
        if (! is_array($result->data) || ! array_is_list($result->data)) {
            throw new ApiOperationException('La API no devolvió la lista solicitada.');
        }

        return PaginatedCollectionData::fromApi(array_values(array_filter($result->data, is_array(...))), $result->meta);
    }

    /** @return array<string, mixed> */
    private function record(ApiResponseData $result): array
    {
        if (! is_array($result->data) || array_is_list($result->data)) {
            throw new ApiOperationException('La API no devolvió el registro solicitado.');
        }

        return $result->data;
    }
}
