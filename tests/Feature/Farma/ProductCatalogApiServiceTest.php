<?php

namespace Tests\Feature\Farma;

use App\Services\Api\ProductCatalogApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductCatalogApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.product_catalog_path' => '/farma/productos',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_each_catalog_uses_its_own_list_action_code(): void
    {
        Http::fake([
            'api.example.test/api/farma/productos/*/listar*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $service = app(ProductCatalogApiService::class);

        foreach (config('farma_product_catalogs') as $catalog => $definition) {
            $service->list($catalog);
            $expectedCode = $definition['actions']['list'];

            Http::assertSent(fn (Request $request): bool => str_contains(
                $request->url(),
                "/{$catalog}/listar",
            ) && $request->hasHeader('Action-Btn', $expectedCode));
        }
    }

    public function test_it_uses_all_crud_routes_and_codes_for_a_catalog(): void
    {
        Http::fake([
            'api.example.test/api/farma/productos/categorias/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 7, 'nombre' => 'Medicamentos']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/productos/categorias/crear' => Http::response([
                'message' => 'Registro creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/categorias/buscar/7' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => ['id' => 7, 'nombre' => 'Medicamentos'],
            ]),
            'api.example.test/api/farma/productos/categorias/actualizar/7' => Http::response([
                'message' => 'Registro actualizado correctamente.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/categorias/eliminar/7' => Http::response([
                'message' => 'Registro eliminado correctamente.', 'code' => 200, 'data' => true,
            ]),
        ]);

        $service = app(ProductCatalogApiService::class);
        $payload = ['nombre' => 'Medicamentos'];

        $this->assertSame(7, $service->list('categorias')->items[0]['id']);
        $this->assertSame('Registro creado correctamente.', $service->create('categorias', $payload));
        $this->assertSame('Medicamentos', $service->find('categorias', 7)['nombre']);
        $this->assertSame('Registro actualizado correctamente.', $service->update('categorias', 7, $payload));
        $this->assertSame('Registro eliminado correctamente.', $service->delete('categorias', 7));

        foreach ([
            '/listar' => 'pctbtn01',
            '/crear' => 'pctbtn02',
            '/buscar/7' => 'pctbtn03',
            '/actualizar/7' => 'pctbtn04',
            '/eliminar/7' => 'pctbtn05',
        ] as $suffix => $code) {
            Http::assertSent(function (Request $request) use ($suffix, $code): bool {
                $path = parse_url($request->url(), PHP_URL_PATH);

                return is_string($path)
                    && str_ends_with($path, $suffix)
                    && $request->hasHeader('Action-Btn', $code);
            });
        }
    }
}
