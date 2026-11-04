<?php

namespace App\Services\Api;

use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Http\Client\ConnectionException;

final class SelectorApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    public function page(string $source, array $input): array
    {
        $definition = config('selector_sources.'.$source) ?? abort(404);
        $permission = collect($definition['permissions'])->first(fn ($codes) => app(CurrentUserPermissions::class)->hasAction(...$codes));
        abort_unless($permission, 403);
        foreach ($definition['requires'] ?? [] as $codes) {
            abort_unless(app(CurrentUserPermissions::class)->hasAction(...$codes), 403);
        }
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            abort(401);
        }
        try {
            $response = $this->client->request()->withToken($token)->withHeader('Action-Btn', $permission[2])
                ->timeout(15)->get('/selectors/'.$source, $input);
            $body = $response->json();
            $this->sessions->ensureActive($response, $body);
            if (! $response->successful() || ! is_array($body) || (int) ($body['code'] ?? $response->status()) >= 400 || ! is_array($body['data'] ?? null)) {
                throw new ApiOperationException('No fue posible consultar el selector.', status: $response->status());
            }

            return $body;
        } catch (ApiConfigurationException $error) {
            throw new ApiOperationException($error->getMessage(), status: 503);
        } catch (ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor.', status: 503);
        }
    }
}
