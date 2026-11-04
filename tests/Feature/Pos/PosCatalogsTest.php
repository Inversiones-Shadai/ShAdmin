<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\Catalogos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PosCatalogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'test-token');
        $this->permissions(range(1, 5));
        $this->fakeApi();
    }

    public function test_catalog_screen_and_navigation_use_portal_permissions(): void
    {
        $this->get(route('pos.catalogos'))->assertOk()->assertSee('Efectivo de prueba')->assertSee('Nuevo medio de pago');
        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="permission-view-pos-cop"', false);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/pos/catalogos/listar') && $request->hasHeader('Action-Btn', 'copbtn01'));
        $this->permissions([]);
        $this->get(route('pos.catalogos'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('data-test="permission-view-pos-cop"', false);
    }

    public function test_create_cash_method_sends_stock_flags_and_idempotency(): void
    {
        Livewire::test(Catalogos::class)->call('openCreate')->call('rememberSelectorChoice', 'pos-stocks', '11')->set('stockId', '11')->set('codigo', 'EFECTIVO')
            ->set('descripcion', 'Efectivo de prueba')->set('esEfectivo', true)->call('save')
            ->assertHasNoErrors()->assertSet('showFormModal', false)->assertSet('pendingId', null);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Action-Btn', 'copbtn02') && $request->hasHeader('Idempotency-Key')
            && $request['stock_id'] === 11 && $request['tipo'] === 'MEDIO_PAGO' && $request['activo'] === true
            && $request['datos'] === ['codigo' => 'EFECTIVO', 'descripcion' => 'Efectivo de prueba', 'es_efectivo' => true, 'requiere_referencia' => false]);
    }

    public function test_edit_preserves_stock_and_both_identity_codes_even_if_public_inputs_change(): void
    {
        Livewire::test(Catalogos::class)->call('openEdit', 5)->set('stockId', '99')->set('codigo', 'OTRO')
            ->set('descripcion', 'Descripción actualizada')->set('requiereReferencia', true)->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request->hasHeader('Action-Btn', 'copbtn04')
            && $request['stock_id'] === 11 && $request['codigo'] === 'pago-efectivo' && $request['datos']['codigo'] === 'EFECTIVO'
            && $request['datos']['requiere_referencia'] === true);
    }

    public function test_deactivation_preserves_record_and_uses_correct_action(): void
    {
        Livewire::test(Catalogos::class)->call('confirmDeactivate', 5)->assertSet('deactivatingName', 'Efectivo de prueba')
            ->call('deactivate')->assertHasNoErrors()->assertSet('showDeactivateModal', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/pos/catalogos/desactivar/5') && $request->hasHeader('Action-Btn', 'copbtn05'));
    }

    public function test_list_only_blocks_all_mutation_actions(): void
    {
        $this->permissions([1]);
        Livewire::test(Catalogos::class)->assertDontSee('data-test="create-catalogospos"', false)->call('openCreate')->assertForbidden();
        Livewire::test(Catalogos::class)->call('openEdit', 5)->assertForbidden();
        Livewire::test(Catalogos::class)->call('confirmDeactivate', 5)->assertForbidden();
        Livewire::test(Catalogos::class)->call('save')->assertForbidden();
        Livewire::test(Catalogos::class)->call('deactivate')->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }

    public function test_create_requires_stock_lookup_permission_and_a_known_stock(): void
    {
        $this->permissions([1, 2], false);
        Livewire::test(Catalogos::class)->call('openCreate')->assertHasErrors(['form'])->call('save')->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/config/stock'));
        $this->permissions([1, 2]);
        Livewire::test(Catalogos::class)->call('openCreate')->set('stockId', '999')->set('codigo', 'CASH')->set('descripcion', 'TEST')
            ->call('save')->assertHasErrors(['stockId']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }

    public function test_uncertain_save_reuses_id_and_content_and_blocks_another_operation(): void
    {
        $attempts = [];
        $this->fakeApi(['api.example.test/api/pos/catalogos/crear' => function (Request $request) use (&$attempts) {
            $attempts[] = [$request->header('Idempotency-Key'), $request->data()];
            if (count($attempts) === 1) {
                throw new ConnectionException('test timeout');
            }

            return Http::response(['code' => 201, 'message' => 'Guardado', 'data' => []], 201);
        }]);
        $component = Livewire::test(Catalogos::class)->call('openCreate')->call('rememberSelectorChoice', 'pos-stocks', '11')->set('stockId', '11')->set('codigo', 'CASH')
            ->set('descripcion', 'TEST')->set('esEfectivo', true)->call('save')->assertHasErrors(['form']);
        $requestId = $component->get('pendingId');
        $component->call('closeForm')->assertSet('showFormModal', true)->call('confirmDeactivate', 5)->assertSet('deactivatingId', null)
            ->set('descripcion', 'CAMBIO')->call('save')->assertHasErrors(['form'])->assertSet('pendingId', $requestId)
            ->set('descripcion', 'TEST')->call('save')->assertHasNoErrors()->assertSet('pendingId', null);
        $this->assertCount(2, $attempts);
        $this->assertSame($attempts[0], $attempts[1]);
    }

    public function test_validation_response_maps_fields_and_allows_correction(): void
    {
        $this->fakeApi(['api.example.test/api/pos/catalogos/crear' => Http::response(['code' => 422, 'message' => 'Datos inválidos',
            'data' => ['errors' => ['datos.descripcion' => ['Revisa la descripción.']]]], 422)]);
        Livewire::test(Catalogos::class)->call('openCreate')->call('rememberSelectorChoice', 'pos-stocks', '11')->set('stockId', '11')->set('codigo', 'CASH')->set('descripcion', 'TEST')
            ->call('save')->assertHasErrors(['descripcion'])->assertSet('pendingId', null)->call('closeForm')->assertSet('showFormModal', false);
    }

    public function test_expired_api_session_redirects_to_login(): void
    {
        $this->fakeApi(['api.example.test/api/pos/catalogos/listar*' => Http::response(['message' => 'Unauthenticated.'], 401)]);
        Livewire::test(Catalogos::class)->assertRedirect(route('login'));
    }

    private function permissions(array $actions, bool $stocks = true): void
    {
        session()->put('auth.api.spermisos', [
            ['modulo_l' => ['codigo' => 'pos'], 'vistas_l' => [['vistai' => ['codigo' => 'cop'],
                'accionesi' => array_map(fn (int $i): array => ['codigo' => 'copbtn0'.$i], $actions)]]],
            ['modulo_l' => ['codigo' => 'cfg'], 'vistas_l' => $stocks ? [['vistai' => ['codigo' => 'stk'], 'accionesi' => [['codigo' => 'stkbnt02']]]] : []],
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function fakeApi(array $overrides = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $record = ['id' => 5, 'stock_id' => 11, 'tipo' => 'MEDIO_PAGO', 'codigo' => 'pago-efectivo', 'activo' => true,
            'datos' => ['codigo' => 'EFECTIVO', 'descripcion' => 'Efectivo de prueba', 'es_efectivo' => true, 'requiere_referencia' => false]];
        $this->fakeSelectorChoice('pos-stocks', ['id' => 11, 'descripcion' => 'Stock de prueba']);
        Http::fake($overrides + [
            'api.example.test/api/pos/catalogos/listar*' => Http::response(['code' => 200, 'data' => [$record], 'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1]]),
            'api.example.test/api/pos/catalogos/buscar/5' => Http::response(['code' => 200, 'data' => $record]),
            'api.example.test/api/config/stock/listar*' => Http::response(['code' => 200, 'data' => [['id' => 11, 'descripcion' => 'Stock de prueba']]]),
            'api.example.test/api/pos/catalogos/*' => Http::response(['code' => 200, 'message' => 'Guardado', 'data' => $record]),
        ]);
    }
}
