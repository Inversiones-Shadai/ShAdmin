<?php

namespace Tests\Feature\Config;

use App\Services\Api\ModuleApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModuleApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.module_path' => '/config/modulo',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.device_name' => 'DESKTOP-TEST',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_lists_paginated_modules_and_states(): void
    {
        Http::fake([
            'api.example.test/api/config/modulo/listar*' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    [
                        'id' => 2,
                        'nombre' => 'CONFIG',
                        'codigo' => 'cfg',
                        'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                    ],
                ],
                'meta' => [
                    'total' => 18,
                    'current_page' => 2,
                    'last_page' => 2,
                ],
            ]),
            'api.example.test/api/config/modulo/estados' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
        ]);

        $service = app(ModuleApiService::class);
        $page = $service->list(2);

        $this->assertSame(2, $page->items[0]['id']);
        $this->assertSame(18, $page->total);
        $this->assertSame(2, $page->currentPage);
        $this->assertSame(2, $page->lastPage);
        $this->assertSame('ACTIVO', $service->states()[0]['descripcion']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/modulo/listar?page=2'
            && $request->hasHeader('Authorization', 'Bearer api-token'));
    }

    public function test_it_uses_all_module_mutation_routes_and_nests_the_state(): void
    {
        Http::fake([
            'api.example.test/api/config/modulo/crear' => Http::response([
                'message' => 'Modulo creado con exito',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/modulo/buscar/8' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    'id' => 8,
                    'nombre' => 'REPORTES',
                    'codigo' => 'rpt',
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
            'api.example.test/api/config/modulo/actualizar/8' => Http::response([
                'message' => 'Modulo actualizado con exito',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/modulo/eliminar/8' => Http::response([
                'message' => 'Modulo eliminado con exito',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        $service = app(ModuleApiService::class);

        $this->assertSame('Modulo creado con exito', $service->create([
            'nombre' => 'REPORTES',
            'codigo' => 'rpt',
            'estado' => 1,
        ]));
        $this->assertSame('REPORTES', $service->find(8)['nombre']);
        $this->assertSame('Modulo actualizado con exito', $service->update([
            'id' => 8,
            'estado' => 2,
        ]));
        $this->assertSame('Modulo eliminado con exito', $service->delete(8));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/modulo/crear'
            && $request->data() === [
                'nombre' => 'REPORTES',
                'codigo' => 'rpt',
                'estado' => ['id' => 1],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/modulo/actualizar/8'
            && $request->data() === [
                'id' => 8,
                'estado' => ['id' => 2],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.example.test/api/config/modulo/buscar/8');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.example.test/api/config/modulo/eliminar/8');
    }
}
