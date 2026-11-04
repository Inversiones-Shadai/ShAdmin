<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

class DesktopReleaseApiService
{
    public const array ACTIONS = ['create' => 'updbtn02', 'edit' => 'updbtn03', 'pilots' => 'updbtn04',
        'pilot' => 'updbtn05', 'stable' => 'updbtn06', 'withdraw' => 'updbtn07'];

    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    public function listing(int $page, string $product, string $status, string $search): PaginatedCollectionData
    {
        return $this->page($this->send('GET', '', compact('page', 'product', 'status', 'search'), 'updbtn01'));
    }

    public function artifacts(int $page, string $product, string $search): PaginatedCollectionData
    {
        return $this->page($this->send('GET', '/artifacts', compact('page', 'product', 'search'), 'updbtn02'));
    }

    public function pilots(string $id, int $page, string $search): array
    {
        return (array) $this->send('GET', '/'.$id.'/pilots', compact('page', 'search'), 'updbtn04')->data;
    }

    public function events(string $id, int $page): PaginatedCollectionData
    {
        return $this->page($this->send('GET', '/'.$id.'/events', compact('page'), 'updbtn08'));
    }

    public function execute(string $action, ?string $id, array $payload): array
    {
        if (! isset(self::ACTIONS[$action])) {
            throw new ApiOperationException('Acción de actualización inválida.', status: 422);
        }
        $path = $action === 'create' ? '' : '/'.$id.($action === 'edit' ? '' : '/'.$action);
        $method = in_array($action, ['edit', 'pilots'], true) ? 'PUT' : 'POST';

        return (array) $this->send($method, $path, $payload, self::ACTIONS[$action])->data;
    }

    private function page(ApiResponseData $response): PaginatedCollectionData
    {
        return PaginatedCollectionData::fromApi(is_array($response->data) ? $response->data : [], $response->meta);
    }

    private function send(string $method, string $path, array $payload, string $action): ApiResponseData
    {
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente en ShAdmin.', status: 401);
        }
        try {
            $request = $this->client->request()->withToken($token)->withHeader('Action-Btn', $action);
            $url = '/config/desktop-releases'.$path;
            $response = match ($method) {
                'GET' => $request->get($url, $payload), 'POST' => $request->post($url, $payload),
                'PUT' => $request->put($url, $payload),
            };
        } catch (ApiConfigurationException|ConnectionException) {
            throw new ApiOperationException('No se pudo confirmar la operación con el servidor. Reintenta la misma solicitud.', status: 503);
        }
        $body = $response->json();
        $this->sessions->ensureActive($response, $body);
        if (! is_array($body)) {
            throw new ApiOperationException('Respuesta del servidor no confirmada.', status: 503);
        }
        $result = ApiResponseData::fromApi($body);
        if (! $response->successful() || ($result->code > 0 && ($result->code < 200 || $result->code >= 300))) {
            throw new ApiOperationException($result->message ?: 'No se completó la operación.',
                is_array($body['errors'] ?? null) ? $body['errors'] : [], $response->status());
        }

        return $result;
    }
}
