<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

use function is_array;
use function is_string;

class WorkHoursApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function findByUser(int $userId): ?array
    {
        try {
            $data = $this->send('get', "/find/{$userId}")->data;
        } catch (ApiOperationException $exception) {
            if ($exception->status === 404) {
                return null;
            }

            throw $exception;
        }

        return $this->record($data);
    }

    /**
     * @param  array{usuario: int, horas_lab: int, horas_lunch: int}  $payload
     */
    public function create(array $payload): string
    {
        return $this->send('post', '/save', [
            ...$payload,
            'usuario' => ['id' => $payload['usuario']],
        ])->message;
    }

    /**
     * @param  array{usuario: int, horas_lab: int, horas_lunch: int}  $payload
     */
    public function update(array $payload): string
    {
        return $this->create($payload);
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
            $request = $this->client
                ->request()
                ->withToken($token);

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
                $envelope->message !== '' ? $envelope->message : 'No se pudo guardar la configuración de horas.',
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
            'put' => $request->put($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.work_hours_path', '/config/horas-lab');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function record(mixed $data): array
    {
        if (! is_array($data)) {
            throw new ApiOperationException('La API no devolvió la configuración de horas.');
        }

        if (array_is_list($data)) {
            $record = $data[0] ?? null;

            if (is_array($record)) {
                return $record;
            }
        }

        foreach (['schedule', 'horas', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key]);
            }
        }

        return $data;
    }
}
