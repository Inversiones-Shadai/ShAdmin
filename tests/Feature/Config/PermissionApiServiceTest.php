<?php

namespace Tests\Feature\Config;

use App\Services\Api\PermissionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PermissionApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.permission_path' => '/config/permisos',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.device_name' => 'DESKTOP-TEST',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_uses_all_permission_catalog_and_list_routes(): void
    {
        Http::fake([
            'api.example.test/api/config/permisos/listarpermisos/7*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 41, 'modulo' => ['nombre' => 'CONFIG']]],
                'meta' => [
                    'total' => 24,
                    'current_page' => 2,
                    'last_page' => 3,
                ],
            ]),
            'api.example.test/api/config/permisos/listarmodulos' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 2, 'nombre' => 'CONFIG']],
            ]),
            'api.example.test/api/config/permisos/listarVistas/2' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 5, 'nombre' => 'USUARIOS']],
            ]),
            'api.example.test/api/config/permisos/listarAcciones/5' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 9, 'nombre' => 'CREAR']],
            ]),
            'api.example.test/api/config/permisos/tipoTiempo' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 1, 'nombre' => 'PERMANENTE']],
            ]),
        ]);

        $service = app(PermissionApiService::class);

        $permissionPage = $service->listForUser(7, 2);

        $this->assertSame(41, $permissionPage->items[0]['id']);
        $this->assertSame(24, $permissionPage->total);
        $this->assertSame(2, $permissionPage->currentPage);
        $this->assertSame(3, $permissionPage->lastPage);
        $this->assertSame('CONFIG', $service->modules()[0]['nombre']);
        $this->assertSame('USUARIOS', $service->views(2)[0]['nombre']);
        $this->assertSame('CREAR', $service->actions(5)[0]['nombre']);
        $this->assertSame('PERMANENTE', $service->timeTypes()[0]['nombre']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/permisos/listarpermisos/7?page=2');
    }

    public function test_it_creates_and_deletes_permissions_with_the_api_contract(): void
    {
        Http::fake([
            'api.example.test/api/config/permisos/crearpermiso' => Http::response([
                'message' => 'Permiso creado exitosamente',
                'code' => 201,
                'data' => true,
            ], 201),
            'api.example.test/api/config/permisos/eliminarpermiso/41' => Http::response([
                'message' => 'Permiso eliminado exitosamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        $service = app(PermissionApiService::class);
        $payload = [
            'usuario' => 7,
            'modulo' => 2,
            'vista' => 5,
            'actionvista' => 9,
            'tipo_tiempo' => 1,
        ];

        $this->assertSame('Permiso creado exitosamente', $service->create($payload));
        $this->assertSame('Permiso eliminado exitosamente', $service->delete(41));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->data() === [
                'usuario' => ['id' => 7],
                'modulo' => ['id' => 2],
                'vista' => ['id' => 5],
                'actionvista' => ['id' => 9],
                'tipo_tiempo' => ['id' => 1],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.example.test/api/config/permisos/eliminarpermiso/41');
    }
}
