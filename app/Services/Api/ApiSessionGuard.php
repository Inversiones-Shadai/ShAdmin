<?php

namespace App\Services\Api;

use App\Exceptions\ApiSessionExpiredException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use function in_array;
use function is_array;

class ApiSessionGuard
{
    public function ensureActive(Response $response, mixed $body): void
    {
        if (! $this->hasExpired($response, $body)) {
            return;
        }

        Auth::guard('web')->logout();
        session()->forget('auth.api');

        $request = request();

        if ($request->hasSession()) {
            $request->session()->forget('auth.api');
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        throw new ApiSessionExpiredException;
    }

    private function hasExpired(Response $response, mixed $body): bool
    {
        if (in_array($response->status(), [401, 419], true)) {
            return true;
        }

        if (! is_array($body)) {
            return false;
        }

        if (in_array((int) ($body['code'] ?? 0), [401, 419], true)) {
            return true;
        }

        $message = Str::lower(Str::ascii((string) ($body['message'] ?? '')));

        return Str::contains($message, [
            'sesion expirada',
            'sesion ha expirado',
            'session expired',
            'token expirado',
            'token ha expirado',
            'token expired',
            'unauthenticated',
            'no autenticado',
        ]);
    }
}
