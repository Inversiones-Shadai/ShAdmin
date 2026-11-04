<?php

namespace Tests\Feature\Farma;

use App\Models\User;
use App\Services\Api\TransferApiService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransferApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.transfer_path' => '/farma/transferencias',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_uses_the_verified_transfer_routes_and_button_codes(): void
    {
        Http::fake([
            'api.example.test/api/farma/transferencias/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 8, 'nro' => 'TR-0008']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1, 'tipos' => [['id' => 2, 'descripcion' => 'Devolución']]],
            ]),
            'api.example.test/api/farma/transferencias/opciones*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['stocks' => []],
            ]),
            'api.example.test/api/farma/transferencias/lotes*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [['id' => 4, 'lote' => 'L-01']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/transferencias/enviar' => Http::response([
                'message' => 'Enviada', 'code' => 201, 'data' => ['id' => 9],
            ]),
            'api.example.test/api/farma/transferencias/buscar/8' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['id' => 8, 'detalles' => []],
            ]),
            'api.example.test/api/farma/transferencias/recibir/8' => Http::response([
                'message' => 'Recibida', 'code' => 200, 'data' => [],
            ]),
        ]);

        $service = app(TransferApiService::class);

        $page = $service->list(1, 'TR-0008', 'enviadas', 2);
        $this->assertSame('TR-0008', $page->items[0]['nro']);
        $this->assertSame([['id' => 2, 'descripcion' => 'Devolución']], $page->meta['tipos']);
        $this->assertSame(['stocks' => []], $service->options());
        $this->assertSame('L-01', $service->lots(1, 'L-01')->items[0]['lote']);
        $this->assertSame(9, $service->sendTransfer(['tipo' => 1, 'stock_para' => 2, 'detalles' => [['lote' => 4, 'cantidad' => 1]]])['id']);
        $this->assertSame(8, $service->find(8)['id']);
        $this->assertSame('Recibida', $service->receive(8));

        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/listar')
            && ! array_key_exists('stock_id', $request->data())
            && ($request->data()['direccion'] ?? null) === 'enviadas'
            && ($request->data()['tipo'] ?? null) === 2
            && ($request->data()['search'] ?? null) === 'TR-0008');

        foreach ([
            ['/listar', 'GET', 'trfbtn01'],
            ['/opciones', 'GET', 'trfbtn02'],
            ['/lotes', 'GET', 'trfbtn03'],
            ['/enviar', 'POST', 'trfbtn04'],
            ['/recibir/8', 'POST', 'trfbtn05'],
            ['/buscar/8', 'GET', 'trfbtn06'],
        ] as [$suffix, $method, $button]) {
            Http::assertSent(fn (Request $request): bool => $request->method() === $method
                && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), $suffix)
                && $request->hasHeader('Action-Btn', $button));
        }
    }

    public function test_stock_scope_is_rejected_before_contacting_the_api_without_permission(): void
    {
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.spermisos', []);

        foreach (['list', 'find'] as $method) {
            try {
                $service = app(TransferApiService::class);
                $method === 'list' ? $service->list(stockId: 2) : $service->find(8, 2);
                $this->fail('Se permitió consultar otro stock sin permiso.');
            } catch (AuthorizationException) {
                Http::assertNothingSent();
            }
        }
    }
}
