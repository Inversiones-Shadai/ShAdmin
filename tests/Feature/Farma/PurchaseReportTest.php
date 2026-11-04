<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\ReporteCompras;
use App\Models\User;
use App\Services\Api\PurchaseReportApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseReportTest extends TestCase
{
    use RefreshDatabase;

    private bool $reportFails = false;

    private bool $paymentsFail = false;

    private bool $exportFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        $this->permissions(['rcpbtn01', 'rcpbtn02', 'rcpbtn03', 'rcpbtn04']);
        Http::fake([
            'api.example.test/api/farma/reporte-compras/exportar*' => function (Request $request) {
                $rows = require base_path('tests/Fixtures/purchase-report-export.php');
                $cursor = $request->data()['antes_de'] ?? null;
                if ($cursor !== null && $this->exportFails) {
                    return Http::response(['code' => 503, 'message' => 'Exportación no disponible'], 503);
                }

                return Http::response(['code' => 200, 'message' => 'ok', 'data' => [
                    'items' => $cursor === null ? [$rows[2], $rows[1]] : [$rows[0]],
                    'next_cursor' => $cursor === null ? 2 : null,
                ]]);
            },
            'api.example.test/api/farma/reporte-compras/pagos/1*' => fn (Request $request) => $this->paymentsFail
                ? Http::response(['code' => 503, 'message' => 'Pagos no disponibles'], 503)
                : Http::response(['code' => 200, 'message' => 'ok', 'data' => [
                    'items' => [['id' => (int) $request['page'], 'nro' => 'PAGO-'.$request['page'], 'valor' => 20,
                        'tipo' => ['descripcion' => 'Abono'], 'created_at' => '2026-10-17T10:00:00Z']],
                    'meta' => ['total' => 2, 'current_page' => (int) $request['page'], 'last_page' => 2],
                    'purchase' => ['id' => 1, 'nro' => 'REP-001', 'total_aplicado' => 25, 'saldo' => 85],
                ]]),
            'api.example.test/api/farma/reporte-compras/listar*' => fn (Request $request) => $this->reportFails
                ? Http::response(['code' => 503, 'message' => 'Reporte no disponible'], 503)
                : Http::response([
                    'code' => 200, 'message' => 'ok', 'data' => [
                        'items' => [[
                            'id' => 1, 'nro' => 'REP-001', 'items' => 1, 'plazo' => 30,
                            'estado' => 'pendiente', 'estado_label' => 'Pendiente',
                            'proveedor' => ['nombre' => 'Proveedor A'], 'tipo' => ['descripcion' => 'Crédito'],
                            'subtotal' => 100, 'isv' => 15, 'descuento' => 5, 'total' => 110,
                            'total_aplicado' => 25, 'saldo' => 85, 'total_abonos' => 20,
                        ]],
                        'meta' => ['total' => 50, 'current_page' => (int) ($request['page'] ?? 1), 'last_page' => 2],
                        'totals' => ['subtotal' => 5000, 'isv' => 750, 'descuento' => 250, 'total' => 5500, 'gran_total' => 5500, 'abonos' => 1234.50, 'saldo_pendiente' => 4000],
                    ],
                ]),
            'api.example.test/api/farma/reporte-compras/proveedores*' => Http::response([
                'code' => 200, 'message' => 'ok', 'data' => [['id' => 7, 'nombre' => 'Proveedor A']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);
    }

    public function test_report_has_its_own_submenu_route_and_global_totals(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="permission-submenu-frm-reports"', false)->assertSee('Reporte de compras');
        $this->get(route('farma.reportes.compras'))->assertOk()->assertSee('purchase-report-idle')->assertDontSee('REP-001');
        Http::assertNothingSent();
        Livewire::test(ReporteCompras::class)->call('nextPage')->assertSet('hasLoaded', false);
        Http::assertNothingSent();
        Livewire::test(ReporteCompras::class)->call('loadReport')->assertSee('REP-001')->assertSee('Gran total: L 5,500.00')->assertDontSee('Total de abonos');
        Http::assertSent(fn (Request $request) => $request->hasHeader('Action-Btn', 'rcpbtn01'));
    }

    public function test_filters_reset_pagination_and_credit_displays_all_abonos(): void
    {
        $component = Livewire::test(ReporteCompras::class)->call('loadReport')->call('nextPage')->assertSet('page', 2)
            ->set('type', '2')->assertSet('page', 1)->assertSee('Total de abonos')->assertSee('1,234.50')
            ->assertSeeInOrder(['Total de abonos', 'Saldo pendiente', '4,000.00'])
            ->call('openProviderPicker')->call('selectProvider', 7)->assertSet('provider', '7')
            ->assertSet('providerName', 'Proveedor A')->set('search', 'REP');
        Http::assertSent(fn (Request $request) => ($request->data()['proveedor'] ?? null) === 7 && ($request->data()['tipo'] ?? null) === 2 && ($request->data()['search'] ?? null) === 'REP' && ($request->data()['page'] ?? null) === 1);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Action-Btn', 'rcpbtn02'));
        $component->set('type', '1')->assertDontSee('Total de abonos')->assertDontSee('Saldo pendiente')->call('clearFilters')
            ->assertSet('type', '')->assertSet('provider', '')->assertSet('search', '')
            ->assertSet('hasLoaded', false)->assertSet('purchases', []);
    }

    public function test_report_and_provider_actions_require_their_own_permissions(): void
    {
        $this->permissions(['rcpbtn02']);
        $this->get(route('farma.reportes.compras'))->assertForbidden();
        Livewire::test(ReporteCompras::class)->assertForbidden();
        $this->permissions(['rcpbtn01']);
        Livewire::test(ReporteCompras::class)->call('openProviderPicker')->assertForbidden();
        Livewire::test(ReporteCompras::class)->call('loadReport')->call('viewPayments', 1)->assertForbidden();
    }

    public function test_api_failure_clears_previous_report_totals(): void
    {
        $component = Livewire::test(ReporteCompras::class)->call('loadReport')->assertSet('total', 50);
        $this->reportFails = true;
        $component->set('type', '2')->assertSet('purchases', [])->assertSet('totals', [])->assertSet('total', 0)->assertSee('Reporte no disponible')->assertDontSee('5,500.00');
    }

    public function test_payments_load_only_when_opened_and_paginate_for_the_selected_invoice(): void
    {
        $component = Livewire::test(ReporteCompras::class)->call('loadReport')->assertSee('Ver pagos');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/pagos/'));
        $component->call('viewPayments', 999)->assertSet('showPaymentsModal', false);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/pagos/'));
        $component->call('viewPayments', 1)->assertSet('showPaymentsModal', true)->assertSee('PAGO-1')
            ->assertSet('paymentPurchase.total_aplicado', 25)->assertSet('paymentPurchase.saldo', 85)
            ->call('nextPaymentPage')->assertSet('paymentPage', 2)->assertSee('PAGO-2')->assertDontSee('PAGO-1')
            ->call('previousPaymentPage')->assertSet('paymentPage', 1)
            ->call('closePayments')->assertSet('paymentTransactions', [])->assertSet('paymentPurchase', []);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/pagos/1') && $request->hasHeader('Action-Btn', 'rcpbtn03') && $request['page'] === 2);
    }

    public function test_payment_errors_clear_previous_movements_and_totals(): void
    {
        $component = Livewire::test(ReporteCompras::class)->call('loadReport')->call('viewPayments', 1)->assertSee('PAGO-1');
        $this->paymentsFail = true;
        $component->call('nextPaymentPage')->assertSee('Pagos no disponibles')->assertDontSee('PAGO-1')
            ->assertSet('paymentTransactions', [])->assertSet('paymentPurchase', ['id' => 1]);
    }

    public function test_clearing_filters_does_not_query_all_purchases(): void
    {
        $component = Livewire::test(ReporteCompras::class)->set('type', '2');
        $count = Http::recorded()->count();
        $component->call('clearFilters')->assertSet('hasLoaded', false)->assertSet('totals', []);
        $this->assertSame($count, Http::recorded()->count());
    }

    public function test_export_downloads_pdf_and_excel_with_all_cursor_batches_and_applied_filters(): void
    {
        foreach (['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] as $format => $contentType) {
            Livewire::test(ReporteCompras::class)->set('type', '2')->call('openProviderPicker')->call('selectProvider', 7)
                ->set('search', 'F-')->call('openExport')->assertSet('showExportModal', true)
                ->set('exportFormat', $format)->call('export')->assertHasNoErrors()
                ->assertFileDownloaded(contentType: $contentType)->assertSet('showExportModal', false);
        }
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/exportar')
            && $request->hasHeader('Action-Btn', 'rcpbtn04') && ($request->data()['antes_de'] ?? null) === 2
            && $request['proveedor'] === 7 && $request['tipo'] === 2 && $request['search'] === 'F-');
        $rows = app(PurchaseReportApiService::class)->exportRows();
        $this->assertSame([3, 2, 1], array_column($rows, 'id'));
        $this->assertCount(2, $rows[2]['pagos']);
    }

    public function test_export_requires_permission_loaded_results_and_a_supported_format(): void
    {
        Livewire::test(ReporteCompras::class)->call('openExport')->assertSet('showExportModal', false)
            ->call('export')->assertNoFileDownloaded()->assertSet('exportError', 'Consulta el reporte antes de exportar.');
        Http::assertNothingSent();
        Livewire::test(ReporteCompras::class)->call('loadReport')->call('openExport')
            ->set('exportFormat', 'csv')->call('export')->assertHasErrors('exportFormat')->assertNoFileDownloaded();
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/exportar'));
        $this->permissions(['rcpbtn01']);
        Livewire::test(ReporteCompras::class)->call('loadReport')->call('openExport')->assertForbidden();
        Livewire::test(ReporteCompras::class)->call('loadReport')->call('export')->assertForbidden();
    }

    public function test_export_does_not_download_a_partial_file_if_a_later_batch_fails(): void
    {
        $this->exportFails = true;
        Livewire::test(ReporteCompras::class)->call('loadReport')->call('openExport')->call('export')
            ->assertNoFileDownloaded()->assertSet('showExportModal', true)->assertSee('Exportación no disponible');
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [['vistai' => ['codigo' => 'rcp'], 'accionesi' => array_map(fn (string $code) => ['codigo' => $code], $actions)]],
        ]]);
    }
}
