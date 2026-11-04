<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\WorkLunchData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

use function is_array;
use function is_string;

class WorkLunchApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function today(): ?WorkLunchData
    {
        $data = $this->send('get', '/today')->data;

        if ($data === null) {
            return null;
        }

        if (! is_array($data)) {
            throw new ApiOperationException('La API devolvió un estado de jornada inválido.');
        }

        return WorkLunchData::fromApi($data);
    }

    /**
     * @return array{message: string, session: WorkLunchData}
     */
    public function work(): array
    {
        return $this->register('/work');
    }

    /**
     * @return array{message: string, session: WorkLunchData}
     */
    public function lunch(): array
    {
        return $this->register('/lunch');
    }

    /**
     * @return array{message: string, session: WorkLunchData}
     */
    private function register(string $path): array
    {
        $response = $this->send('post', $path);

        if (! is_array($response->data)) {
            throw new ApiOperationException('La API no devolvió el horario actualizado.');
        }

        return [
            'message' => $response->message,
            'session' => WorkLunchData::fromApi($response->data),
        ];
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
            $request = $this->client->request()->withToken($token);

            if ($method === 'post') {
                $request = $request->withHeader('Idempotency-Key', (string) Str::uuid());
            }

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
                $envelope->message !== '' ? $envelope->message : 'No se pudo registrar el horario.',
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
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix): string
    {
        $prefix = (string) config('services.shadmin_api.work_lunch_path', '/auth/worklunch');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }
}
