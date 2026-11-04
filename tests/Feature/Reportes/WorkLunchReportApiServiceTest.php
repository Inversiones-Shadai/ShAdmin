<?php

namespace Tests\Feature\Reportes;

use App\Services\Api\WorkLunchReportApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkLunchReportApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.report_work_lunch_path' => '/reportes/worklunch',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.device_name' => 'DESKTOP-TEST',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_filters_work_lunch_records_with_the_expected_api_contract(): void
    {
        Http::fake([
            'api.example.test/api/reportes/worklunch/filter*' => Http::response([
                'message' => 'Reporte de jornada laboral consultado.',
                'code' => 200,
                'data' => [[
                    'id' => 41,
                    'work_date' => '2026-07-30',
                    'usuario' => ['id' => 7, 'nombre' => 'Ana López'],
                    'net_work_minutes' => 450,
                    'lunch_minutes' => 45,
                ]],
                'meta' => [
                    'total' => 31,
                    'current_page' => 2,
                    'last_page' => 3,
                ],
            ]),
        ]);

        $result = app(WorkLunchReportApiService::class)->filter(
            7,
            '2026-07-01',
            '2026-07-30',
            2,
        );

        $this->assertSame(41, $result->items[0]['id']);
        $this->assertSame(31, $result->total);
        $this->assertSame(2, $result->currentPage);
        $this->assertSame(3, $result->lastPage);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.example.test/api/reportes/worklunch/filter?usuario=7&work_date%5B0%5D=2026-07-01&work_date%5B1%5D=2026-07-30&page=2'
            && $request->hasHeader('Authorization', 'Bearer api-token')
            && $request->data()['usuario'] === 7
            && $request->data()['work_date'] === ['2026-07-01', '2026-07-30']
            && $request->data()['page'] === 2);
    }

    public function test_it_loads_the_complete_worker_catalog(): void
    {
        Http::fake([
            'api.example.test/api/reportes/worklunch/listUsuarios' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 7, 'nombre' => 'Ana López'],
                    ['id' => 8, 'nombre' => 'Luis Pérez'],
                ],
            ]),
        ]);

        $users = app(WorkLunchReportApiService::class)->users();

        $this->assertSame([7, 8], array_column($users, 'id'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.example.test/api/reportes/worklunch/listUsuarios');
    }

    public function test_it_collects_all_filtered_pages_for_an_export(): void
    {
        Http::fake([
            'api.example.test/api/reportes/worklunch/filter*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'ok',
                    'code' => 200,
                    'data' => [[
                        'id' => $page,
                        'work_date' => "2026-07-0{$page}",
                    ]],
                    'meta' => [
                        'total' => 2,
                        'current_page' => $page,
                        'last_page' => 2,
                    ],
                ]);
            },
        ]);

        $rows = app(WorkLunchReportApiService::class)->allFiltered(
            7,
            '2026-07-01',
            '2026-07-30',
        );

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['id']);
        $this->assertSame(2, $rows[1]['id']);
        Http::assertSentCount(2);
    }
}
