<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\Stocks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.stock_path' => '/config/stock',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->allStockPermissions());
        Http::preventStrayRequests();
    }

    public function test_component_lists_stocks_without_loading_states(): void
    {
        $this->fakeStocks();

        Livewire::test(Stocks::class)
            ->assertSee('Bodega central')
            ->assertSee('2233-4455')
            ->assertSee('Tegucigalpa')
            ->assertSee('INACTIVO')
            ->assertSee('bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300', false)
            ->assertSee('Página 1 de 2')
            ->assertSet('total', 35)
            ->assertSet('states', []);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/stock/estados');
    }

    public function test_component_loads_states_only_when_opening_the_form(): void
    {
        $this->fakeStocks();

        Livewire::test(Stocks::class)
            ->call('openCreate')
            ->assertSet('states', [])
            ->assertSet('showFormModal', true);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/stock/estados');
    }

    public function test_component_creates_and_updates_a_stock_with_the_api_contract(): void
    {
        $this->fakeStocks();

        Http::fake([
            'api.example.test/api/config/stock/crear' => Http::response([
                'message' => 'Stock creado correctamente',
                'code' => 201,
                'data' => true,
            ]),
            'api.example.test/api/config/stock/buscar/5' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    'id' => 5,
                    'descripcion' => 'Bodega central',
                    'telefono' => '2233-4455',
                    'ubicacion' => 'Tegucigalpa',
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
            'api.example.test/api/config/stock/actualizar/5' => Http::response([
                'message' => 'Stock actualizado correctamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Stocks::class)
            ->call('openCreate')
            ->set('descripcion', 'Sucursal norte')
            ->set('telefono', '9988-7766')
            ->set('ubicacion', 'San Pedro Sula')
            ->set('estado', '1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('sweetalert:toast')
            ->call('openEdit', 5)
            ->assertSet('descripcion', 'Bodega central')
            ->set('telefono', '2222-1111')
            ->set('estado', '2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/stock/crear'
            && $request->data()['estado'] === ['id' => 1]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/stock/actualizar/5'
            && $request->data()['id'] === 5
            && $request->data()['telefono'] === '2222-1111'
            && $request->data()['estado'] === ['id' => 2]);
    }

    public function test_component_deletes_and_navigates_stock_pages(): void
    {
        $this->fakeStocks();

        Http::fake([
            'api.example.test/api/config/stock/eliminar/5' => Http::response([
                'message' => 'Stock eliminado correctamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Stocks::class)
            ->call('confirmDelete', 5)
            ->assertSet('showDeleteModal', true)
            ->call('delete')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('sweetalert:toast')
            ->call('nextPage')
            ->assertSet('page', 2)
            ->assertSee('Sucursal norte');
    }

    public function test_route_and_menu_are_available_with_the_list_permission(): void
    {
        $this->fakeStocks();

        $this->get(route('config.stocks'))
            ->assertOk()
            ->assertSee('Stocks');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-cfg-stk"', false)
            ->assertSee('data:image/png;base64,', false);
    }

    private function fakeStocks(): void
    {
        Http::fake([
            'api.example.test/api/config/stock/listar*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'ok',
                    'code' => 200,
                    'data' => $page === 2
                        ? [[
                            'id' => 6,
                            'descripcion' => 'Sucursal norte',
                            'telefono' => '9988-7766',
                            'ubicacion' => 'San Pedro Sula',
                            'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                        ]]
                        : [[
                            'id' => 5,
                            'descripcion' => 'Bodega central',
                            'telefono' => '2233-4455',
                            'ubicacion' => 'Tegucigalpa',
                            'estado' => ['id' => 2, 'descripcion' => 'INACTIVO'],
                        ]],
                    'meta' => [
                        'total' => 35,
                        'current_page' => $page,
                        'last_page' => 2,
                    ],
                ]);
            },
            'api.example.test/api/config/stock/estados' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ACTIVO'],
                    ['id' => 2, 'descripcion' => 'INACTIVO'],
                ],
            ]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allStockPermissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'cfg'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'stk'],
                'accionesi' => collect([
                    'stkbnt01',
                    'stkbnt02',
                    'stkbnt03',
                    'stkbnt04',
                    'stkbtn05',
                    'stkbtn06',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
