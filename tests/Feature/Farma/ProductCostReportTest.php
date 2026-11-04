<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\ReporteCosteo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCostReportTest extends TestCase
{
    use RefreshDatabase;

    private array $report;

    private bool $failReport = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        $this->permissions(['rctbtn01', 'rctbtn02']);
        $this->report = require base_path('tests/Fixtures/product-cost-report.php');
        Http::fake(['api.example.test/api/farma/reporte-costeo/consultar*' => fn () => $this->failReport
            ? Http::response(['code' => 503, 'message' => 'Consulta no disponible'], 503)
            : Http::response(['code' => 200, 'message' => 'ok', 'data' => $this->report])]);
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'frm'], 'vistas_l' => [[
            'vistai' => ['codigo' => 'rct'], 'accionesi' => array_map(fn ($code): array => ['codigo' => $code], $actions)]]]]);
    }

    public function test_report_is_in_farma_reports_and_waits_for_an_explicit_query(): void
    {
        $this->get(route('farma.reportes.costeo'))->assertOk()->assertSee('Costeo de productos');
        $this->get(route('dashboard'))->assertSee('permission-view-frm-rct')->assertSee('permission-submenu-frm-reports');
        Http::assertNothingSent();
        Livewire::test(ReporteCosteo::class)->call('loadReport')->assertHasErrors('productId')
            ->set('productId', '4')->set('providerId', '2')->set('dateFrom', '2026-01-01')->set('dateTo', '2026-01-31')
            ->call('loadReport')->assertHasNoErrors()->assertSee('data-test="cost-chart"', false)
            ->assertSee('Producto de prueba')->assertSee('COST-3')->assertSee('Aumentó')->assertSee('Disminuyó')
            ->assertSet('report.resumen.costo_reciente', 9);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Action-Btn', 'rctbtn01')
            && $request['producto_id'] === 4 && $request['proveedor_id'] === '2'
            && $request['fecha_desde'] === '2026-01-01' && $request['fecha_hasta'] === '2026-01-31');
    }

    public function test_invalid_ranges_do_not_query_and_clear_restores_the_idle_state(): void
    {
        $component = Livewire::test(ReporteCosteo::class)->set('productId', '4')->set('dateFrom', '2026-02-01')->set('dateTo', '2026-01-01')
            ->call('loadReport')->assertHasErrors('dateTo');
        Http::assertNothingSent();
        $component->set('dateTo', '2026-02-28')->call('loadReport')->call('clearFilters')->assertSet('report', [])->assertSet('productId', '');
    }

    public function test_table_pagination_does_not_reload_or_cut_the_chart_history(): void
    {
        $first = $this->report['compras'][0];
        $this->report['compras'] = array_map(fn ($id): array => array_replace($first, ['id' => $id, 'factura' => 'PAG-'.$id]), range(1, 30));
        $this->report['resumen']['compras'] = 30;
        Livewire::test(ReporteCosteo::class)->set('productId', '4')->call('loadReport')->assertSee('PAG-30')->assertDontSee('PAG-1<', false)
            ->call('goToPage', 2)->assertSet('page', 2)->assertSee('PAG-1')->assertSet('report.resumen.compras', 30);
        Http::assertSentCount(1);
    }

    public function test_errors_clear_old_costs_and_unknown_values_and_empty_history_remain_distinct(): void
    {
        $component = Livewire::test(ReporteCosteo::class)->set('productId', '4')->call('loadReport');
        $this->failReport = true;
        $component->call('loadReport')->assertSet('report', [])->assertSee('Consulta no disponible');
        $this->failReport = false;
        $this->report['compras'] = [];
        $this->report['resumen'] = array_replace($this->report['resumen'], ['compras' => 0, 'costo_inicial' => null,
            'costo_reciente' => null, 'costo_minimo' => null, 'costo_maximo' => null, 'variacion' => null, 'variacion_pct' => null]);
        $component->call('loadReport')->assertSee('Sin compras en el período')->assertSee('Sin dato')->assertDontSee('data-test="cost-chart"', false);
    }

    public function test_report_and_product_selection_have_independent_permissions(): void
    {
        $this->permissions(['rctbtn02']);
        $this->get(route('farma.reportes.costeo'))->assertForbidden();
        Livewire::test(ReporteCosteo::class)->assertForbidden();
        $this->permissions(['rctbtn01']);
        Livewire::test(ReporteCosteo::class)->assertSee('Se necesita el permiso')
            ->call('openPicker', 'products')->assertForbidden();
    }

    public function test_product_picker_uses_only_the_cost_report_permission(): void
    {
        $this->fakeSelectorChoice('cost-report-products', ['id' => 4, 'codigo' => 'PR-4', 'descripcion' => 'Producto de prueba']);
        Livewire::test(ReporteCosteo::class)->call('openFilters')->call('openPicker', 'products')
            ->assertSet('pickerOptions.0.id', 4)->call('selectPickerOption', 4)
            ->assertSet('productId', '4')->assertSet('productName', 'PR-4 · Producto de prueba')
            ->assertSet('showPicker', false)->assertSet('showFiltersModal', true)->call('loadReport')->assertHasNoErrors()
            ->assertSet('showFiltersModal', false);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/selectors/cost-report-products') && $request->hasHeader('Action-Btn', 'rctbtn02'));
    }

    public function test_product_table_searches_and_paginates_without_loading_costs_until_applied(): void
    {
        Http::fake(['*/selectors/cost-report-products*' => function (Request $request) {
            $page = (int) ($request['page'] ?? 1);
            $id = $page === 1 ? 4 : 14;

            return Http::response(['code' => 200, 'data' => [['id' => (string) $id, 'label' => 'Producto '.$id,
                'record' => ['id' => $id, 'codigo' => 'PR-'.$id, 'descripcion' => 'Producto '.$id]]],
                'meta' => ['current_page' => $page, 'last_page' => 2, 'total' => 11]]);
        }]);
        Livewire::test(ReporteCosteo::class)->assertSee('data-test="cost-filters-button"', false)
            ->assertDontSee('data-remote-select="cost-report-products"', false)
            ->call('openFilters')->call('openPicker', 'products')->assertSee('data-test="cost-picker-table"', false)
            ->assertSee('Código')->assertSee('Producto 4')->assertSet('pickerPage', 1)
            ->call('pickerGoToPage', 2)->assertSet('pickerPage', 2)->assertSee('Producto 14')
            ->call('selectPickerOption', 999)->assertSet('productId', '')
            ->set('pickerSearch', 'PR-4')->assertSet('pickerPage', 1)
            ->call('selectPickerOption', 4)->assertSet('productId', '4')->assertSet('report', []);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/reporte-costeo/consultar'));
        Http::assertSent(fn (Request $request): bool => ($request['page'] ?? null) === 2 && $request->hasHeader('Action-Btn', 'rctbtn02'));
        Http::assertSent(fn (Request $request): bool => ($request['search'] ?? null) === 'PR-4' && ($request['page'] ?? null) === 1);
    }

    public function test_cancel_and_clear_in_grouped_filters_preserve_the_existing_report_until_applied(): void
    {
        $this->fakeSelectorChoice('cost-report-products', ['id' => 4, 'codigo' => 'PR-4', 'descripcion' => 'Producto de prueba']);
        Livewire::test(ReporteCosteo::class)->call('openFilters')->call('openPicker', 'products')->call('selectPickerOption', 4)
            ->set('dateFrom', '2026-01-01')->call('loadReport')
            ->call('openFilters')->call('clearFilters')->assertSet('productId', '')->assertSet('report.producto.id', 4)
            ->call('cancelFilters')->assertSet('productId', '4')->assertSet('productName', 'PR-4 · Producto de prueba')
            ->assertSet('dateFrom', '2026-01-01')->assertSet('showFiltersModal', false)->assertSet('report.producto.id', 4);
        $calls = Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/reporte-costeo/consultar'));
        $this->assertCount(1, $calls);
    }

    public function test_providers_use_the_same_searchable_table_and_can_be_cleared_independently(): void
    {
        $this->fakeSelectorChoice('cost-report-providers', ['id' => 2, 'nombre' => 'Proveedor norte']);
        Livewire::test(ReporteCosteo::class)->set('productId', '4')->call('openFilters')->call('openPicker', 'providers')
            ->assertSee('Filtrar por proveedor')->assertSee('Proveedor norte')->call('selectPickerOption', 2)
            ->assertSet('providerId', '2')->assertSet('providerName', 'Proveedor norte')->assertSet('productId', '4')
            ->call('clearProvider')->assertSet('providerId', '')->assertSet('providerName', '')->assertSet('productId', '4');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/selectors/cost-report-providers') && $request->hasHeader('Action-Btn', 'rctbtn01'));
    }

    public function test_picker_failure_discards_old_options_and_can_be_retried(): void
    {
        $fail = false;
        Http::fake(['*/selectors/cost-report-products*' => function () use (&$fail) {
            return $fail ? Http::response(['code' => 503, 'message' => 'No disponible'], 503)
                : Http::response(['code' => 200, 'data' => [['id' => '4', 'label' => 'Producto',
                    'record' => ['id' => 4, 'codigo' => 'PR-4', 'descripcion' => 'Producto']]],
                    'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 1]]);
        }]);
        $component = Livewire::test(ReporteCosteo::class)->call('openPicker', 'products')->assertCount('pickerOptions', 1);
        $fail = true;
        $component->set('pickerSearch', 'Otra búsqueda')->assertCount('pickerOptions', 0)->assertSee('No fue posible consultar el selector.')
            ->call('selectPickerOption', 4)->assertSet('productId', '');
        $fail = false;
        $component->call('pickerGoToPage', 1)->assertCount('pickerOptions', 1)->call('selectPickerOption', 4)->assertSet('productId', '4');
    }
}
