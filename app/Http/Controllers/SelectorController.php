<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\SelectorApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SelectorController
{
    public function __invoke(Request $request, string $source, SelectorApiService $api): JsonResponse
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'search' => ['nullable', 'string', 'max:255'],
            'selected' => ['sometimes', 'regex:/^[1-9][0-9]{0,19}$/'],
            'filters' => ['sometimes', 'array:parent,product'], 'filters.*' => ['nullable', 'regex:/^[1-9][0-9]{0,19}$/'],
        ]);
        try {
            return response()->json($api->page($source, $data))->header('Cache-Control', 'no-store');
        } catch (ApiSessionExpiredException) {
            return response()->json(['message' => 'La sesión terminó. Inicia sesión nuevamente.'], 401);
        } catch (ApiOperationException $error) {
            return response()->json(['message' => $error->getMessage()], $error->status >= 400 ? $error->status : 503);
        }
    }
}
