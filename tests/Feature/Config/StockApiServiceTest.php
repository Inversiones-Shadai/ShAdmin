<?php

namespace Tests\Feature\Config;

use App\Services\Api\StockApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.stock_path' => '/config/stock',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_lists_paginated_stocks_and_states(): void
    {
        Http::fake([
            'api.example.test/api/config/stock/listar*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [[
                    'id' => 5,
                    'descripcion' => 'Bodega central',
                    'telefono' => '2233-4455',
                    'ubicacion' => 'Tegucigalpa',
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ]],
                'meta' => [
                    'total' => 35,
                    'current_page' => 2,
                    'last_page' => 2,
                ],
            ]),
            'api.example.test/api/config/stock/estados' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 1, 'descripcion' => 'ACTIVO']],
            ]),
        ]);

        $service = app(StockApiService::class);
        $page = $service->list(2);

        $this->assertSame(5, $page->items[0]['id']);
        $this->assertSame(35, $page->total);
        $this->assertSame(2, $page->currentPage);
        $this->assertSame(2, $page->lastPage);
        $this->assertSame('ACTIVO', $service->states()[0]['descripcion']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/stock/listar?page=2'
            && $request->hasHeader('Authorization', 'Bearer api-token'));
    }

    public function test_it_uses_all_stock_mutation_routes_and_the_server_payload(): void
    {
        Http::fake([
            'api.example.test/api/config/stock/crear' => Http::response([
                'message' => 'Stock creado correctamente',
                'code' => 201,
                'data' => true,
            ]),
            'api.example.test/api/config/stock/buscar/5' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    'id' => 5,
                    'descripcion' => 'Bodega central',
                    'telefono' => '2233-4455',
                    'ubicacion' => 'Tegucigalpa',
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
            'api.example.test/api/config/stock/actualizar/5' => Http::response([
                'message' => 'Stock actualizado correctamente',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/stock/eliminar/5' => Http::response([
                'message' => 'Stock eliminado correctamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        $service = app(StockApiService::class);
        $payload = [
            'descripcion' => 'Bodega central',
            'telefono' => '2233-4455',
            'ubicacion' => 'Tegucigalpa',
            'estado' => 1,
        ];

        $this->assertSame('Stock creado correctamente', $service->create($payload));
        $this->assertSame('Bodega central', $service->find(5)['descripcion']);
        $this->assertSame('Stock actualizado correctamente', $service->update(5, $payload));
        $this->assertSame('Stock eliminado correctamente', $service->delete(5));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/stock/crear'
            && $request->data() === [
                ...$payload,
                'estado' => ['id' => 1],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/stock/actualizar/5'
            && $request->data() === [
                ...$payload,
                'estado' => ['id' => 1],
                'id' => 5,
            ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/stock/buscar/5');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/stock/eliminar/5');
    }
}
