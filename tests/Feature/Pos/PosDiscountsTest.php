<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\Descuentos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PosDiscountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'test-token');
        $this->permissions(range(1, 6));
        $this->fakeApi();
    }

    public function test_navigation_and_screen_require_their_own_portal_permission(): void
    {
        $this->get(route('pos.descuentos'))->assertOk()->assertSee('TEST categoría')->assertSee('Todos los descuentos')->assertDontSee('modes.dto1', false)->assertSee('para todos los stocks')->assertDontSee('Selecciona el stock');
        $this->get(route('dashboard'))->assertOk()->assertSee('permission-view-pos-dcp');
        $this->permissions([]);
        $this->get(route('pos.descuentos'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('permission-view-pos-dcp');
    }

    public function test_create_uses_one_rule_for_all_discounts_and_lookup_without_inventory_or_stocks_grants(): void
    {
        Livewire::test(Descuentos::class)->call('openCreate')->set('referenceId', '10')
            ->set('mode', 'EXCLUIR')
            ->call('save')->assertHasNoErrors()->assertSet('showFormModal', false)->assertSet('pending', null);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Action-Btn', 'dcpbtn02') && $request->hasHeader('Idempotency-Key')
            && ! array_key_exists('stock_id', $request->data()) && $request['alcance'] === 'CATEGORIA' && $request['referencia_id'] === '10'
            && $request['regla'] === ['modo' => 'EXCLUIR'] && ! isset($request['reglas']));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/config/stock') || ($request->data()['tipo'] ?? null) === 'STOCK');
    }

    public function test_search_button_opens_product_images_with_paging_and_selects_only_a_listed_product(): void
    {
        $image = 'data:image/png;base64,'.base64_encode('TEST placeholder image');
        $this->fakeApi(['api.example.test/api/pos/descuentos/opciones*' => function (Request $r) use ($image) {
            $page = (int) ($r['page'] ?? 1);
            $rows = ($r['tipo'] ?? '') === 'PRODUCTO'
                ? [['id' => $page === 1 ? 20 : 21, 'nombre' => $page === 1 ? 'TEST leche' : 'TEST leche segunda', 'codigo' => 'TEST-P', 'imagen' => $page === 1 ? $image : null]]
                : [['id' => 10, 'nombre' => 'TEST categoría']];

            return Http::response(['code' => 200, 'data' => $rows, 'meta' => ['total' => 11, 'per_page' => 10, 'current_page' => $page, 'last_page' => 2]]);
        }]);
        $component = Livewire::test(Descuentos::class)->call('openCreate')->set('alcance', 'PRODUCTO')
            ->assertSet('showProductPicker', false)->assertSee('Buscar producto')->call('openProductPicker')
            ->assertSet('showProductPicker', true)->assertSee('Buscar por nombre')->assertSee($image, false)->assertSee('10 productos por página')
            ->set('search', 'leche')->assertSet('referencePage', 1)
            ->call('searchReferences', 2)->assertSet('referencePage', 2)->assertSee('TEST leche segunda')->assertSet('search', 'leche')
            ->call('selectProduct', 21)->assertSet('referenceId', '21')->assertSet('showProductPicker', false);
        $component->call('openProductPicker')->call('closeProductPicker')->assertSet('referenceId', '21');
        Http::assertSent(fn (Request $r): bool => ($r->data()['tipo'] ?? null) === 'PRODUCTO' && $r['buscar'] === 'leche' && (int) $r['page'] === 2 && $r->hasHeader('Action-Btn', 'dcpbtn02'));
        $component->set('mode', 'EXCLUIR')->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r['alcance'] === 'PRODUCTO' && $r['referencia_id'] === '21');
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/farma/'));
    }

    public function test_product_picker_failure_drops_old_results_and_cannot_select_an_unlisted_product(): void
    {
        $this->fakeApi(['api.example.test/api/pos/descuentos/opciones*' => fn (Request $r) => ($r['tipo'] ?? '') === 'PRODUCTO'
            ? Http::response(['code' => 500, 'message' => 'TEST no disponible'], 500)
            : Http::response(['code' => 200, 'data' => [['id' => 10, 'nombre' => 'TEST categoría']]])]);
        Livewire::test(Descuentos::class)->call('openCreate')->set('alcance', 'PRODUCTO')->call('openProductPicker')
            ->assertSet('references', [])->assertHasErrors('form')->call('selectProduct', 999)->assertNotFound();
        $this->permissions([1]);
        Livewire::test(Descuentos::class)->call('openProductPicker')->assertForbidden();
    }

    public function test_edit_preserves_identity_and_revision_and_view_never_writes(): void
    {
        Livewire::test(Descuentos::class)->call('openRecord', 5, true)->set('referenceId', '999')
            ->set('alcance', 'PRODUCTO')->set('mode', 'LOTE')->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $r): bool => $r->method() === 'PUT' && $r->hasHeader('Action-Btn', 'dcpbtn04')
            && ! array_key_exists('stock_id', $r->data()) && $r['alcance'] === 'CATEGORIA' && $r['referencia_id'] === '10'
            && $r['revision_esperada'] === 2 && $r['regla']['modo'] === 'LOTE');
        $this->fakeApi();
        Livewire::test(Descuentos::class)->call('openRecord', 5)->assertSet('readOnly', true)->call('save')->assertForbidden();
        Http::assertNotSent(fn (Request $r): bool => $r->method() !== 'GET');
    }

    public function test_uncertain_operation_survives_reload_and_retries_original_payload(): void
    {
        $attempts = [];
        $this->fakeApi(['api.example.test/api/pos/descuentos/crear' => function (Request $r) use (&$attempts) {
            $attempts[] = [$r->header('Idempotency-Key'), $r->data()];
            if (count($attempts) === 1) {
                throw new ConnectionException('TEST timeout');
            }

            return Http::response(['code' => 201, 'message' => 'Guardado', 'data' => []], 201);
        }]);
        $component = Livewire::test(Descuentos::class)->call('openCreate')->set('referenceId', '10')
            ->set('mode', 'EXCLUIR')->call('save')->assertHasErrors('form');
        $pending = $component->get('pending');
        $component->call('openCreate')->assertSet('pending', $pending)->call('closeForm')->assertSet('showFormModal', true);
        Livewire::test(Descuentos::class)->assertSet('pending', $pending)->call('retry')->assertSet('pending', null)->assertHasNoErrors();
        $this->assertCount(2, $attempts);
        $this->assertSame($attempts[0], $attempts[1]);
    }

    public function test_list_and_view_permissions_never_allow_mutation_and_deactivation_is_independent(): void
    {
        $this->permissions([1, 3]);
        Livewire::test(Descuentos::class)->call('openRecord', 5)->assertSet('readOnly', true);
        Livewire::test(Descuentos::class)->call('openCreate')->assertForbidden();
        Livewire::test(Descuentos::class)->call('openRecord', 5, true)->assertForbidden();
        Livewire::test(Descuentos::class)->call('deactivate', 5)->assertForbidden();
        Http::assertNotSent(fn (Request $r): bool => $r->method() !== 'GET');
        $this->permissions([1, 5]);
        Livewire::test(Descuentos::class)->call('deactivate', 5)->assertSet('pending', null);
        Http::assertSent(fn (Request $r): bool => $r->method() === 'DELETE' && $r->hasHeader('Action-Btn', 'dcpbtn05')
            && $r['revision_esperada'] === 2 && $r->hasHeader('Idempotency-Key'));
    }

    public function test_delete_button_and_operation_require_their_own_permission(): void
    {
        $this->permissions(range(1, 5));
        Livewire::test(Descuentos::class)->assertDontSee('delete-discountspos', false)->call('delete', 5)->assertForbidden();
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/eliminar/'));
        $this->permissions([1, 6]);
        Livewire::test(Descuentos::class)->assertSee('delete-discountspos', false)->assertSee('Esta acción no se puede deshacer.')
            ->call('delete', 5)->assertHasNoErrors()->assertSet('pending', null);
        Http::assertSent(fn (Request $r): bool => $r->method() === 'DELETE'
            && str_ends_with($r->url(), '/pos/descuentos/eliminar/5') && $r->hasHeader('Action-Btn', 'dcpbtn06')
            && $r['revision_esperada'] === 2 && $r->hasHeader('Idempotency-Key'));
    }

    public function test_delete_is_available_for_inactive_rules_and_rejects_unlisted_ids(): void
    {
        $record = ['id' => 5, 'tipo' => 'DESCUENTO', 'activo' => false,
            'datos' => ['version' => 2, 'revision' => 3, 'alcance' => 'PRODUCTO', 'referencia_id' => '20',
                'descripcion' => 'TEST producto', 'regla' => ['modo' => 'EXCLUIR']]];
        $this->fakeApi(['api.example.test/api/pos/descuentos/listar*' => Http::response(['code' => 200, 'data' => [$record]])]);
        Livewire::test(Descuentos::class)->assertSee('delete-discountspos', false)->assertDontSee('Desactivar')
            ->call('delete', 5)->assertHasNoErrors();
        Http::assertSent(fn (Request $r): bool => $r->method() === 'DELETE' && $r['revision_esperada'] === 3);
        Livewire::test(Descuentos::class)->call('delete', 999)->assertNotFound();
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/eliminar/999'));
    }

    public function test_uncertain_delete_survives_reload_and_retries_original_request(): void
    {
        $attempts = [];
        $this->fakeApi(['api.example.test/api/pos/descuentos/eliminar/5' => function (Request $r) use (&$attempts) {
            $attempts[] = [$r->header('Idempotency-Key'), $r->data()];
            if (count($attempts) === 1) {
                throw new ConnectionException('TEST timeout');
            }

            return Http::response(['code' => 200, 'message' => 'Regla eliminada.', 'data' => []]);
        }]);
        $component = Livewire::test(Descuentos::class)->call('delete', 5)->assertHasErrors('form')->assertSee('eliminación');
        $pending = $component->get('pending');
        $component->call('deactivate', 5)->call('delete', 5)->assertSet('pending', $pending);
        $this->permissions([1, 5]);
        Livewire::test(Descuentos::class)->call('retry')->assertForbidden();
        $this->permissions([1, 6]);
        Livewire::test(Descuentos::class)->assertSet('pending', $pending)->call('retry')->assertHasNoErrors()->assertSet('pending', null);
        $this->assertCount(2, $attempts);
        $this->assertSame($attempts[0], $attempts[1]);
    }

    public function test_confirmed_delete_refreshes_list_and_displays_success(): void
    {
        $deleted = false;
        $record = ['id' => 5, 'tipo' => 'DESCUENTO', 'activo' => true,
            'datos' => ['version' => 2, 'revision' => 2, 'alcance' => 'CATEGORIA', 'referencia_id' => '10',
                'descripcion' => 'TEST categoría', 'regla' => ['modo' => 'EXCLUIR']]];
        $this->fakeApi([
            'api.example.test/api/pos/descuentos/listar*' => function () use (&$deleted, $record) {
                return Http::response(['code' => 200, 'data' => $deleted ? [] : [$record]]);
            },
            'api.example.test/api/pos/descuentos/eliminar/5' => function () use (&$deleted) {
                $deleted = true;

                return Http::response(['code' => 200, 'message' => 'Regla eliminada.', 'data' => []]);
            },
        ]);
        Livewire::test(Descuentos::class)->call('delete', 5)->assertSet('records', [])->assertSet('pending', null)
            ->assertSee('No hay reglas para mostrar.')->assertDispatched('sweetalert:toast', icon: 'success', title: 'Regla eliminada.');
    }

    public function test_individual_percentage_override_cannot_be_saved(): void
    {
        Livewire::test(Descuentos::class)->call('openCreate')->set('referenceId', '10')
            ->set('mode', 'PORCENTAJE')->call('save')->assertHasErrors('mode');
        Http::assertNotSent(fn (Request $r): bool => $r->method() !== 'GET');
    }

    public function test_legacy_mixed_rule_requires_an_explicit_general_choice(): void
    {
        $record = ['id' => 5, 'stock_id' => null, 'tipo' => 'DESCUENTO', 'activo' => true,
            'datos' => ['version' => 1, 'revision' => 2, 'alcance' => 'CATEGORIA', 'referencia_id' => '10', 'descripcion' => 'TEST categoría',
                'reglas' => ['dto1' => ['modo' => 'EXCLUIR'], 'dto2' => ['modo' => 'LOTE'], 'dto3' => ['modo' => 'HEREDAR']]]];
        $this->fakeApi(['api.example.test/api/pos/descuentos/buscar/5' => Http::response(['code' => 200, 'data' => $record])]);
        Livewire::test(Descuentos::class)->call('openRecord', 5, true)->assertSet('mode', '')
            ->call('save')->assertHasErrors('mode')->set('mode', 'EXCLUIR')->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $r): bool => $r->method() === 'PUT' && $r['regla'] === ['modo' => 'EXCLUIR']);
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'pos'], 'vistas_l' => [['vistai' => ['codigo' => 'dcp'],
            'accionesi' => array_map(fn (int $i): array => ['codigo' => 'dcpbtn0'.$i], $actions)]]]]);
    }

    private function fakeApi(array $overrides = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $record = ['id' => 5, 'stock_id' => null, 'tipo' => 'DESCUENTO', 'codigo' => 'categoria-10', 'activo' => true,
            'datos' => ['version' => 2, 'revision' => 2, 'alcance' => 'CATEGORIA', 'referencia_id' => '10', 'descripcion' => 'TEST categoría',
                'regla' => ['modo' => 'EXCLUIR']]];
        Http::fake($overrides + [
            'api.example.test/api/pos/descuentos/listar*' => Http::response(['code' => 200, 'data' => [$record], 'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1]]),
            'api.example.test/api/pos/descuentos/buscar/5' => Http::response(['code' => 200, 'data' => $record]),
            'api.example.test/api/pos/descuentos/opciones*' => Http::response(['code' => 200, 'data' => [['id' => 11, 'nombre' => 'TEST stock'], ['id' => 10, 'nombre' => 'TEST categoría']]]),
            'api.example.test/api/pos/descuentos/*' => Http::response(['code' => 200, 'message' => 'Guardado', 'data' => $record]),
        ]);
    }
}
