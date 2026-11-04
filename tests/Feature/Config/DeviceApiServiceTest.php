<?php

namespace Tests\Feature\Config;

use App\Services\Api\ApiValueHasher;
use App\Services\Api\DeviceApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.device_path' => '/config/device',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_lists_paginated_devices_and_catalogs(): void
    {
        Http::fake([
            'api.example.test/api/config/device/listar*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [[
                    'id' => 8,
                    'displayname' => 'Caja principal',
                    'stock' => ['id' => 3, 'descripcion' => 'Sucursal centro'],
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ]],
                'meta' => [
                    'total' => 40,
                    'current_page' => 2,
                    'last_page' => 2,
                ],
            ]),
            'api.example.test/api/config/device/estados' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 1, 'descripcion' => 'ACTIVO']],
            ]),
            'api.example.test/api/config/device/stocks' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 3, 'descripcion' => 'Sucursal centro']],
            ]),
        ]);

        $service = app(DeviceApiService::class);
        $page = $service->list(2);

        $this->assertSame(8, $page->items[0]['id']);
        $this->assertSame(40, $page->total);
        $this->assertSame(2, $page->currentPage);
        $this->assertSame(2, $page->lastPage);
        $this->assertSame('ACTIVO', $service->states()[0]['descripcion']);
        $this->assertSame('Sucursal centro', $service->stocks()[0]['descripcion']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/device/listar?page=2'
            && $request->hasHeader('Authorization', 'Bearer api-token'));
    }

    public function test_it_uses_device_routes_nested_relations_and_secured_identity(): void
    {
        Http::fake([
            'api.example.test/api/config/device/crear' => Http::response([
                'message' => 'Dispositivo creado correctamente',
                'code' => 201,
                'data' => true,
            ]),
            'api.example.test/api/config/device/buscar/8' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    'id' => 8,
                    'displayname' => 'Caja principal',
                    'stock' => ['id' => 3, 'descripcion' => 'Sucursal centro'],
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
            'api.example.test/api/config/device/actualizar/8' => Http::response([
                'message' => 'Dispositivo actualizado correctamente',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/device/eliminar/8' => Http::response([
                'message' => 'Dispositivo eliminado correctamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        $service = app(DeviceApiService::class);
        $payload = [
            'ip' => '192.168.1.20',
            'ip2' => '192.168.1.21',
            'displayname' => 'Caja principal',
            'name' => 'DESKTOP-CAJA',
            'stock' => 3,
            'estado' => 1,
        ];

        $this->assertSame('Dispositivo creado correctamente', $service->create($payload));
        $this->assertSame('Caja principal', $service->find(8)['displayname']);
        $this->assertSame('Dispositivo actualizado correctamente', $service->update([
            'id' => 8,
            'estado' => 2,
        ]));
        $this->assertSame('Dispositivo eliminado correctamente', $service->delete(8));

        $hasher = app(ApiValueHasher::class);
        $securedIp = $hasher->hash($hasher->hash($payload['ip']));
        $securedIp2 = $hasher->hash($hasher->hash($payload['ip2']));
        $securedName = $hasher->hash($hasher->hash($payload['name']));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/device/crear'
            && $request->data() === [
                'ip' => $securedIp,
                'ip2' => $securedIp2,
                'displayname' => 'Caja principal',
                'name' => $securedName,
                'stock' => ['id' => 3],
                'estado' => ['id' => 1],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/device/actualizar/8'
            && $request->data() === [
                'id' => 8,
                'estado' => ['id' => 2],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/device/buscar/8');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/device/eliminar/8');
    }
}
