<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\Compras;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PosPurchasesTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'synthetic-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'synthetic-token');
        $this->permissions([1, 2, 3]);
        Http::preventStrayRequests();
        Http::fake([
            'api.example.test/api/pos/compras/listar*' => Http::response(['code' => 200, 'data' => [$this->row()], 'meta' => ['total' => 31, 'current_page' => 1, 'last_page' => 2]]),
            'api.example.test/api/pos/compras/filtrar*' => Http::response(['code' => 200, 'data' => [$this->row()], 'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1]]),
            'api.example.test/api/pos/compras/opciones/*' => Http::response(['code' => 200, 'data' => [['id' => '1', 'name' => 'TEST option']]]),
            'api.example.test/api/pos/compras/buscar/*' => Http::response(['code' => 200, 'data' => [...$this->row(), 'reason' => null, 'equipment' => 'TEST-PC', 'subtotal' => '200.25', 'tax' => '30.04', 'lines' => [[
                'code' => 'TEST-001', 'description' => 'TEST producto', 'quantity' => '2.0000', 'purchase_factor' => '100.0000', 'base_quantity' => '200.0000',
                'lot' => 'TEST-LOT', 'manufactured' => '2026-01-01', 'expires' => '2027-01-01', 'cost' => '100.1234', 'has_tax' => true, 'total' => '230.29',
            ]]]]),
        ]);
    }

    private function row(): array
    {
        return ['id' => self::ID, 'invoice_cai' => '00001998', 'date' => '2026-10-11', 'stock' => 'TEST Stock', 'supplier' => 'TEST proveedor', 'operator' => 'TEST operador', 'total' => '230.29', 'status' => 'accepted', 'status_label' => 'Aceptada'];
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'pos'], 'vistas_l' => [['vistai' => ['codigo' => 'cpp'], 'accionesi' => array_map(fn ($n) => ['codigo' => 'cppbtn0'.$n], $actions)]]]]);
    }

    public function test_page_and_menu_are_under_pos_and_document_contains_original_unit(): void
    {
        $this->get(route('pos.compras'))->assertOk()->assertSee('Compras POS')->assertSee('00001998');
        $this->get(route('dashboard'))->assertOk()->assertSee('permission-view-pos-cpp');
        Livewire::test(Compras::class)->call('openDocument', self::ID)->assertSet('showDocument', true)->assertSee('100.0000')->assertSee('200.0000')->assertSee('TEST producto')->call('closeDocument')->assertSet('document', null);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Action-Btn', 'cppbtn03') && str_contains($r->url(), '/buscar/'));
    }

    public function test_filters_search_choices_send_own_permission_and_reset_page(): void
    {
        Livewire::test(Compras::class)->call('goToPage', 2)->call('openFilters')->set('supplierSearch', 'TEST')->set('stockId', '1')->set('supplierId', '1')
            ->set('productId', '10000000000000000001')->set('dateFrom', '2026-10-11')->set('dateTo', '2026-10-11')->call('applyFilters')->assertHasNoErrors()->assertSet('page', 1)->assertSet('showFilters', false)
            ->call('clearFilters')->assertSet('filters', []);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Action-Btn', 'cppbtn02') && str_contains($r->url(), '/filtrar') && $r['product_id'] === '10000000000000000001');
    }

    public function test_list_permission_does_not_enable_details_or_filter_and_direct_calls_are_denied(): void
    {
        $this->permissions([1]);
        Livewire::test(Compras::class)->assertDontSee('data-test="show-purchasepos"', false)->assertDontSee('data-test="filter-purchasespos"', false)->call('openDocument', self::ID)->assertForbidden();
        Livewire::test(Compras::class)->call('openFilters')->assertForbidden();
        $this->permissions([]);
        $this->get(route('pos.compras'))->assertForbidden();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/buscar/') || str_contains($r->url(), '/opciones/'));
    }

    public function test_failed_refresh_clears_previous_rows_and_session_expiry_redirects(): void
    {
        $component = Livewire::test(Compras::class)->assertSee('00001998');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.example.test/api/pos/compras/listar*' => Http::response(['code' => 503, 'message' => 'No disponible'], 503)]);
        $component->call('refresh')->assertSet('purchases', [])->assertSee('No disponible')->assertDontSee('00001998');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.example.test/api/pos/compras/listar*' => Http::response(['code' => 401, 'message' => 'Sesión expirada'], 401)]);
        $component->call('refresh')->assertRedirect(route('login'));
    }

    public function test_invalid_dates_do_not_send_filter_or_modify_receipts(): void
    {
        Livewire::test(Compras::class)->call('openFilters')->set('dateFrom', '2026-10-11')->set('dateTo', '2026-10-10')->call('applyFilters')->assertHasErrors('dateTo')->assertSet('showFilters', true);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/filtrar') || $r->method() !== 'GET');
    }
}
