<?php

namespace Tests\Feature\Farma;

use App\Services\Api\DistributionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DistributionApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.distribution_path' => '/farma/distribucion',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_uses_distribution_action_codes_for_list_detail_and_save(): void
    {
        Http::fake([
            'api.example.test/api/farma/distribucion/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['producto_id' => 4, 'descripcion' => 'Producto']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/distribucion/producto/4' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 8, 'lote' => 'L-01', 'cantidad' => 15]],
            ]),
            'api.example.test/api/farma/distribucion/lote/8' => Http::response([
                'message' => 'guardado', 'code' => 200,
                'data' => ['id' => 2, 'lote_id' => 8, 'precio' => 125, 'isv' => true, 'dto1' => 5, 'dto2' => 0, 'dto3' => 0, 'dto4' => 0],
            ]),
        ]);

        $service = app(DistributionApiService::class);
        $this->assertSame(4, $service->list(1, 'Producto')->items[0]['producto_id']);
        $this->assertSame('L-01', $service->productLots(4)[0]['lote']);
        $this->assertSame(125, $service->saveLot(8, ['precio' => 125, 'isv' => true, 'dto1' => 5, 'dto2' => 0, 'dto3' => 0, 'dto4' => 0])['precio']);

        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/farma/distribucion/listar')
            && ($request->data()['search'] ?? null) === 'Producto'
            && $request->hasHeader('Action-Btn', 'dstbtn01'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/farma/distribucion/producto/4')
            && $request->hasHeader('Action-Btn', 'dstbtn02'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/farma/distribucion/lote/8')
            && $request->hasHeader('Action-Btn', 'dstbtn03'));
    }
}
