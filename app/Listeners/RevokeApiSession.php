<?php

namespace App\Listeners;

use App\Services\Api\AuthApiService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Throwable;

use function is_string;

class RevokeApiSession
{
    public function __construct(private readonly AuthApiService $api) {}

    public function handle(Logout $event): void
    {
        $request = request();

        if (! $request->hasSession() || ! $request->routeIs('logout', 'app.exit')) {
            return;
        }

        $token = $request->session()->get('auth.api.token');

        if (! is_string($token) || $token === '' || blank(config('services.shadmin_api.base_url'))) {
            return;
        }

        try {
            $this->api->logout($token);
        } catch (Throwable $exception) {
            Log::warning('No se pudo revocar la sesión API durante el cierre local.', [
                'error' => $exception->getMessage(),
            ]);
        } finally {
            $request->session()->forget('auth.api');
        }
    }
}
