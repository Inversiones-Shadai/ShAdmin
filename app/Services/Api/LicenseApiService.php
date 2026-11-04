<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

class LicenseApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    public function list(int $page, string $search, string $customerId = ''): PaginatedCollectionData
    {
        $result = $this->send('GET', '', ['page' => $page, 'search' => $search] + ($customerId === '' ? [] : ['customer_id' => $customerId]), 'licbtn01');

        return PaginatedCollectionData::fromApi(is_array($result->data) ? $result->data : [], $result->meta);
    }

    public function customers(int $page = 1, string $search = ''): PaginatedCollectionData
    {
        $result = $this->send('GET', '/customers', ['page' => $page, 'search' => $search], 'licbtn01');

        return PaginatedCollectionData::fromApi(is_array($result->data) ? $result->data : [], $result->meta);
    }

    /** @return array<string, mixed> */
    public function saveCustomer(?string $id, string $name): array
    {
        return (array) $this->send($id === null ? 'POST' : 'PUT', '/customers'.($id === null ? '' : '/'.$id),
            ['name' => $name], $id === null ? 'licbtn02' : 'licbtn03')->data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generate(array $payload): array
    {
        return (array) $this->send('POST', '', $payload, 'licbtn02')->data;
    }

    /** @param array<string, mixed> $payload */
    public function update(string $id, array $payload): void
    {
        $this->send('PUT', '/'.$id, $payload, 'licbtn03');
    }

    /** @return array<string, mixed> */
    public function detail(string $id, int $devicesPage = 1, int $eventsPage = 1): array
    {
        return (array) $this->send('GET', '/'.$id, ['devices_page' => $devicesPage, 'events_page' => $eventsPage], 'licbtn04')->data;
    }

    public function unlink(string $id, string $activation): string
    {
        return $this->send('DELETE', '/'.$id.'/devices/'.$activation, [], 'licbtn05')->message;
    }

    public function delete(string $id): string
    {
        return $this->send('DELETE', '/'.$id, [], 'licbtn06')->message;
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
            $url = '/config/licenses'.$path;
            $response = match ($method) {
                'GET' => $request->get($url, $payload),
                'POST' => $request->post($url, $payload),
                'PUT' => $request->put($url, $payload),
                'DELETE' => $request->delete($url, $payload),
                default => throw new ApiOperationException('Método HTTP de licencias inválido.'),
            };
        } catch (ApiConfigurationException|ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor.', status: 503);
        }
        $body = $response->json();
        $this->sessions->ensureActive($response, $body);
        if (! is_array($body)) {
            throw new ApiOperationException('La API devolvió una respuesta inválida.', status: 503);
        }
        $result = ApiResponseData::fromApi($body);
        if (! $response->successful() || ($result->code > 0 && ($result->code < 200 || $result->code >= 300))) {
            $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];
            throw new ApiOperationException($result->message ?: 'No se pudo completar la operación.', $errors, $response->status());
        }

        return $result;
    }
}
