<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\Lotes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LotManagementTest extends TestCase
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
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [
                [
                    'vistai' => ['codigo' => 'lts'],
                    'accionesi' => [['codigo' => 'ltsbtn01'], ['codigo' => 'ltsbtn02']],
                ],
                [
                    'vistai' => ['codigo' => 'prd'],
                    'accionesi' => [['codigo' => 'prdbtn06']],
                ],
            ],
        ]]);
        Http::preventStrayRequests();
        Http::fake([
            'api.example.test/api/farma/lotes/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [[
                    'producto_id' => 4,
                    'codigo' => 'P-004',
                    'descripcion' => 'Amoxicilina 500 mg',
                    'laboratorio' => 'Laboratorio prueba',
                    'presentacion' => 'Tableta',
                    'administracion' => 'Oral',
                    'tiene_imagen' => true,
                    'lotes_count' => 2,
                    'cantidad_total' => 15,
                    'proxima_expiracion' => '2028-01-15',
                ]],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/lotes/producto/4' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [[
                    'id' => 8,
                    'compra_id' => 10,
                    'producto_id' => 4,
                    'lote' => 'LOTE-001',
                    'fecha_elaboracion' => '2026-01-15',
                    'fecha_expiracion' => '2028-01-15',
                    'cantidad' => 15,
                    'costo' => 50.25,
                    'isv' => true,
                    'factura' => ['id' => 10, 'nro' => 'FAC-001'],
                ]],
            ]),
        ]);
    }

    public function test_lots_view_lists_products_and_opens_read_only_lot_detail(): void
    {
        $this->get(route('farma.lotes'))
            ->assertOk()
            ->assertSee('Amoxicilina 500 mg')
            ->assertSee('Tableta')
            ->assertSee('Oral')
            ->assertSee('15/01/2028')
            ->assertSee(route('farma.products.preview', ['product' => 4]), false)
            ->assertSee('data-test="view-product-lots"', false);

        Livewire::test(Lotes::class)
            ->assertSet('products.0.producto_id', 4)
            ->call('openLots', 4)
            ->assertSet('showLotsModal', true)
            ->assertSet('selectedProduct.descripcion', 'Amoxicilina 500 mg')
            ->assertSet('lots.0.factura.nro', 'FAC-001')
            ->assertSee('LOTE-001')
            ->assertSee('FAC-001')
            ->assertSee('L 50.25')
            ->assertSee('ISV')
            ->assertSee('Fecha de elaboración')
            ->assertSee('Fecha de expiración')
            ->call('closeLots')
            ->assertSet('showLotsModal', false)
            ->assertSet('lots', []);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/farma/lotes/producto/4')
            && $request->hasHeader('Action-Btn', 'ltsbtn02'));
    }

    public function test_lots_search_queries_the_api_before_pagination(): void
    {
        Livewire::test(Lotes::class)
            ->set('page', 2)
            ->set('search', 'Amoxicilina')
            ->assertSet('page', 1);

        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/farma/lotes/listar')
            && ($request->data()['search'] ?? null) === 'Amoxicilina');
    }

    public function test_lot_detail_modal_uses_the_full_available_content_width(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/lotes.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('wire:model.self="showLotsModal" class="lots-content-modal lots-detail-modal', $view);
        $this->assertStringContainsString('width: calc(100vw - 19rem) !important;', $view);
        $this->assertStringContainsString('class="w-full min-w-6xl table-fixed text-sm"', $view);
    }

    public function test_lot_product_images_use_the_shared_storage_session_cache(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/lotes.blade.php'));
        $javascript = file_get_contents(resource_path('js/session-image-cache.js'));
        $entrypoint = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($view);
        $this->assertIsString($javascript);
        $this->assertIsString($entrypoint);
        $this->assertStringContainsString("\$product['imagen_url'] ?? route('farma.products.preview'", $view);
        $this->assertStringContainsString('data-session-image-src=', $view);
        $this->assertStringContainsString('class="h-16 w-full object-contain p-1"', $view);
        $this->assertStringContainsString('data-session-image-placeholder class="hidden', $view);
        $this->assertStringContainsString('shadmin-image-session', $javascript);
        $this->assertStringContainsString('pending.has(key)', $javascript);
        $this->assertStringContainsString('caches.open(namespace)', $javascript);
        $this->assertStringNotContainsString('localStorage', $javascript);
        $this->assertStringNotContainsString('sessionStorage', $javascript);
        $this->assertStringContainsString("import './storage-image-loader';", $entrypoint);
    }
}
