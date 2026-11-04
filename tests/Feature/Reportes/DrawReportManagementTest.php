<?php

namespace Tests\Feature\Reportes;

use App\Livewire\Reportes\Sorteos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DrawReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.report_draw_path' => '/reportes/sorteos',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->reportPermissions());
        Http::preventStrayRequests();
    }

    public function test_route_menu_catalogs_and_filtered_results_are_available(): void
    {
        $this->fakeReportApi();

        $this->get(route('reportes.sorteos'))
            ->assertOk()
            ->assertSee('Reporte de sorteos');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-module-rpt"', false)
            ->assertSee('data-test="permission-view-rpt-rptstr"', false)
            ->assertSee('Reportes');

        Livewire::test(Sorteos::class)
            ->assertSet('draws.0.label', 'Sorteo de verano')
            ->assertSet('clients.0.label', '0801199012345 · Ana López')
            ->assertSee('data-test="open-draw-catalog"', false)
            ->assertSee('data-test="open-client-catalog"', false)
            ->assertSee('data-test="open-user-catalog"', false)
            ->assertSee('data-test="open-stock-catalog"', false)
            ->call('openCatalog', 'draws')
            ->assertSet('showCatalogModal', true)
            ->assertSee('data-test="report-catalog-modal"', false)
            ->call('selectCatalogItem', 8)
            ->assertSet('selectedDraw', '8')
            ->assertSet('showCatalogModal', false)
            ->call('applyFilters')
            ->assertHasNoErrors()
            ->assertSet('filterActive', true)
            ->assertSet('total', 1)
            ->assertSee('Sorteo de verano')
            ->assertSee('Ana López')
            ->assertSee('Sucursal Centro')
            ->assertSee('data-test="export-report-button"', false)
            ->assertSee('data-test="report-results-table"', false);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/filter?')
            && $request->data()['filterItems'][0]['key'] === 'promocion'
            && $request->data()['filterItems'][0]['value'] === '8');
    }

    public function test_it_exports_the_active_report_as_pdf_or_excel(): void
    {
        $this->fakeReportApi();

        Livewire::test(Sorteos::class)
            ->set('selectedDraw', '8')
            ->call('applyFilters')
            ->call('openExport')
            ->assertSet('showExportModal', true)
            ->set('exportFormat', 'pdf')
            ->call('exportReport')
            ->assertFileDownloaded(contentType: 'application/pdf');

        Livewire::test(Sorteos::class)
            ->set('selectedDraw', '8')
            ->call('applyFilters')
            ->call('openExport')
            ->set('exportFormat', 'xlsx')
            ->call('exportReport')
            ->assertFileDownloaded(
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
    }

    public function test_catalog_search_uses_its_specific_permission_and_endpoint(): void
    {
        $this->fakeReportApi();

        Livewire::test(Sorteos::class)
            ->call('openCatalog', 'clients')
            ->set('catalogSearch', 'Carlos')
            ->assertHasNoErrors()
            ->assertSet('clients.0.label', '0501198811111 · Carlos Pérez')
            ->call('selectCatalogItem', 22)
            ->assertSet('selectedClient', '22')
            ->assertSet('selectedLabels.clients', '0501198811111 · Carlos Pérez');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/filter_clientes?')
            && $request->data()['filterItems'][1]['value'] === 'Carlos');
    }

    public function test_each_catalog_can_be_opened_and_selected_from_the_table(): void
    {
        $this->fakeReportApi();

        $component = Livewire::test(Sorteos::class);

        foreach ([
            ['draws', 8, 'selectedDraw'],
            ['clients', 21, 'selectedClient'],
            ['users', 4, 'selectedUser'],
            ['stocks', 3, 'selectedStock'],
        ] as [$catalog, $id, $selected]) {
            $component
                ->call('openCatalog', $catalog)
                ->assertSet('activeCatalog', $catalog)
                ->assertSet('catalogTotal', 1)
                ->assertSee('data-test="select-report-catalog-item"', false)
                ->call('selectCatalogItem', $id)
                ->assertSet($selected, (string) $id)
                ->assertSet('showCatalogModal', false);
        }

        $component
            ->call('clearCatalogSelection', 'users')
            ->assertSet('selectedUser', '');
    }

    public function test_catalog_modal_navigates_api_pages(): void
    {
        Http::fake([
            'api.example.test/api/reportes/sorteos/list_sorteos*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'ok',
                    'code' => 200,
                    'data' => [['id' => $page, 'nombre' => "Sorteo {$page}"]],
                    'meta' => ['total' => 2, 'current_page' => $page, 'last_page' => 2],
                ]);
            },
        ]);
        $this->fakeReportApi();

        Livewire::test(Sorteos::class)
            ->call('openCatalog', 'draws')
            ->assertSet('catalogPage', 1)
            ->assertSet('catalogLastPage', 2)
            ->call('goToCatalogPage', 2)
            ->assertSet('catalogPage', 2)
            ->assertSet('draws.0.label', 'Sorteo 2')
            ->call('selectCatalogItem', 2)
            ->assertSet('selectedLabels.draws', 'Sorteo 2');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/reportes/sorteos/list_sorteos?page=2');
    }

    public function test_each_catalog_filters_rows_inside_its_modal(): void
    {
        $this->fakeReportApi();

        $component = Livewire::test(Sorteos::class);

        foreach ([
            ['draws', 'verano', 'Sorteo filtrado'],
            ['clients', 'Carlos', 'Carlos Pérez'],
            ['users', 'Operador', 'Operador filtrado'],
            ['stocks', 'Centro', 'Stock filtrado'],
        ] as [$catalog, $term, $expected]) {
            $component
                ->call('openCatalog', $catalog)
                ->set('catalogSearch', $term)
                ->assertHasNoErrors()
                ->assertSee($expected)
                ->assertSet('catalogPage', 1);
        }

        foreach (['filter_sorteos', 'filter_clientes', 'filter_usuarios', 'filter_stocks'] as $endpoint) {
            Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "/{$endpoint}?"));
        }
    }

    public function test_catalog_filter_is_hidden_and_forbidden_without_its_permission(): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'rpt'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'rptstr'],
                'accionesi' => [
                    ['codigo' => 'rptstrbtn01'],
                    ['codigo' => 'rptstrbnt07'],
                ],
            ]],
        ]]);
        $this->fakeReportApi();

        Livewire::test(Sorteos::class)
            ->call('openCatalog', 'clients')
            ->assertSet('showCatalogModal', true)
            ->assertDontSee('data-test="report-catalog-search"', false)
            ->set('catalogSearch', 'Carlos')
            ->assertForbidden();
    }

    public function test_route_is_forbidden_without_the_main_filter_action(): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'rpt'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'rptstr'],
                'accionesi' => [['codigo' => 'rptstrbtn09']],
            ]],
        ]]);

        $this->get(route('reportes.sorteos'))->assertForbidden();
    }

    private function fakeReportApi(): void
    {
        Http::fake([
            'api.example.test/api/reportes/sorteos/list_sorteos*' => Http::response($this->page([
                ['id' => 8, 'nombre' => 'Sorteo de verano'],
            ])),
            'api.example.test/api/reportes/sorteos/list_clientes*' => Http::response($this->page([
                ['id' => 21, 'docid' => '0801199012345', 'pnombre' => 'Ana', 'papellido' => 'López'],
            ])),
            'api.example.test/api/reportes/sorteos/list_usuarios*' => Http::response($this->page([
                ['id' => 4, 'nombre' => 'Operador'],
            ])),
            'api.example.test/api/reportes/sorteos/list_stocks*' => Http::response($this->page([
                ['id' => 3, 'descripcion' => 'Sucursal Centro'],
            ])),
            'api.example.test/api/reportes/sorteos/filter_clientes*' => Http::response($this->page([
                ['id' => 22, 'docid' => '0501198811111', 'pnombre' => 'Carlos', 'papellido' => 'Pérez'],
            ])),
            'api.example.test/api/reportes/sorteos/filter_sorteos*' => Http::response($this->page([
                ['id' => 9, 'nombre' => 'Sorteo filtrado'],
            ])),
            'api.example.test/api/reportes/sorteos/filter_usuarios*' => Http::response($this->page([
                ['id' => 5, 'nombre' => 'Operador filtrado'],
            ])),
            'api.example.test/api/reportes/sorteos/filter_stocks*' => Http::response($this->page([
                ['id' => 4, 'descripcion' => 'Stock filtrado'],
            ])),
            'api.example.test/api/reportes/sorteos/filter*' => Http::response($this->page([
                [
                    'id' => 15,
                    'ntiket' => '1024',
                    'promocion' => ['id' => 8, 'nombre' => 'Sorteo de verano'],
                    'cliente' => [
                        'id' => 21,
                        'docid' => '0801199012345',
                        'pnombre' => 'Ana',
                        'papellido' => 'López',
                    ],
                    'usuario' => ['id' => 4, 'nombre' => 'Operador'],
                    'stock' => ['id' => 3, 'descripcion' => 'Sucursal Centro'],
                    'created_at' => '2026-07-29 10:30:00',
                ],
            ])),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    private function page(array $data): array
    {
        return [
            'message' => 'ok',
            'code' => 200,
            'data' => $data,
            'meta' => [
                'total' => count($data),
                'current_page' => 1,
                'last_page' => 1,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportPermissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'rpt'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'rptstr'],
                'accionesi' => collect([
                    'rptstrbtn01',
                    'rptstrbnt02',
                    'rptstrbtn03',
                    'rptstrbnt04',
                    'rptstrbnt05',
                    'rptstrbtn06',
                    'rptstrbnt07',
                    'rptstrbtn08',
                    'rptstrbtn09',
                    'rptstrbtn10',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
