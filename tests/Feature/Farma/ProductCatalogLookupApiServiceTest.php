<?php

namespace Tests\Feature\Farma;

use App\Exceptions\ApiOperationException;
use App\Services\Api\ProductApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductCatalogLookupApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.product_lookup_path' => '/farma/productos/producto',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    #[DataProvider('catalogs')]
    public function test_catalog_methods_send_the_expected_routes_and_filter_model(string $catalog, string $listMethod, string $filterMethod, array $filterItems): void
    {
        Http::fake(['api.example.test/api/farma/productos/producto/*' => Http::response([
            'message' => 'ok', 'code' => 200,
            'data' => [['id' => 7, 'descripcion' => 'Catalog item']],
            'meta' => ['total' => 31, 'current_page' => 2, 'last_page' => 2],
        ])]);

        $service = app(ProductApiService::class);
        $listed = $service->$listMethod(2);
        $filtered = $service->$filterMethod('  prueba  ', 2);
        $this->assertSame(7, $listed->items[0]['id']);
        $this->assertSame(31, $filtered->total);
        $this->assertSame(2, $filtered->currentPage);
        $this->assertSame(2, $filtered->lastPage);

        foreach ([
            '/'.$catalog => ['page' => 2],
            '/filter_'.$catalog => ['page' => 2, 'filterItems' => $filterItems],
        ] as $suffix => $expected) {
            Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
                && parse_url($request->url(), PHP_URL_PATH) === '/api/farma/productos/producto'.$suffix
                && $request->hasHeader('Authorization', 'Bearer api-token')
                && $request->hasHeader('Action-Btn', 'prdbtn07')
                && $request->data() === $expected
            );
        }
        Http::assertSentCount(2);
    }

    #[DataProvider('catalogs')]
    public function test_empty_catalog_search_uses_the_list_endpoint(string $catalog, string $listMethod, string $filterMethod, array $filterItems): void
    {
        Http::fake(['api.example.test/*' => Http::response([
            'message' => 'ok', 'code' => 200, 'data' => [],
            'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
        ])]);
        $result = app(ProductApiService::class)->$filterMethod('   ', 0);
        $this->assertSame([], $result->items);
        Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/api/farma/productos/producto/'.$catalog
            && $request->data() === ['page' => 1]
        );
    }

    public function test_catalog_filter_preserves_api_validation_errors(): void
    {
        Http::fake(['api.example.test/*' => Http::response([
            'message' => 'El filtro no es válido.', 'code' => 400, 'data' => null,
        ], 400)]);
        $this->expectException(ApiOperationException::class);
        $this->expectExceptionMessage('El filtro no es válido.');
        app(ProductApiService::class)->filterFamilies('prueba');
    }

    public static function catalogs(): array
    {
        return [
            'laboratorios' => ['laboratorios', 'listLaboratories', 'filterLaboratories', [
                ['key' => 'nombre', 'value' => 'prueba'],
            ]],
            'familias' => ['familias', 'listFamilies', 'filterFamilies', [
                ['key' => 'descripcion', 'value' => 'prueba'],
            ]],
            'unidades' => ['unidades', 'listUnits', 'filterUnits', [
                ['key' => 'abreviatura_c', 'value' => 'prueba'],
                ['key' => 'abreviatura_v', 'value' => 'prueba'],
            ]],
        ];
    }
}
