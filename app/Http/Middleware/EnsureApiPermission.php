<?php

namespace App\Http\Middleware;

use App\Services\Auth\CurrentUserPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiPermission
{
    public function __construct(
        private readonly CurrentUserPermissions $permissions,
    ) {}

    public function handle(Request $request, Closure $next, string $ability): Response
    {
        abort_unless(
            $this->permissions->allows($ability),
            Response::HTTP_FORBIDDEN,
            'No tienes permiso para acceder a esta sección.',
        );

        return $next($request);
    }
}
