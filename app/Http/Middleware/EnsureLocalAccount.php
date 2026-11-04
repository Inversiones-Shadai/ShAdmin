<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(filled(config('services.shadmin_api.base_url')), 404);

        return $next($request);
    }
}
