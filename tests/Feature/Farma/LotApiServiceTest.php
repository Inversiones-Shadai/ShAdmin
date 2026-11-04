<?php

namespace Tests\Feature\Farma;

use App\Services\Api\LotApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LotApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.lot_path' => '/farma/lotes',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_uses_lot_list_and_detail_action_codes(): void
    {
        Http::fake([
            'api.example.test/api/farma/lotes/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['producto_id' => 4, 'descripcion' => 'Producto']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/lotes/producto/4' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 8, 'lote' => 'L-01']],
            ]),
        ]);

        $service = app(LotApiService::class);
        $this->assertSame(4, $service->list(1, 'Producto')->items[0]['producto_id']);
        $this->assertSame('L-01', $service->productLots(4)[0]['lote']);

        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/farma/lotes/listar')
            && ($request->data()['search'] ?? null) === 'Producto'
            && $request->hasHeader('Action-Btn', 'ltsbtn01'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/farma/lotes/producto/4')
            && $request->hasHeader('Action-Btn', 'ltsbtn02'));
    }
}
