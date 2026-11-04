<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Auth\AuthenticatedSessionData;
use App\Data\Auth\AuthLoginData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use UnexpectedValueException;

use function is_array;

class AuthApiService
{
    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiValueHasher $hasher,
    ) {}

    /**
     * @throws AuthenticationException
     */
    public function login(AuthLoginData $credentials): AuthenticatedSessionData
    {
        try {
            $response = $this->client
                ->request()
                ->post((string) config('services.shadmin_api.auth_login_path', '/auth/session/login'), $this->hashedPayload($credentials))
                ->throw()
                ->json();
        } catch (ApiConfigurationException $exception) {
            throw new AuthenticationException($exception->getMessage());
        } catch (ConnectionException) {
            throw new AuthenticationException('No se pudo conectar con el servidor.');
        } catch (RequestException $exception) {
            throw new AuthenticationException($exception->response->json('message') ?: 'No fue posible iniciar sesion.');
        }

        if (! is_array($response)) {
            throw new AuthenticationException('La API devolvio una respuesta de autenticacion invalida.');
        }

        $envelope = ApiResponseData::fromApi($response);

        if ($envelope->code < 200 || $envelope->code >= 300 || ! is_array($envelope->data)) {
            throw new AuthenticationException($envelope->message ?: 'Credenciales invalidas.');
        }

        try {
            $session = AuthenticatedSessionData::fromApi($envelope->data);
        } catch (UnexpectedValueException) {
            throw new AuthenticationException('La API devolvio datos de sesion invalidos.');
        }

        if ($session->token === '') {
            throw new AuthenticationException('La API no devolvio un token de autenticacion.');
        }

        return $session;
    }

    /**
     * @throws ApiOperationException
     */
    public function logout(string $token): void
    {
        try {
            $response = $this->client
                ->request()
                ->withToken($token)
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->connectTimeout(2)
                ->timeout(5)
                ->post((string) config('services.shadmin_api.auth_logout_path', '/auth/session/logout'));
        } catch (ApiConfigurationException $exception) {
            throw new ApiOperationException($exception->getMessage());
        } catch (ConnectionException) {
            throw new ApiOperationException('No se pudo cerrar la sesión en el servidor.');
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new ApiOperationException('La API devolvió una respuesta de cierre de sesión inválida.', status: $response->status());
        }

        $envelope = ApiResponseData::fromApi($body);
        $code = $envelope->code > 0 ? $envelope->code : $response->status();

        if (! $response->successful() || $code < 200 || $code >= 300) {
            throw new ApiOperationException(
                $envelope->message !== '' ? $envelope->message : 'No se pudo cerrar la sesión en el servidor.',
                status: $code,
            );
        }
    }

    /**
     * @return array{name: string, password: string}
     */
    private function hashedPayload(AuthLoginData $credentials): array
    {
        return [
            ...$credentials->toPayload(),
            'name' => $this->hasher->hash($credentials->name),
            'password' => $this->hasher->hash($credentials->password),
        ];
    }
}
