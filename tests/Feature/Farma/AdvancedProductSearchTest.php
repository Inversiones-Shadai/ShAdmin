<?php

namespace Tests\Feature\Farma;

use App\Livewire\Shared\AdvancedProductSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AdvancedProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.product_path' => '/farma/productos/producto',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->permissions());
        Http::preventStrayRequests();
        Http::fake([
            ...$this->productOptionResponses([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    'laboratorios' => [['id' => 3, 'label' => 'Laboratorio Central']],
                ],
            ]),
            'api.example.test/api/farma/productos/producto/listar*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);
                $numbers = $page === 2 ? range(11, 12) : range(1, 10);

                return Http::response([
                    'message' => 'ok',
                    'code' => 200,
                    'data' => collect($numbers)->map(fn (int $number): array => [
                        'id' => $number,
                        'codigo' => sprintf('PRD-%03d', $number),
                        'codigobar' => sprintf('750000000%03d', $number),
                        'descripcion' => "Producto {$number}",
                        'laboratorio_id' => 3,
                        'laboratorio' => ['id' => 3, 'nombre' => 'Laboratorio Central'],
                        'tiene_imagen' => $number === 1,
                    ])->all(),
                    'meta' => ['total' => 12, 'current_page' => $page, 'last_page' => 2],
                ]);
            },
            'api.example.test/api/farma/productos/producto/imagen/1' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => ['image' => $this->pngBase64()],
            ]),
        ]);
    }

    public function test_it_searches_filters_paginates_and_selects_products(): void
    {
        Livewire::test(AdvancedProductSearch::class)
            ->call('open', 'purchase-detail-0', [2])
            ->assertSet('showModal', true)
            ->assertSet('total', 12)
            ->assertCount('products', 10)
            ->assertSee('Producto 1')
            ->assertSee('Laboratorio Central')
            ->assertSee('Ya agregado')
            ->assertSee('xl:grid-cols-5', false)
            ->call('nextPage')
            ->assertSet('page', 2)
            ->assertCount('products', 2)
            ->assertSee('Producto 12')
            ->call('clearFilters')
            ->set('searchTerm', '750000000001')
            ->set('laboratory', '3')
            ->call('selectProduct', 1)
            ->assertSet('showModal', false)
            ->assertDispatched('advanced-product-search:selected');

        Http::assertSent(fn (Request $request): bool => str_ends_with(
            (string) parse_url($request->url(), PHP_URL_PATH),
            '/listar',
        )
            && ($request->data()['search'] ?? null) === '750000000001'
            && ($request->data()['laboratorio'] ?? null) === 3
            && ! isset($request->data()['proveedor'])
            && $request->hasHeader('Action-Btn', 'prdbtn03'));

        Http::assertNotSent(fn (Request $request): bool => preg_match('/\/(familias|principios_activos)(\?|$)/', $request->url()) === 1);
    }

    public function test_preview_route_returns_a_protected_product_image(): void
    {
        $response = $this->get(route('farma.products.preview', ['product' => 1]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertSame(base64_decode($this->pngBase64(), true), $response->getContent());
    }

    public function test_transfer_search_uses_lot_products_without_general_catalog_permissions(): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'lts'],
                'accionesi' => [['codigo' => 'ltsbtn01']],
            ]],
        ]]);
        Http::fake([
            'api.example.test/api/farma/lotes/listar*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'ok', 'code' => 200,
                    'data' => [[
                        'producto_id' => $page === 1 ? 4 : 9,
                        'codigo' => 'P-004',
                        'descripcion' => $page === 1 ? 'Amoxicilina con lotes' : 'Producto de segunda página',
                        'laboratorio' => 'Laboratorio de lotes',
                        'presentacion' => 'Tableta',
                        'lotes_count' => 2,
                        'cantidad_total' => 15,
                    ]],
                    'meta' => ['total' => 2, 'current_page' => $page, 'last_page' => 2],
                ]);
            },
        ]);

        Livewire::test(AdvancedProductSearch::class)
            ->call('open', 'transfer-detail')
            ->assertSet('showModal', true)
            ->assertSet('products.0.id', 4)
            ->assertSet('products.0.presentacion', 'Tableta')
            ->assertSee('Productos con lotes')
            ->assertSee('Laboratorio de lotes')
            ->assertDontSee('Todos los laboratorios')
            ->call('nextPage')
            ->assertSet('page', 2)
            ->assertSet('products.0.id', 9)
            ->set('searchTerm', 'Amoxicilina')
            ->assertSet('page', 1)
            ->call('selectProduct', 4)
            ->assertSet('showModal', false)
            ->assertDispatched('advanced-product-search:selected', fn (string $name, array $params): bool => $params['context'] === 'transfer-detail' && $params['product']['id'] === 4)
            ->dispatch('inventory-lots-updated')
            ->assertSet('products', [])
            ->assertSet('total', 0);

        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/farma/lotes/listar')
            && ($request->data()['search'] ?? null) === 'Amoxicilina'
            && $request->hasHeader('Action-Btn', 'ltsbtn01'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/farma/productos/'));
    }

    public function test_transfer_search_requires_permission_to_list_lot_products(): void
    {
        Livewire::test(AdvancedProductSearch::class)
            ->call('open', 'transfer-detail')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    /** @return array<int, array<string, mixed>> */
    private function permissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'prd'],
                'accionesi' => collect(['prdbtn03', 'prdbtn06', 'prdbtn07'])
                    ->map(fn (string $code): array => ['codigo' => $code])
                    ->all(),
            ]],
        ]];
    }

    private function pngBase64(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image, null, 9, PNG_ALL_FILTERS);
        $png = ob_get_clean();
        imagedestroy($image);
        $this->assertIsString($png);

        return base64_encode($png);
    }
}
