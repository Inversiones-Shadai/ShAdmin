<?php

namespace Tests\Feature\Farma;

use App\Exceptions\ApiOperationException;
use App\Services\Api\PurchaseApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurchaseApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.purchase_path' => '/farma/compras',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_fetches_last_costs_in_one_authenticated_batch(): void
    {
        Http::fake(['api.example.test/api/farma/compras/ultimos-costos*' => Http::response([
            'message' => 'ok', 'code' => 200, 'data' => [['producto_id' => 4, 'costo' => '12.35'], ['producto_id' => 5, 'costo' => null]],
        ])]);
        $this->assertSame([4 => '12.35', 5 => null], app(PurchaseApiService::class)->latestCosts([4, 5, 4], 10));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Action-Btn', 'cmpbtn07') && $request->hasHeader('Authorization', 'Bearer api-token')
            && $request->data()['productos'] === [4, 5] && $request->data()['excluir_compra'] === 10);
        Http::assertSentCount(1);
    }

    public function test_it_rejects_invalid_cost_data_and_skips_empty_batches(): void
    {
        $service = app(PurchaseApiService::class);
        $this->assertSame([], $service->latestCosts([]));
        Http::assertNothingSent();
        $sequence = Http::sequence();
        foreach ([-1, 'invalid', []] as $cost) {
            $sequence->push([
                'message' => 'ok', 'code' => 200, 'data' => [['producto_id' => 4, 'costo' => $cost]],
            ]);
        }
        Http::fake(['api.example.test/api/farma/compras/ultimos-costos*' => $sequence]);
        foreach ([-1, 'invalid', []] as $cost) {
            try {
                $service->latestCosts([4]);
                $this->fail('Invalid API cost must be rejected.');
            } catch (ApiOperationException $exception) {
                $this->assertSame('La API devolvió un costo inválido.', $exception->getMessage());
            }
        }
    }

    public function test_it_uses_all_purchase_and_transaction_action_codes(): void
    {
        Http::fake([
            'api.example.test/api/farma/compras/listar*' => Http::response(['message' => 'ok', 'code' => 200, 'data' => [['id' => 4]], 'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1]]),
            'api.example.test/api/farma/compras/opciones*' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['tipos' => []]]),
            'api.example.test/api/farma/compras/proveedores*' => Http::response(['message' => 'ok', 'code' => 200, 'data' => [['id' => 3, 'nombre' => 'Proveedor Médico']], 'meta' => ['total' => 1, 'current_page' => 2, 'last_page' => 2]]),
            'api.example.test/api/farma/compras/proveedor-imagen/3' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['image' => 'aW1hZ2U=']]),
            'api.example.test/api/farma/compras/reporte/4' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['id' => 4, 'usuario' => ['nombre' => 'Usuario']]]),
            'api.example.test/api/farma/compras/kardex/4' => Http::response(['message' => 'Compra enviada al Kardex.', 'code' => 200, 'data' => ['id' => 4, 'kardex_enviado' => true]]),
            'api.example.test/api/farma/compras/crear' => Http::response(['message' => 'Compra creada.', 'code' => 201, 'data' => true]),
            'api.example.test/api/farma/compras/borrador' => Http::response(['message' => 'Borrador creado.', 'code' => 201, 'data' => ['id' => 4]]),
            'api.example.test/api/farma/compras/borrador/4' => Http::response(['message' => 'Borrador sincronizado.', 'code' => 200, 'data' => ['id' => 4]]),
            'api.example.test/api/farma/compras/borrador/4/detalle' => Http::response(['message' => 'Detalle sincronizado.', 'code' => 200, 'data' => ['id' => 4]]),
            'api.example.test/api/farma/compras/borrador/4/detalle/9' => Http::response(['message' => 'Detalle eliminado.', 'code' => 200, 'data' => ['id' => 4]]),
            'api.example.test/api/farma/compras/finalizar/4' => Http::response(['message' => 'Compra finalizada.', 'code' => 200, 'data' => ['id' => 4]]),
            'api.example.test/api/farma/compras/buscar/4' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['id' => 4]]),
            'api.example.test/api/farma/compras/actualizar' => Http::response(['message' => 'Compra actualizada.', 'code' => 200, 'data' => true]),
            'api.example.test/api/farma/compras/eliminar/4' => Http::response(['message' => 'Compra eliminada.', 'code' => 200, 'data' => true]),
            'api.example.test/api/farma/compras/documento/4' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['image' => 'aW1hZ2U=']]),
            'api.example.test/api/farma/compras/transacciones/listar*' => Http::response(['message' => 'ok', 'code' => 200, 'data' => [['id' => 8]], 'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1]]),
            'api.example.test/api/farma/compras/transacciones/opciones' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['tipos' => []]]),
            'api.example.test/api/farma/compras/transacciones/crear' => Http::response(['message' => 'Transacción creada.', 'code' => 201, 'data' => true]),
            'api.example.test/api/farma/compras/transacciones/buscar/8' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['id' => 8]]),
            'api.example.test/api/farma/compras/transacciones/actualizar' => Http::response(['message' => 'Transacción actualizada.', 'code' => 200, 'data' => true]),
            'api.example.test/api/farma/compras/transacciones/eliminar/8' => Http::response(['message' => 'Transacción eliminada.', 'code' => 200, 'data' => true]),
            'api.example.test/api/farma/compras/transacciones/documento/8' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['image' => 'aW1hZ2U=']]),
        ]);

        $service = app(PurchaseApiService::class);
        $this->assertSame(4, $service->list(2, 'SOLFAHSA')->items[0]['id']);
        $service->options();
        $service->options(compact: true);
        $this->assertSame('Proveedor Médico', $service->providers(2, 'Médico')->items[0]['nombre']);
        $service->providerImage(3);
        $this->assertSame('Usuario', $service->report(4)['usuario']['nombre']);
        $this->assertTrue($service->sendToKardex(4)['kardex_enviado']);
        $service->create(['nro' => 'F-1']);
        $service->createDraft(['nro' => 'F-1']);
        $service->syncDraft(4, ['nro' => 'F-1']);
        $service->syncDetail(4, ['producto' => 1]);
        $service->deleteDetail(4, 9);
        $service->finalize(4);
        $service->find(4);
        $service->update(4, ['nro' => 'F-1']);
        $service->delete(4);
        $service->document(4);
        $this->assertSame(8, $service->listTransactions()->items[0]['id']);
        $service->transactionOptions();
        $service->createTransaction(['compra' => 4]);
        $service->findTransaction(8);
        $service->updateTransaction(8, ['compra' => 4]);
        $service->deleteTransaction(8);
        $service->transactionDocument(8);

        foreach ([
            '/listar' => 'cmpbtn01', '/opciones' => 'cmpbtn07', '/proveedores' => 'cmpbtn07', '/proveedor-imagen/3' => 'cmpbtn07', '/reporte/4' => 'cmpbtn08', '/kardex/4' => 'cmpbtn09', '/crear' => 'cmpbtn02', '/buscar/4' => 'cmpbtn03',
            '/actualizar' => 'cmpbtn04', '/eliminar/4' => 'cmpbtn05', '/documento/4' => 'cmpbtn06',
            '/borrador' => 'cmpbtn02', '/borrador/4' => 'cmpbtn04', '/borrador/4/detalle' => 'cmpbtn04',
            '/borrador/4/detalle/9' => 'cmpbtn04', '/finalizar/4' => 'cmpbtn04',
            '/transacciones/listar' => 'ctrbtn01', '/transacciones/opciones' => 'ctrbtn07', '/transacciones/crear' => 'ctrbtn02',
            '/transacciones/buscar/8' => 'ctrbtn03', '/transacciones/actualizar' => 'ctrbtn04',
            '/transacciones/eliminar/8' => 'ctrbtn05', '/transacciones/documento/8' => 'ctrbtn06',
        ] as $suffix => $code) {
            Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), $suffix) && $request->hasHeader('Action-Btn', $code));
        }

        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/listar')
            && ($request->data()['page'] ?? null) === 2
            && ($request->data()['search'] ?? null) === 'SOLFAHSA');
        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/proveedores')
            && ($request->data()['page'] ?? null) === 2
            && ($request->data()['per_page'] ?? null) === 10
            && ($request->data()['search'] ?? null) === 'Médico');
    }
}
