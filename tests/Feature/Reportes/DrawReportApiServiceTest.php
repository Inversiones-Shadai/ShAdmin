<?php

namespace Tests\Feature\Reportes;

use App\Services\Api\DrawReportApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DrawReportApiServiceTest extends TestCase
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

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_filters_report_rows_and_preserves_pagination(): void
    {
        Http::fake([
            'api.example.test/api/reportes/sorteos/filter*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [$this->ticket()],
                'meta' => [
                    'total' => 35,
                    'current_page' => 2,
                    'last_page' => 2,
                ],
            ]),
        ]);

        $page = app(DrawReportApiService::class)->filterTickets([
            ['key' => 'promocion', 'value' => 8],
            ['key' => 'stock', 'value' => 3],
        ], 2);

        $this->assertSame('1024', $page->items[0]['ntiket']);
        $this->assertSame(35, $page->total);
        $this->assertSame(2, $page->currentPage);

        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://api.example.test/api/reportes/sorteos/filter?',
        ) && $request->data() === [
            'name' => 'reporte-sorteos',
            'filterItems' => [
                [
                    'key' => 'promocion',
                    'value' => '8',
                    'operator' => '=',
                    'logicalOperator' => 'AND',
                ],
                [
                    'key' => 'stock',
                    'value' => '3',
                    'operator' => '=',
                    'logicalOperator' => 'AND',
                ],
            ],
            'page' => 2,
        ]);
    }

    public function test_it_uses_the_list_and_remote_search_endpoints_for_each_catalog(): void
    {
        Http::fake([
            'api.example.test/api/reportes/sorteos/*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $service = app(DrawReportApiService::class);
        $service->listDraws();
        $service->filterDraws('Verano');
        $service->listClients();
        $service->filterClients('Ana');
        $service->listUsers();
        $service->filterUsers('Operador');
        $service->listStocks();
        $service->filterStocks('Centro');

        foreach ([
            '/list_sorteos',
            '/filter_sorteos',
            '/list_clientes',
            '/filter_clientes',
            '/list_usuarios',
            '/filter_usuarios',
            '/list_stocks',
            '/filter_stocks',
        ] as $path) {
            Http::assertSent(fn (Request $request): bool => str_contains($request->url(), $path));
        }

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/filter_sorteos')
            && $request->data()['filterItems'][0]['value'] === '%Verano%');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/filter_clientes')
            && count($request->data()['filterItems']) === 2
            && $request->data()['filterItems'][0]['logicalOperator'] === 'OR');
    }

    public function test_it_collects_all_filtered_pages_for_an_export(): void
    {
        Http::fake([
            'api.example.test/api/reportes/sorteos/filter*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'ok',
                    'code' => 200,
                    'data' => [[...$this->ticket(), 'id' => $page, 'ntiket' => (string) (1000 + $page)]],
                    'meta' => [
                        'total' => 2,
                        'current_page' => $page,
                        'last_page' => 2,
                    ],
                ]);
            },
        ]);

        $rows = app(DrawReportApiService::class)->allFilteredTickets([
            ['key' => 'promocion', 'value' => 8],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('1001', $rows[0]['ntiket']);
        $this->assertSame('1002', $rows[1]['ntiket']);
        Http::assertSentCount(2);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticket(): array
    {
        return [
            'id' => 15,
            'ntiket' => '1024',
            'promocion' => ['id' => 8, 'nombre' => 'Sorteo de verano'],
            'cliente' => ['id' => 21, 'docid' => '0801199012345', 'pnombre' => 'Ana', 'papellido' => 'López'],
            'usuario' => ['id' => 4, 'nombre' => 'Operador'],
            'stock' => ['id' => 3, 'descripcion' => 'Sucursal Centro'],
            'created_at' => '2026-07-29 10:30:00',
        ];
    }
}
