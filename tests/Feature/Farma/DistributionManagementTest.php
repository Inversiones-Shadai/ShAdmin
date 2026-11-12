<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\Distribucion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DistributionManagementTest extends TestCase
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
            'farma.purchase_isv_rate' => 0.15,
        ]);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [
                ['vistai' => ['codigo' => 'dst'], 'accionesi' => [['codigo' => 'dstbtn01'], ['codigo' => 'dstbtn02'], ['codigo' => 'dstbtn03'], ['codigo' => 'dstbtn04']]],
                ['vistai' => ['codigo' => 'prd'], 'accionesi' => [['codigo' => 'prdbtn06']]],
            ],
        ]]);
        $this->fakeSelectorsFromLegacy(['distribution-lots' => 'farma/distribucion/producto/{product}']);
        Http::preventStrayRequests();
        Http::fake([
            'api.example.test/api/farma/distribucion/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [[
                    'producto_id' => 4, 'codigo' => 'P-004', 'descripcion' => 'Amoxicilina 500 mg',
                    'laboratorio' => 'Laboratorio prueba', 'presentacion' => 'Tableta', 'administracion' => 'Oral',
                    'tiene_imagen' => true, 'lotes_count' => 2, 'lotes_configurados' => 1,
                    'cantidad_total' => 35, 'proxima_expiracion' => '2028-01-15',
                ]],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/distribucion/producto/4' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [[
                    'id' => 8, 'lote' => 'LOTE-001', 'cantidad' => 15, 'costo' => 50, 'isv' => true,
                    'fecha_elaboracion' => '2026-01-15', 'fecha_expiracion' => '2028-01-15',
                    'factura' => ['id' => 10, 'nro' => 'FAC-001'], 'configurado' => true,
                    'distribucion' => ['id' => 2, 'precio' => 100, 'isv' => true, 'dto1' => 5, 'dto2' => 0, 'dto3' => 0, 'dto4' => 0],
                ], [
                    'id' => 9, 'lote' => 'LOTE-002', 'cantidad' => 20, 'costo' => 80, 'isv' => false,
                    'fecha_elaboracion' => '2026-02-15', 'fecha_expiracion' => '2029-02-15',
                    'factura' => ['id' => 11, 'nro' => 'FAC-002'], 'configurado' => false, 'distribucion' => null,
                ]],
            ]),
            'api.example.test/api/farma/distribucion/lote/9' => Http::response([
                'message' => 'guardado', 'code' => 200,
                'data' => ['id' => 3, 'lote_id' => 9, 'precio' => 125.50, 'isv' => true, 'dto1' => 10, 'dto2' => 5, 'dto3' => 0, 'dto4' => 0],
            ]),
        ]);
    }

    public function test_distribution_lists_products_and_saves_pricing_for_selected_lot(): void
    {
        $this->get(route('farma.distribucion'))
            ->assertOk()
            ->assertSee('Amoxicilina 500 mg')
            ->assertSee('Tableta')
            ->assertSee('Oral')
            ->assertSee('1/2 lotes')
            ->assertSee(route('farma.products.preview', ['product' => 4]), false)
            ->assertSee('data-test="set-product-pricing"', false);

        Livewire::test(Distribucion::class)
            ->call('openPricing', 4)->call('rememberSelectorChoice', 'distribution-lots', '8')->set('selectedLot', '8')
            ->assertSet('showPricingModal', true)
            ->assertSet('selectedLot', '8')
            ->assertSet('precio', '100.00')
            ->assertSee('LOTE-001')
            ->set('dto1', '10')
            ->assertSee('L 103.50')
            ->assertSee('+80.00%')
            ->call('rememberSelectorChoice', 'distribution-lots', '9')->set('selectedLot', '9')
            ->assertSet('precio', '')
            ->set('precio', '125.50')
            ->set('dto1', '10')
            ->set('dto2', '5')
            ->call('savePricing')
            ->assertHasNoErrors()
            ->assertSet('lots.0.configurado', true)
            ->assertSet('products.0.lotes_configurados', 2)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/farma/distribucion/lote/9')
            && ($request->data()['precio'] ?? null) === 125.5
            && ! array_key_exists('isv', $request->data())
            && $request->hasHeader('Action-Btn', 'dstbtn03'));
    }

    public function test_promotion_and_single_header_are_saved_for_the_selected_lot(): void
    {
        Livewire::test(Distribucion::class)
            ->call('openPricing', 4)->call('rememberSelectorChoice', 'distribution-lots', '9')->set('selectedLot', '9')
            ->set('precio', '100')->set('promocion_activa', true)
            ->call('savePricing')->assertHasErrors(['promocion_porcentaje', 'promocion_hasta'])
            ->set('promocion_porcentaje', '15')->set('promocion_hasta', '2026-12-31')
            ->set('mostrar_encabezado', true)->set('encabezado_texto', 'Oferta del lote')
            ->call('savePricing')->assertHasNoErrors()
            ->call('rememberSelectorChoice', 'distribution-lots', '8')->set('selectedLot', '8')
            ->assertSet('promocion_activa', false)->assertSet('encabezado_texto', '');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->data()['promocion_activa'] === true
            && (float) $request->data()['promocion_porcentaje'] === 15.0
            && $request->data()['promocion_hasta'] === '2026-12-31'
            && $request->data()['mostrar_encabezado'] === true
            && $request->data()['encabezado_texto'] === 'Oferta del lote');
    }

    public function test_distribution_requires_a_positive_price_and_valid_discounts(): void
    {
        Livewire::test(Distribucion::class)
            ->call('openPricing', 4)->call('rememberSelectorChoice', 'distribution-lots', '8')->set('selectedLot', '8')
            ->set('precio', '0')
            ->set('dto1', '100')
            ->call('savePricing')
            ->assertHasErrors(['precio', 'dto1']);
    }

    public function test_promotion_can_be_limited_to_selected_stocks_without_repeating_the_header(): void
    {
        $this->fakeSelectorChoice('distribution-stocks', ['id' => 7, 'descripcion' => 'Stock norte']);
        Livewire::test(Distribucion::class)
            ->call('openPricing', 4)->call('rememberSelectorChoice', 'distribution-lots', '9')->set('selectedLot', '9')
            ->set('precio', '100')->set('promocion_activa', true)
            ->set('promocion_porcentaje', '15')->set('promocion_hasta', '2026-12-31')
            ->set('promocion_todos_stocks', false)->call('savePricing')->assertHasErrors(['promocion_stock_ids'])
            ->call('rememberSelectorChoice', 'distribution-stocks', '7')->set('promotionStock', '7')->call('addPromotionStock')
            ->assertSet('promocion_stock_ids', [7])->assertSee('Stock norte')
            ->call('rememberSelectorChoice', 'distribution-stocks', '7')->set('promotionStock', '7')->call('addPromotionStock')
            ->assertSet('promocion_stock_ids', [7])
            ->set('encabezado_texto', 'Oferta norte')->call('savePricing')->assertHasNoErrors()
            ->call('removePromotionStock', 7)->assertSet('promocion_stock_ids', [])
            ->call('rememberSelectorChoice', 'distribution-lots', '8')->set('selectedLot', '8')
            ->assertSet('promocion_todos_stocks', true)->assertSet('promocion_stock_ids', []);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->data()['promocion_todos_stocks'] === false
            && $request->data()['promocion_stock_ids'] === [7]
            && $request->data()['encabezado_texto'] === 'Oferta norte');
    }

    public function test_taxable_lot_shows_cost_with_isv_and_each_discount_breakdown(): void
    {
        Livewire::test(Distribucion::class)
            ->call('openPricing', 4)->call('rememberSelectorChoice', 'distribution-lots', '8')->set('selectedLot', '8')
            ->set('dto1', '10')
            ->set('dto2', '15')
            ->set('dto3', '0')
            ->set('dto4', '60')
            ->assertViewHas('unitCostWithTax', 57.5)
            ->assertSee('L 57.50')
            ->assertSee('Subtotal')
            ->assertViewHas('discountReferences', [
                'dto1' => ['subtotal' => 100.0, 'descuento' => 10.0, 'isv' => 13.5, 'total' => 103.5, 'ganancia' => 80.0],
                'dto2' => ['subtotal' => 100.0, 'descuento' => 15.0, 'isv' => 12.75, 'total' => 97.75, 'ganancia' => 70.0],
                'dto3' => ['subtotal' => 100.0, 'descuento' => 0.0, 'isv' => 15.0, 'total' => 115.0, 'ganancia' => 100.0],
                'dto4' => ['subtotal' => 100.0, 'descuento' => 60.0, 'isv' => 6.0, 'total' => 46.0, 'ganancia' => -20.0],
            ])
            ->assertSeeHtmlInOrder(['Descuento 1', 'data-test="dto1-gain"', '+80.00%', 'data-test="dto1-reference"', 'Subtotal', 'Descuento', 'ISV', 'Total'])
            ->assertSeeHtmlInOrder(['Descuento 4', 'data-test="dto4-gain"', '-20.00%', 'data-test="dto4-reference"']);
    }

    public function test_switching_to_exempt_lot_hides_isv_and_uses_untaxed_cost(): void
    {
        Livewire::test(Distribucion::class)
            ->call('openPricing', 4)->call('rememberSelectorChoice', 'distribution-lots', '8')->set('selectedLot', '8')
            ->call('rememberSelectorChoice', 'distribution-lots', '9')->set('selectedLot', '9')
            ->set('precio', '100')
            ->set('dto1', '10')
            ->assertViewHas('unitCostWithTax', 80.0)
            ->assertViewHas('discountReferences', fn (array $references): bool => $references['dto1'] === [
                'subtotal' => 100.0, 'descuento' => 10.0, 'isv' => null, 'total' => 90.0, 'ganancia' => 12.5,
            ])
            ->assertSee('L 80.00')
            ->assertSee('+12.50%')
            ->assertDontSee('data-test="dto1-isv"', false)
            ->assertDontSee('data-test="dto2-isv"', false)
            ->assertDontSee('data-test="dto3-isv"', false)
            ->assertDontSee('data-test="dto4-isv"', false);
    }

    public function test_discount_amounts_reconcile_to_cents_with_configured_isv_rate(): void
    {
        config(['farma.purchase_isv_rate' => 0.18]);

        Livewire::test(Distribucion::class)
            ->call('openPricing', 4)->call('rememberSelectorChoice', 'distribution-lots', '8')->set('selectedLot', '8')
            ->set('precio', '391.30')
            ->set('dto1', '15')
            ->assertViewHas('unitCostWithTax', 59.0)
            ->assertViewHas('discountReferences', fn (array $references): bool => $references['dto1'] === [
                'subtotal' => 391.3, 'descuento' => 58.7, 'isv' => 59.87, 'total' => 392.47, 'ganancia' => 565.2,
            ]);
    }

    public function test_missing_or_zero_cost_does_not_show_a_profit_percentage(): void
    {
        Livewire::test(Distribucion::class)
            ->call('openPricing', 4)->call('rememberSelectorChoice', 'distribution-lots', '8')->set('selectedLot', '8')
            ->set('lots.0.costo', null)
            ->assertViewHas('unitCostWithTax', null)
            ->assertViewHas('discountReferences', fn (array $references): bool => $references['dto1']['ganancia'] === null)
            ->assertSee('No disponible')
            ->assertDontSee('+90.00%')
            ->set('lots.0.costo', 0)
            ->assertViewHas('unitCostWithTax', 0.0)
            ->assertViewHas('discountReferences', fn (array $references): bool => $references['dto1']['ganancia'] === null);
    }

    public function test_catalog_lot_can_be_registered_for_a_product_not_in_the_distribution_list(): void
    {
        $this->fakeSelectorChoice('distribution-products', ['id' => 88, 'codigo' => 'P088', 'descripcion' => 'Producto descentralizado']);
        Http::fake(['*/farma/distribucion/lote' => Http::response([
            'code' => 201, 'data' => ['id' => 18, 'lote' => 'LOCAL-88', 'cantidad' => 0, 'costo' => 10.125, 'isv' => true,
                'fecha_elaboracion' => '2026-01-01', 'fecha_expiracion' => '2028-01-01', 'factura' => null, 'configurado' => false, 'distribucion' => null],
        ], 201)]);

        Livewire::test(Distribucion::class)->assertSee('data-test="create-catalog-lot"', false)
            ->call('openCatalogLot')->assertSet('showCatalogLotModal', true)
            ->call('rememberSelectorChoice', 'distribution-products', '88')->set('catalogProduct', '88')
            ->set('catalogLot', ' LOCAL-88 ')->set('catalogManufactured', '2026-01-01')->set('catalogExpires', '2028-01-01')
            ->set('catalogCost', '10.1250')->set('catalogIsv', true)->call('saveCatalogLot')->assertHasNoErrors()
            ->assertSet('showCatalogLotModal', false)->assertSet('showPricingModal', true)->assertSet('selectedLot', '18')
            ->assertSet('selectedProduct.producto_id', 88)->assertSee('Producto descentralizado')
            ->assertSet('lots.0.cantidad', 0)->assertSet('isv', true)->assertSet('precio', '')->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/farma/distribucion/lote')
            && $request->hasHeader('Action-Btn', 'dstbtn04') && $request->hasHeader('Idempotency-Key')
            && $request['producto'] === 88 && $request['lote'] === 'LOCAL-88' && $request['costo'] === '10.1250'
            && ! array_key_exists('cantidad', $request->data()) && ! array_key_exists('compra', $request->data()));
    }

    public function test_registering_lots_requires_its_own_permission_on_every_action(): void
    {
        session()->forget('auth.api.abilities');
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'frm'], 'vistas_l' => [
            ['vistai' => ['codigo' => 'dst'], 'accionesi' => [['codigo' => 'dstbtn01'], ['codigo' => 'dstbtn02'], ['codigo' => 'dstbtn03']]],
        ]]]);
        Livewire::test(Distribucion::class)->assertDontSee('data-test="create-catalog-lot"', false)->call('openCatalogLot')->assertForbidden();
        Livewire::test(Distribucion::class)->call('saveCatalogLot')->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_catalog_lot_validation_blocks_blank_lots_reversed_dates_and_negative_cost(): void
    {
        Livewire::test(Distribucion::class)->call('openCatalogLot')->set('catalogProduct', '4')->set('catalogLot', ' ')
            ->set('catalogManufactured', '2029-01-01')->set('catalogExpires', '2028-01-01')->set('catalogCost', '-1')
            ->call('saveCatalogLot')->assertHasErrors(['catalogLot', 'catalogManufactured', 'catalogCost']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_duplicate_lot_error_stays_in_the_registration_form(): void
    {
        Http::fake(['*/farma/distribucion/lote' => Http::response([
            'code' => 422, 'message' => 'Revisa los datos del lote.', 'data' => ['errors' => ['lote' => ['Este lote ya está registrado.']]],
        ], 422)]);
        Livewire::test(Distribucion::class)->call('openCatalogLot')->set('catalogProduct', '4')->set('catalogLot', 'LOCAL')
            ->call('saveCatalogLot')->assertHasErrors(['catalogLot'])->assertSet('showCatalogLotModal', true)
            ->assertSee('Este lote ya está registrado.');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['costo'] === null && $request['fecha_elab'] === null && $request['fecha_exp'] === null);
    }

    public function test_expired_session_during_lot_registration_redirects_to_login(): void
    {
        Http::fake(['*/farma/distribucion/lote' => Http::response(['code' => 401, 'message' => 'Sesión expirada.'], 401)]);
        Livewire::test(Distribucion::class)->call('openCatalogLot')->set('catalogProduct', '4')->set('catalogLot', 'LOCAL')
            ->call('saveCatalogLot')->assertRedirect(route('login'));
        $this->assertNull(session('auth.api.token'));
    }
}
