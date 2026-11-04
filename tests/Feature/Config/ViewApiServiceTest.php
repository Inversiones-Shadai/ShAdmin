<?php

namespace Tests\Feature\Config;

use App\Services\Api\ViewApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ViewApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.view_path' => '/config/vista',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_loads_views_filters_catalogs_and_actions(): void
    {
        Http::fake([
            'api.example.test/api/config/vista/listar?*' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    ['id' => 5, 'nombre' => 'USUARIOS', 'codigo' => 'usr'],
                ],
                'meta' => [
                    'total' => 21,
                    'current_page' => 2,
                    'last_page' => 3,
                ],
            ]),
            'api.example.test/api/config/vista/findbymodule/2*' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    ['id' => 5, 'nombre' => 'USUARIOS', 'codigo' => 'usr'],
                ],
                'meta' => [
                    'total' => 1,
                    'current_page' => 1,
                    'last_page' => 1,
                ],
            ]),
            'api.example.test/api/config/vista/estados' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [['id' => 1, 'descripcion' => 'ACTIVO']],
            ]),
            'api.example.test/api/config/vista/listarModulos' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [['id' => 2, 'nombre' => 'CONFIG']],
            ]),
            'api.example.test/api/config/vista/acciones/5' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [['id' => 9, 'nombre' => 'crear', 'codigo' => 'usrbnt01']],
            ]),
        ]);

        $service = app(ViewApiService::class);
        $page = $service->list(2);
        $filtered = $service->listByModule(2);

        $this->assertSame(5, $page->items[0]['id']);
        $this->assertSame(21, $page->total);
        $this->assertSame(2, $page->currentPage);
        $this->assertSame(3, $page->lastPage);
        $this->assertSame(5, $filtered->items[0]['id']);
        $this->assertSame('ACTIVO', $service->states()[0]['descripcion']);
        $this->assertSame('CONFIG', $service->modules()[0]['nombre']);
        $this->assertSame('usrbnt01', $service->actions(5)[0]['codigo']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/vista/listar?page=2'
            && $request->hasHeader('Authorization', 'Bearer api-token'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/vista/findbymodule/2?page=1');
    }

    public function test_it_uses_all_view_and_action_mutation_routes(): void
    {
        Http::fake([
            'api.example.test/api/config/vista/crear' => Http::response([
                'message' => 'Vista creada',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/vista/buscar/5' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    'id' => 5,
                    'nombre' => 'USUARIOS',
                    'codigo' => 'usr',
                    'modulo' => ['id' => 2, 'nombre' => 'CONFIG'],
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
            'api.example.test/api/config/vista/actualizar/5' => Http::response([
                'message' => 'Vista actualizada',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/vista/eliminar/5' => Http::response([
                'message' => 'Vista eliminada',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/vista/createaction' => Http::response([
                'message' => 'Accion creada',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/vista/deleteaction/9' => Http::response([
                'message' => 'Accion eliminada',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        $service = app(ViewApiService::class);

        $this->assertSame('Vista creada', $service->create([
            'modulo' => 2,
            'nombre' => 'USUARIOS',
            'codigo' => 'usr',
            'estado' => 1,
        ]));
        $this->assertSame('USUARIOS', $service->find(5)['nombre']);
        $this->assertSame('Vista actualizada', $service->update([
            'id' => 5,
            'estado' => 2,
        ]));
        $this->assertSame('Vista eliminada', $service->delete(5));
        $this->assertSame('Accion creada', $service->createAction([
            'vista' => 5,
            'nombre' => 'crear',
            'codigo' => 'usrbnt01',
        ]));
        $this->assertSame('Accion eliminada', $service->deleteAction(9));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/vista/crear'
            && $request->data() === [
                'modulo' => ['id' => 2],
                'nombre' => 'USUARIOS',
                'codigo' => 'usr',
                'estado' => ['id' => 1],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/vista/actualizar/5'
            && $request->data() === [
                'id' => 5,
                'estado' => ['id' => 2],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/vista/createaction'
            && $request->data() === [
                'vista' => ['id' => 5],
                'nombre' => 'crear',
                'codigo' => 'usrbnt01',
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.example.test/api/config/vista/eliminar/5');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.example.test/api/config/vista/deleteaction/9');
    }
}
