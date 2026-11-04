<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\Compras;
use App\Livewire\Farma\CompraTransacciones;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseManagementTest extends TestCase
{
    use RefreshDatabase;

    private array $lastCostOverrides = [];

    private bool $costLookupFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.purchase_path' => '/farma/compras',
            'services.shadmin_api.purchase_catalog_path' => '/farma/compras/catalogos',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->permissions());
        Http::preventStrayRequests();
        Http::fake([
            'api.example.test/api/farma/compras/ultimos-costos*' => fn (Request $request) => $this->costLookupFails
                ? Http::response(['message' => 'Error', 'code' => 503], 503)
                : Http::response([
                    'message' => 'ok', 'code' => 200,
                    'data' => array_map(fn ($id): array => ['producto_id' => (int) $id, 'costo' => $this->lastCostOverrides[(int) $id] ?? null], $request->data()['productos']),
                ]),
            'api.example.test/api/farma/compras/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [[
                    'id' => 1, 'nro' => 'F-001', 'items' => 2, 'plazo' => 30, 'subtotal' => 100,
                    'isv' => 15, 'total' => 115, 'total_aplicado' => 40, 'saldo' => 75,
                    'estado' => 'pendiente', 'estado_label' => 'Pendiente', 'vence_el' => '2026-08-31', 'created_at' => '2026-08-03T14:35:00.000000Z',
                    'kardex_enviado' => false, 'kardex_enviado_at' => null,
                    'tiene_documento' => false, 'proveedor' => ['nombre' => 'Proveedor'], 'tipo' => ['descripcion' => 'Crédito'],
                ]],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/compras/transacciones/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 1, 'nro' => 'A-1', 'valor' => 40, 'tiene_documento' => false, 'compra' => ['nro' => 'F-001', 'proveedor' => 'Proveedor'], 'tipo' => ['descripcion' => 'Abono']]],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/compras/opciones*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [
                    'tipos' => [['id' => 2, 'label' => 'Crédito']],
                ],
            ]),
            'api.example.test/api/farma/compras/proveedores*' => function (Request $request) {
                $search = (string) ($request->data()['search'] ?? '');
                $page = (int) ($request->data()['page'] ?? 1);
                $providers = $search === 'Otro'
                    ? [['id' => 2, 'nombre' => 'Otro proveedor', 'tiene_imagen' => false]]
                    : [['id' => $page, 'nombre' => $page === 1 ? 'Proveedor' : 'Proveedor 2', 'tiene_imagen' => $page === 1]];

                return Http::response(['message' => 'ok', 'code' => 200, 'data' => $providers,
                    'meta' => ['total' => 2, 'current_page' => $page, 'last_page' => 2]]);
            },
            'api.example.test/api/farma/compras/crear' => Http::response([
                'message' => 'Compra creada.', 'code' => 201, 'data' => $this->draftPurchase(true),
            ]),
            'api.example.test/api/farma/compras/actualizar' => Http::response([
                'message' => 'Compra actualizada.', 'code' => 200, 'data' => $this->draftPurchase(true),
            ]),
            'api.example.test/api/farma/compras/borrador' => Http::response([
                'message' => 'Borrador creado.', 'code' => 201, 'data' => $this->draftPurchase(),
            ]),
            'api.example.test/api/farma/compras/proveedor-imagen/1' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['image' => $this->pngImage()],
            ]),
            'api.example.test/api/farma/compras/reporte/1' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => $this->purchaseReport(),
            ]),
            'api.example.test/api/farma/compras/kardex/1' => Http::response([
                'message' => 'Compra enviada al Kardex.', 'code' => 200, 'data' => [
                    'id' => 1, 'nro' => 'F-001', 'items' => 2, 'plazo' => 30, 'subtotal' => 100,
                    'isv' => 15, 'total' => 115, 'total_aplicado' => 40, 'saldo' => 75,
                    'estado' => 'pendiente', 'estado_label' => 'Pendiente', 'vence_el' => '2026-08-31', 'created_at' => '2026-08-03T14:35:00.000000Z',
                    'kardex_enviado' => true, 'kardex_enviado_at' => '2026-08-03T15:00:00.000000Z',
                    'tiene_documento' => false, 'proveedor' => ['nombre' => 'Proveedor'], 'tipo' => ['descripcion' => 'Crédito'],
                ],
            ]),
            'api.example.test/api/farma/compras/borrador/10' => Http::response([
                'message' => 'Encabezado sincronizado.', 'code' => 200, 'data' => $this->draftPurchase(),
            ]),
            'api.example.test/api/farma/compras/borrador/10/detalle' => Http::response([
                'message' => 'Producto sincronizado.', 'code' => 200, 'data' => $this->draftPurchase(true),
            ]),
            'api.example.test/api/farma/compras/finalizar/10' => Http::response([
                'message' => 'Compra finalizada.', 'code' => 200, 'data' => [...$this->draftPurchase(true), 'estado' => 'pendiente', 'estado_label' => 'Pendiente'],
            ]),
            'api.example.test/api/farma/compras/transacciones/opciones' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [
                    'compras' => [
                        ['id' => 1, 'label' => 'F-001 · Proveedor · saldo L 75.00', 'saldo' => 75, 'total' => 115, 'total_aplicado' => 40],
                        ['id' => 2, 'label' => 'F-002 · Proveedor · saldo L 200.00', 'saldo' => 200, 'total' => 200, 'total_aplicado' => 0],
                    ],
                    'tipos' => [['id' => 1, 'label' => 'Pago al contado'], ['id' => 2, 'label' => 'Abono'], ['id' => 3, 'label' => 'Nota de crédito']],
                ],
            ]),
            'api.example.test/api/farma/compras/transacciones/crear' => Http::response([
                'message' => 'Pago registrado.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/compras/catalogos/*/listar*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [['id' => 1, 'descripcion' => 'Contado']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);
        $this->fakeSelectorsFromLegacy(['payment-types' => 'farma/compras/transacciones/opciones|tipos', 'payment-purchases' => 'farma/compras/transacciones/opciones|compras']);
    }

    public function test_purchase_submenu_and_all_views_render(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="permission-submenu-frm-purchases"', false);
        $this->get(route('farma.compras'))->assertOk()->assertSee('F-001')->assertSee('Fecha de creación')->assertSee('03/08/2026')->assertSee('Gravado / ISV c/u')->assertSee('Unidad de compra')->assertSee('Pendiente');
        $this->get(route('farma.compras.transacciones'))->assertOk()->assertSee('A-1');
        $this->get(route('farma.compras.tipos-compra'))->assertOk()->assertSee('Contado');
        $this->get(route('farma.compras.tipos-transaccion'))->assertOk()->assertSee('Contado');
    }

    public function test_subtotal_tolerates_incomplete_detail_rows(): void
    {
        Livewire::test(Compras::class)
            ->set('details', [['costo' => '25.00']])
            ->assertViewHas('subtotalCalculado', 0.0)
            ->set('details', [['cantidad' => 2, 'costo' => '25.00']])
            ->assertViewHas('subtotalCalculado', 50.0);
    }

    public function test_purchase_search_updates_after_a_short_pause_and_queries_the_api_database(): void
    {
        Livewire::test(Compras::class)
            ->set('page', 2)
            ->set('search', 'SOLFAHSA')
            ->assertSet('page', 1);

        $view = file_get_contents(resource_path('views/livewire/farma/compras.blade.php'));
        $this->assertIsString($view);
        $this->assertStringContainsString('wire:model.live.debounce.350ms="search"', $view);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/farma/compras/listar')
            && ($request->data()['page'] ?? null) === 1
            && ($request->data()['search'] ?? null) === 'SOLFAHSA');
    }

    public function test_purchase_totals_include_optional_isv_and_line_discounts(): void
    {
        Livewire::test(Compras::class)
            ->set('details', [
                ['cantidad' => 2, 'costo' => '50.03', 'isv' => true, 'descuento' => '5.00'],
                ['cantidad' => 1, 'costo' => '40.00', 'isv' => false, 'descuento' => '2.00'],
            ])
            ->assertViewHas('purchaseTotals', fn (array $totals): bool => $totals['subtotal'] === 140.06
                && $totals['isv'] === 15.0
                && $totals['descuento'] === 12.0
                && $totals['total'] === 143.06
                && $totals['unitTaxes'] === [7.5, 0.0]
                && $totals['lines'] === [105.06, 38.0]);
    }

    public function test_purchase_numeric_inputs_wait_before_recalculating_totals(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/compras.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('wire:model.live.debounce.1500ms="details.{{ $index }}.cantidad"', $view);
        $this->assertStringContainsString('wire:model.live.debounce.1500ms="details.{{ $index }}.costo"', $view);
        $this->assertStringContainsString('wire:model.live.debounce.1500ms="details.{{ $index }}.descuento"', $view);
        $this->assertStringContainsString('wire:model.live="details.{{ $index }}.isv"', $view);
    }

    public function test_purchase_detail_uses_a_compact_fixed_column_layout(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/compras.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('class="w-420 min-w-full table-fixed text-xs"', $view);
        $this->assertStringContainsString('<col class="w-96" /><col class="w-28" /><col class="w-24" />', $view);
        $this->assertStringContainsString('class="flex min-h-8 w-full items-center', $view);
        $this->assertGreaterThanOrEqual(6, substr_count($view, 'size="sm"'));
        $this->assertStringNotContainsString('min-w-[118rem]', $view);
    }

    public function test_purchase_list_keeps_money_on_one_line_and_places_creation_date_after_balance(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/compras.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('>Saldo</th><th class="px-4 py-3 whitespace-nowrap">Fecha de creación</th><th class="px-2 py-3 text-left">Acciones</th>', $view);
        $this->assertGreaterThanOrEqual(6, substr_count($view, 'whitespace-nowrap text-right'));
        $this->assertStringContainsString('class="flex min-w-56 flex-wrap items-center justify-start gap-0"', $view);
        $this->assertStringNotContainsString('flex min-w-56 flex-wrap justify-end gap-1', $view);
    }

    public function test_purchase_status_column_keeps_the_due_date_on_one_line(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/compras.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('<th class="min-w-36 px-4 py-3">Estado</th>', $view);
        $this->assertStringContainsString('class="mt-1 block whitespace-nowrap text-xs text-slate-500">Vence', $view);
    }

    public function test_purchase_detail_accepts_a_product_from_the_global_search(): void
    {
        Livewire::test(Compras::class)
            ->set('details', [[
                'producto' => '',
                'cantidad' => 1,
                'lote' => '',
                'costo' => '0.00',
                'isv' => false,
            ]])
            ->call('selectProductFromSearch', 'purchase-detail-0', [
                'id' => 27,
                'descripcion' => 'Amoxicilina 500 mg',
            ])
            ->assertSet('details.0.producto', '27');
    }

    public function test_purchase_detail_shows_name_code_presentation_and_purchase_unit(): void
    {
        Livewire::test(Compras::class)
            ->call('openCreate')
            ->call('selectProductFromSearch', 'purchase-detail-0', [
                'id' => 27, 'descripcion' => 'Amoxicilina 500 mg', 'codigo' => 'P-27',
                'laboratorio' => 'Laboratorio prueba', 'presentacion' => 'Tableta', 'unidad_compra' => '1',
            ])
            ->assertViewHas('detailProducts', fn (array $products): bool => $products[0] === [
                'name' => 'Amoxicilina 500 mg',
                'code' => 'P-27',
                'laboratory' => 'Laboratorio prueba',
                'presentation' => 'Tableta',
                'purchaseUnit' => '1',
            ])
            ->assertSee('Amoxicilina 500 mg')
            ->assertSee('Laboratorio prueba')
            ->assertSee('Tableta')
            ->assertSee('1')
            ->assertSee('Código: P-27');
    }

    public function test_last_cost_is_prefilled_and_differences_do_not_fetch_again(): void
    {
        $this->lastCostOverrides = [27 => '50.03'];

        $component = Livewire::test(Compras::class)->call('openCreate')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27, 'descripcion' => 'Producto'])
            ->assertSet('details.0.costo', '50.03')
            ->assertDontSee('purchase-cost-difference')
            ->set('details.0.costo', '55.03')->assertSee('Subió L 5.00')->assertSee('Anterior: L 50.03')
            ->set('details.0.costo', '49.99')->assertSee('Bajó L 0.04')
            ->set('details.0.costo', '50.03')->assertDontSee('purchase-cost-difference')
            ->set('details.0.costo', '')->assertDontSee('purchase-cost-difference');
        $component->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27]);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/ultimos-costos')));
    }

    public function test_changing_product_resets_cost_and_no_history_is_cached(): void
    {
        $this->lastCostOverrides = [27 => '15.00'];
        Livewire::test(Compras::class)->call('openCreate')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27])->assertSet('details.0.costo', '15.00')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 28])->assertSet('details.0.costo', '0.00')
            ->assertSee('Sin compras anteriores')->set('details.0.costo', '10.00')->assertDontSee('purchase-cost-difference')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 28]);
        $this->assertCount(2, Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/ultimos-costos')));
    }

    public function test_open_edit_batches_costs_excludes_current_purchase_and_preserves_saved_cost(): void
    {
        $purchase = $this->draftPurchase(true);
        $purchase['detalles'][0]['costo'] = 60;
        $purchase['detalles'][] = [...$purchase['detalles'][0], 'id' => 92, 'producto_id' => 28, 'costo' => 30];
        Http::fake([
            'api.example.test/api/farma/compras/buscar/10' => Http::response(['message' => 'ok', 'code' => 200, 'data' => $purchase]),
        ]);
        $this->lastCostOverrides = [27 => '50.00', 28 => '40.00'];
        Livewire::test(Compras::class)->call('openEdit', 10)
            ->assertSet('details.0.costo', '60.00')->assertSet('details.1.costo', '30.00')
            ->assertSee('Subió L 10.00')->assertSee('Bajó L 10.00')
            ->call('removeDetail', 0)->assertSee('Bajó L 10.00')->assertDontSee('Subió L 10.00');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/ultimos-costos')
            && $request->data()['productos'] === [27, 28] && $request->data()['excluir_compra'] === 10);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/ultimos-costos')));
    }

    public function test_last_cost_lookup_failure_allows_manual_cost_and_can_retry(): void
    {
        $this->costLookupFails = true;
        $component = Livewire::test(Compras::class)->call('openCreate')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27])
            ->assertSee('No se pudo consultar el último costo.')->set('details.0.costo', '60.00')
            ->assertSet('details.0.costo', '60.00')->assertDontSee('purchase-cost-difference');
        $this->costLookupFails = false;
        $this->lastCostOverrides = [27 => '50.00'];
        $component->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27])
            ->assertSet('details.0.costo', '50.00')->assertDontSee('No se pudo consultar');
    }

    public function test_last_cost_cache_is_cleared_when_reopening_form(): void
    {
        Livewire::test(Compras::class)->call('openCreate')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27])
            ->call('closeForm')->assertSet('latestProductCosts', [])->assertSet('productCostErrors', [])
            ->call('openCreate')->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27]);
        $this->assertCount(2, Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/ultimos-costos')));
    }

    public function test_purchase_writes_only_when_finalized(): void
    {
        Livewire::test(Compras::class)
            ->call('openCreate')
            ->set('tipo', '2')
            ->call('openProviderPicker')
            ->call('selectProvider', 1)
            ->set('nro', 'F-010')
            ->set('plazo', '30')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27, 'descripcion' => 'Amoxicilina 500 mg'])
            ->set('details.0.lote', 'L-001')
            ->set('details.0.fecha_elaboracion', '2026-01-15')
            ->set('details.0.fecha_expiracion', '2028-01-15')
            ->set('details.0.costo', '50.03')
            ->set('details.0.cantidad', 2)
            ->assertSet('editingId', null);

        Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true));

        Livewire::test(Compras::class)
            ->call('openCreate')
            ->set('tipo', '2')
            ->call('openProviderPicker')
            ->call('selectProvider', 1)
            ->set('nro', 'F-010')
            ->set('plazo', '30')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27, 'descripcion' => 'Amoxicilina 500 mg'])
            ->set('details.0.lote', 'L-001')
            ->set('details.0.fecha_elaboracion', '2026-01-15')
            ->set('details.0.fecha_expiracion', '2028-01-15')
            ->set('details.0.costo', '50.03')
            ->set('details.0.cantidad', 2)
            ->set('details.0.es_bonificacion', true)
            ->call('save')
            ->assertSet('showFormModal', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/farma/compras/crear')
            && ! array_key_exists('en_escala', $request->data())
            && $request->data()['detalles'][0]['es_bonificacion'] === true
            && $request->data()['detalles'][0]['producto'] === 27
            && $request->data()['detalles'][0]['cantidad'] === 2);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/borrador') || str_contains($request->url(), '/finalizar/'));
    }

    public function test_normal_and_bonus_lines_of_the_same_product_are_saved_separately(): void
    {
        $line = ['producto' => 27, 'cantidad' => 10, 'lote' => 'L-MIXTO', 'fecha_elaboracion' => '2026-01-15',
            'fecha_expiracion' => '2028-01-15', 'costo' => 50, 'isv' => false, 'descuento' => 0, 'es_bonificacion' => false];
        Livewire::test(Compras::class)->call('openCreate')->set('tipo', '2')->set('proveedor', '1')->set('nro', 'BONUS-TEST')
            ->set('details', [$line, [...$line, 'cantidad' => 2, 'costo' => 0, 'es_bonificacion' => true]])
            ->call('openProductSearch', 1)->assertDispatched('advanced-product-search:open', fn (string $event, array $data): bool => $data['excludedIds'] === [])
            ->call('save')->assertHasNoErrors()->assertSet('showFormModal', false);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/farma/compras/crear')
            && $request->data()['detalles'][0]['es_bonificacion'] === false && $request->data()['detalles'][1]['es_bonificacion'] === true
            && $request->data()['detalles'][0]['producto'] === 27 && $request->data()['detalles'][1]['producto'] === 27
            && (float) $request->data()['detalles'][1]['costo'] === 0.0);
    }

    public function test_selected_provider_image_and_purchase_detail_dates_are_rendered(): void
    {
        Livewire::test(Compras::class)
            ->call('openCreate')
            ->call('openProviderPicker')
            ->call('selectProvider', 1)
            ->assertSet('providerImageSrc', fn (?string $src): bool => is_string($src) && str_starts_with($src, 'data:image/png;base64,'))
            ->assertSee('Imagen del proveedor seleccionado')
            ->assertSee('Fecha de elaboración')
            ->assertSee('Fecha de expiración');
    }

    public function test_provider_picker_searches_and_paginates_without_loading_all_providers(): void
    {
        Livewire::test(Compras::class)
            ->call('openCreate')
            ->call('openProviderPicker')
            ->assertSee('Proveedor')
            ->call('nextProviderPage')
            ->assertSet('providerPage', 2)
            ->assertSee('Proveedor 2')
            ->set('providerSearch', 'Otro')
            ->assertSet('providerPage', 1)
            ->call('selectProvider', 2)
            ->assertSet('proveedor', '2')
            ->assertSet('providerName', 'Otro proveedor')
            ->assertSet('showProviderPicker', false);

        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/compras/proveedores')
            && ($request->data()['search'] ?? null) === 'Otro'
            && ($request->data()['page'] ?? null) === 1);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/compras/opciones'));
    }

    public function test_invoice_image_is_prepared_when_selected(): void
    {
        Livewire::test(Compras::class)
            ->call('openCreate')
            ->set('documentoArchivo', UploadedFile::fake()->image('factura.png', 400, 400))
            ->assertSet('documentoBase64', fn (?string $image): bool => is_string($image) && $image !== '')
            ->assertSee('Vista previa de factura');
    }

    public function test_editing_existing_purchase_writes_all_changes_once_on_finalize(): void
    {
        $purchase = $this->draftPurchase(true);
        $purchase['detalles'][0] = [
            'id' => 91, 'producto_id' => 27, 'cantidad' => 1, 'lote' => 'L-001',
            'fecha_elaboracion' => '2026-01-15', 'fecha_expiracion' => '2028-01-15',
            'costo' => 50.03, 'isv' => false, 'descuento' => 0,
            'producto' => ['id' => 27, 'descripcion' => 'Amoxicilina 500 mg', 'laboratorio' => 'Laboratorio prueba', 'presentacion' => 'Tableta', 'unidad_compra' => 1],
        ];
        Http::fake(['api.example.test/api/farma/compras/buscar/10' => Http::response(['message' => 'ok', 'code' => 200, 'data' => $purchase])]);

        $component = Livewire::test(Compras::class)
            ->call('openEdit', 10)
            ->assertSee('Amoxicilina 500 mg')
            ->set('details.0.cantidad', 3)
            ->set('nota', 'Cambio local');

        Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true));

        $component->call('save')->assertSet('showFormModal', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/farma/compras/actualizar')
            && $request->data()['id'] === 10
            && $request->data()['detalles'][0]['cantidad'] === 3
            && $request->data()['nota'] === 'Cambio local');
    }

    public function test_editing_purchase_preserves_and_updates_bonus_per_product_without_scale_confirmation(): void
    {
        $purchase = $this->draftPurchase(true);
        $purchase['en_escala'] = null;
        $purchase['detalles'][0] = [
            'id' => 91, 'producto_id' => 27, 'cantidad' => 1, 'lote' => 'L-001',
            'fecha_elaboracion' => '2026-01-15', 'fecha_expiracion' => '2028-01-15',
            'costo' => 50.03, 'isv' => false, 'descuento' => 0,
            'es_bonificacion' => true,
        ];
        Http::fake(['api.example.test/api/farma/compras/buscar/10' => Http::response(['code' => 200, 'data' => $purchase])]);
        Livewire::test(Compras::class)->call('openEdit', 10)->assertSet('details.0.es_bonificacion', true)
            ->assertDontSee('Compra en escala')->set('details.0.es_bonificacion', false)
            ->call('save')->assertHasNoErrors()->assertSet('showFormModal', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request->data()['detalles'][0]['es_bonificacion'] === false
            && ! array_key_exists('en_escala', $request->data()));
    }

    public function test_removing_saved_detail_stays_local_until_finalize(): void
    {
        $purchase = $this->draftPurchase(true);
        Http::fake(['api.example.test/api/farma/compras/buscar/10' => Http::response(['message' => 'ok', 'code' => 200, 'data' => $purchase])]);

        Livewire::test(Compras::class)
            ->call('openEdit', 10)
            ->call('removeDetail', 0)
            ->assertSet('details.0.producto', '');

        Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true));
    }

    public function test_purchase_rejects_expiration_before_manufacturing(): void
    {
        Livewire::test(Compras::class)
            ->call('openCreate')
            ->set('tipo', '2')
            ->set('proveedor', '1')
            ->set('nro', 'F-010')
            ->call('selectProductFromSearch', 'purchase-detail-0', ['id' => 27, 'descripcion' => 'Amoxicilina 500 mg'])
            ->set('details.0.lote', 'L-001')
            ->set('details.0.costo', '50.03')
            ->set('details.0.fecha_elaboracion', '2028-01-15')
            ->set('details.0.fecha_expiracion', '2026-01-15')
            ->call('save')
            ->assertHasErrors(['details.0.fecha_expiracion' => 'after_or_equal']);
    }

    public function test_purchase_list_opens_installment_payment_and_payment_history(): void
    {
        Livewire::test(Compras::class)
            ->call('openPayment', 1)
            ->assertSet('showPaymentModal', true)
            ->assertSet('paymentType', '2')
            ->assertSet('paymentBalance', 75.0)
            ->set('paymentValue', '75.00')
            ->call('closePayment')
            ->assertSet('showPaymentModal', false)
            ->call('viewPayments', 1)
            ->assertSet('showPaymentsModal', true)
            ->assertSet('paymentTransactions.0.nro', 'A-1')
            ->assertSet('paymentsTotal', 40.0);
    }

    public function test_purchase_report_shows_user_header_totals_and_products(): void
    {
        Livewire::test(Compras::class)
            ->call('openReport', 1)
            ->assertSet('showReportModal', true)
            ->assertSet('reportPurchase.usuario.nombre', 'María Operadora')
            ->assertSee('Reporte de compra')
            ->assertSee('María Operadora')
            ->assertSee('Amoxicilina 500 mg')
            ->assertSee('L-001')
            ->assertSee('15/01/2026')
            ->call('closeReport')
            ->assertSet('showReportModal', false)
            ->assertSet('reportPurchase', []);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with($request->url(), '/farma/compras/reporte/1')
            && $request->hasHeader('Action-Btn', 'cmpbtn08'));
    }

    public function test_purchase_report_modal_uses_the_full_purchase_view_width(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/compras.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString(
            'wire:model.self="showReportModal" class="purchases-content-modal purchases-form-modal',
            $view,
        );
    }

    public function test_purchase_report_product_table_is_compact_and_groups_product_metadata(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/compras.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString(
            'class="w-7xl table-fixed text-xs" data-test="purchase-report-products-table"',
            $view,
        );
        $this->assertStringContainsString("data_get(\$detail, 'producto.laboratorio')", $view);
        $this->assertStringContainsString("data_get(\$detail, 'producto.presentacion')", $view);
        $this->assertStringContainsString("data_get(\$detail, 'producto.unidad_compra')", $view);
        $this->assertStringNotContainsString('min-w-[112rem] text-sm', $view);
    }

    public function test_purchase_can_be_sent_to_kardex_only_from_the_confirmation_action(): void
    {
        Livewire::test(Compras::class)
            ->assertSee('data-test="send-purchase-kardex"', false)
            ->call('confirmKardex', 1)
            ->assertSet('showKardexModal', true)
            ->assertSet('kardexPurchaseName', 'F-001')
            ->assertSee('Esta acción solo se puede realizar una vez')
            ->call('sendToKardex')
            ->assertSet('showKardexModal', false)
            ->assertSet('purchases.0.kardex_enviado', true)
            ->assertDontSee('data-test="send-purchase-kardex"', false)
            ->assertSee('Enviada a Kardex');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/farma/compras/kardex/1')
            && $request->hasHeader('Action-Btn', 'cmpbtn09'));
    }

    public function test_purchase_payment_cannot_exceed_the_available_balance(): void
    {
        Livewire::test(Compras::class)
            ->call('openPayment', 1)
            ->set('paymentValue', '75.01')
            ->call('savePayment')
            ->assertHasErrors(['paymentValue' => 'max'])
            ->assertSet('showPaymentModal', true);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/farma/compras/transacciones/crear'));
    }

    public function test_installments_and_credit_notes_require_a_document_but_cash_does_not(): void
    {
        Livewire::test(Compras::class)
            ->set('purchases.0.total_aplicado', 0)
            ->set('purchases.0.saldo', 115)
            ->call('openPayment', 1, true)
            ->assertViewHas('paymentDocumentRequired', false)
            ->call('rememberSelectorChoice', 'payment-types', '2')
            ->set('paymentType', '2')
            ->assertViewHas('paymentDocumentRequired', true)
            ->set('paymentValue', '25.00')
            ->call('savePayment')
            ->assertHasErrors(['paymentDocumentFile' => 'required'])
            ->call('rememberSelectorChoice', 'payment-types', '3')
            ->set('paymentType', '3')
            ->assertViewHas('paymentDocumentRequired', true);

        Livewire::test(CompraTransacciones::class)
            ->call('openCreate')
            ->call('rememberSelectorChoice', 'payment-purchases', '2')
            ->set('compra', '2')
            ->call('rememberSelectorChoice', 'payment-types', '1')
            ->set('tipo', '1')
            ->assertViewHas('documentRequired', false)
            ->call('rememberSelectorChoice', 'payment-types', '2')
            ->set('tipo', '2')
            ->assertViewHas('documentRequired', true);
    }

    public function test_cash_payment_from_purchase_list_uses_only_the_exact_invoice_total(): void
    {
        Livewire::test(Compras::class)
            ->set('purchases.0.total_aplicado', 0)
            ->set('purchases.0.saldo', 115)
            ->call('openPayment', 1, true)
            ->assertSet('paymentType', '1')
            ->assertSet('paymentTotal', 115.0)
            ->assertSet('paymentValue', '115.00')
            ->assertViewHas('paymentCash', true)
            ->set('paymentValue', '114.99')
            ->call('savePayment')
            ->assertHasErrors(['paymentValue'])
            ->set('paymentValue', '115.00')
            ->call('savePayment')
            ->assertSet('showPaymentModal', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/farma/compras/transacciones/crear')
            && ($request->data()['tipo'] ?? null) === 1
            && (float) ($request->data()['valor'] ?? 0) === 115.0);
    }

    public function test_cash_payment_is_unavailable_after_an_installment_was_applied(): void
    {
        Livewire::test(Compras::class)
            ->call('openPayment', 1)
            ->call('rememberSelectorChoice', 'payment-types', '1')
            ->set('paymentType', '1')
            ->assertSet('paymentValue', '115.00')
            ->assertHasErrors(['paymentValue'])
            ->call('savePayment')
            ->assertHasErrors(['paymentValue'])
            ->assertSet('showPaymentModal', true);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/farma/compras/transacciones/crear'));
    }

    public function test_cash_payment_from_transactions_uses_the_purchase_total(): void
    {
        Livewire::test(CompraTransacciones::class)
            ->call('openCreate')
            ->call('rememberSelectorChoice', 'payment-purchases', '2')
            ->set('compra', '2')
            ->call('rememberSelectorChoice', 'payment-types', '1')
            ->set('tipo', '1')
            ->assertSet('valor', '200.00')
            ->assertViewHas('cashPayment', true)
            ->assertViewHas('selectedPurchaseTotal', 200.0)
            ->set('valor', '200.01')
            ->call('save')
            ->assertHasErrors(['valor'])
            ->set('valor', '200.00')
            ->call('save')
            ->assertSet('showFormModal', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/farma/compras/transacciones/crear')
            && ($request->data()['compra'] ?? null) === 2
            && ($request->data()['tipo'] ?? null) === 1
            && (float) ($request->data()['valor'] ?? 0) === 200.0);
    }

    /** @return array<string, mixed> */
    private function draftPurchase(bool $withDetail = false): array
    {
        return [
            'id' => 10, 'tipo_id' => 2, 'proveedor_id' => 1, 'nro' => 'F-010', 'plazo' => 0,
            'items' => $withDetail ? 1 : 0, 'subtotal' => $withDetail ? 50.03 : 0,
            'isv' => 0, 'descuento' => 0, 'total' => $withDetail ? 50.03 : 0,
            'total_aplicado' => 0, 'saldo' => $withDetail ? 50.03 : 0,
            'estado' => 'borrador', 'estado_label' => 'Borrador', 'tiene_documento' => false,
            'proveedor' => ['nombre' => 'Proveedor'], 'tipo' => ['descripcion' => 'Crédito'],
            'detalles' => $withDetail ? [[
                'id' => 91, 'producto_id' => 27, 'cantidad' => 1, 'lote' => '',
                'fecha_elaboracion' => null, 'fecha_expiracion' => null, 'costo' => 0,
                'isv' => false, 'descuento' => 0, 'total' => 0,
            ]] : [],
        ];
    }

    private function permissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => collect([
                'cmp' => ['cmpbtn01', 'cmpbtn02', 'cmpbtn03', 'cmpbtn04', 'cmpbtn05', 'cmpbtn06', 'cmpbtn07', 'cmpbtn08', 'cmpbtn09'],
                'ctp' => ['ctpbtn01', 'ctpbtn02', 'ctpbtn03', 'ctpbtn04', 'ctpbtn05'],
                'ctr' => ['ctrbtn01', 'ctrbtn02', 'ctrbtn03', 'ctrbtn04', 'ctrbtn05', 'ctrbtn06', 'ctrbtn07'],
                'ttp' => ['ttpbtn01', 'ttpbtn02', 'ttpbtn03', 'ttpbtn04', 'ttpbtn05'],
            ])->map(fn (array $actions, string $view): array => [
                'vistai' => ['codigo' => $view],
                'accionesi' => collect($actions)->map(fn (string $code): array => ['codigo' => $code])->all(),
            ])->values()->all(),
        ]];
    }

    private function pngImage(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return base64_encode($png);
    }

    /** @return array<string, mixed> */
    private function purchaseReport(): array
    {
        return [
            'id' => 1,
            'nro' => 'F-001',
            'plazo' => 30,
            'subtotal' => 100,
            'isv' => 15,
            'descuento' => 5,
            'total' => 110,
            'saldo' => 70,
            'estado' => 'pendiente',
            'estado_label' => 'Pendiente',
            'created_at' => '2026-08-03T14:35:00.000000Z',
            'proveedor' => ['nombre' => 'Proveedor'],
            'tipo' => ['descripcion' => 'Crédito'],
            'usuario' => ['id' => 9, 'nombre' => 'María Operadora'],
            'detalles' => [[
                'id' => 91,
                'cantidad' => 2,
                'lote' => 'L-001',
                'fecha_elaboracion' => '2026-01-15',
                'fecha_expiracion' => '2028-01-15',
                'costo' => 50,
                'subtotal' => 100,
                'isv_valor' => 15,
                'descuento_valor' => 5,
                'total' => 110,
                'producto' => [
                    'descripcion' => 'Amoxicilina 500 mg',
                    'laboratorio' => 'Laboratorio prueba',
                    'presentacion' => 'Tableta',
                    'unidad_compra' => 1,
                ],
            ]],
        ];
    }
}
