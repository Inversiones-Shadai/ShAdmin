<?php

namespace App\Services\Api;

use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;

final class ProductCostReportApiService
{
    public function __construct(private readonly ApiClient $client, private readonly ApiSessionGuard $sessions) {}

    public function report(int $product, string $from = '', string $to = '', string $provider = ''): array
    {
        $query = ['producto_id' => $product];
        foreach (['fecha_desde' => $from, 'fecha_hasta' => $to, 'proveedor_id' => $provider] as $key => $value) {
            if ($value !== '') {
                $query[$key] = $value;
            }
        }
        $token = session('auth.api.token');
        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('Inicia sesión nuevamente.');
        }
        try {
            $response = $this->client->request()->withToken($token)->withHeader('Action-Btn', 'rctbtn01')
                ->get('/farma/reporte-costeo/consultar', $query);
        } catch (ApiConfigurationException|ConnectionException) {
            throw new ApiOperationException('No fue posible conectar con el servidor del reporte.');
        }
        $body = $response->json();
        $this->sessions->ensureActive($response, $body);
        if (! is_array($body) || ! $response->successful() || (int) ($body['code'] ?? 200) >= 300) {
            throw new ApiOperationException(is_array($body) ? ($body['message'] ?? 'No fue posible consultar el costeo.') : 'La API devolvió una respuesta inválida.');
        }
        $data = $body['data'] ?? null;
        if (! is_array($data) || ! is_array($data['producto'] ?? null) || ! is_array($data['compras'] ?? null)
            || ! is_array($data['resumen'] ?? null) || count($data['compras']) > 1000) {
            throw new ApiOperationException('El servidor no devolvió el reporte completo.');
        }

        return $data;
    }
}
