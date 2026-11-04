<?php

namespace Tests\Feature\Reportes;

use App\Livewire\Reportes\JornadaLaboral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WorkLunchReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.report_draw_path' => '/reportes/sorteos',
            'services.shadmin_api.report_work_lunch_path' => '/reportes/worklunch',
            'services.shadmin_api.work_hours_path' => '/config/horas-lab',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->reportPermissions());
        Http::preventStrayRequests();
    }

    public function test_route_navigation_and_compliance_indicators_are_available(): void
    {
        $this->fakeApis();

        $this->get(route('reportes.jornada-laboral'))
            ->assertOk()
            ->assertSee('Reporte de jornada laboral');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-rpt-rptwl"', false)
            ->assertSee('Jornada laboral');

        Livewire::test(JornadaLaboral::class)
            ->assertSet('users', [])
            ->set('selectedUser', '7')
            ->set('fromDate', '2026-07-01')
            ->set('toDate', '2026-07-30')
            ->call('applyFilters')
            ->assertHasNoErrors()
            ->assertSet('filterActive', true)
            ->assertSet('schedule.work_minutes', 480)
            ->assertSet('schedule.lunch_minutes', 30)
            ->assertSet('shortWorkCount', 1)
            ->assertSet('longLunchCount', 1)
            ->assertSet('jornadas.0.work_status', 'short')
            ->assertSet('jornadas.0.work_deficit_minutes', 30)
            ->assertSet('jornadas.0.lunch_status', 'exceeded')
            ->assertSet('jornadas.0.lunch_excess_minutes', 15)
            ->assertSee('Faltaron 30 min')
            ->assertSee('Excedió 15 min')
            ->assertSee('data-test="export-work-lunch-report-button"', false)
            ->assertSee('data-test="work-lunch-report-table"', false);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/reportes/worklunch/filter?')
            && $request->data()['usuario'] === 7
            && $request->data()['work_date'] === ['2026-07-01', '2026-07-30']);
    }

    public function test_open_sessions_are_not_marked_as_short_and_missing_schedules_are_reported(): void
    {
        $this->fakeApis(schedule: null, openSession: true);

        Livewire::test(JornadaLaboral::class)
            ->set('selectedUser', '7')
            ->set('fromDate', '2026-07-01')
            ->set('toDate', '2026-07-30')
            ->call('applyFilters')
            ->assertHasNoErrors()
            ->assertSet('schedule', null)
            ->assertSet('shortWorkCount', 0)
            ->assertSet('longLunchCount', 0)
            ->assertSet('jornadas.0.work_status', 'unconfigured')
            ->assertSee('El trabajador no tiene horario asignado')
            ->assertSee('Sin horario');
    }

    public function test_it_exports_the_active_report_as_pdf_or_excel(): void
    {
        $this->fakeApis();

        Livewire::test(JornadaLaboral::class)
            ->set('selectedUser', '7')
            ->set('fromDate', '2026-07-01')
            ->set('toDate', '2026-07-30')
            ->call('applyFilters')
            ->call('openExport')
            ->assertSet('showExportModal', true)
            ->set('exportFormat', 'pdf')
            ->call('exportReport')
            ->assertFileDownloaded(contentType: 'application/pdf');

        Livewire::test(JornadaLaboral::class)
            ->set('selectedUser', '7')
            ->set('fromDate', '2026-07-01')
            ->set('toDate', '2026-07-30')
            ->call('applyFilters')
            ->call('openExport')
            ->set('exportFormat', 'xlsx')
            ->call('exportReport')
            ->assertFileDownloaded(
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
    }

    public function test_route_is_forbidden_without_the_report_filter_action(): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'rpt'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'rptstr'],
                'accionesi' => [['codigo' => 'rptstrbtn08']],
            ]],
        ]]);

        $this->get(route('reportes.jornada-laboral'))->assertForbidden();
    }

    public function test_user_list_and_export_require_their_own_rptwl_action_codes(): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'rpt'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'rptwl'],
                'accionesi' => collect([
                    'rptwlbtn01',
                    'rptwlbtn03',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]]);
        $this->fakeApis();

        Livewire::test(JornadaLaboral::class)
            ->assertSet('users', [])
            ->assertDontSee('data-test="export-work-lunch-report-button"', false)
            ->assertDontSee('Buscar trabajador...');

        Livewire::test(JornadaLaboral::class)
            ->call('openExport')
            ->assertForbidden();
    }

    private function fakeApis(?array $schedule = ['horas_lab' => 8, 'horas_lunch' => 30], bool $openSession = false): void
    {
        $scheduleResponse = $schedule === null
            ? Http::response([
                'message' => 'El usuario no tiene una configuración de horas.',
                'code' => 404,
                'data' => null,
            ], 404)
            : Http::response([
                'message' => 'Configuración de horas consultada.',
                'code' => 200,
                'data' => [
                    'id' => 15,
                    'usuario' => ['id' => 7],
                    ...$schedule,
                ],
            ]);

        Http::fake([
            'api.example.test/api/reportes/worklunch/listUsuarios' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 7, 'nombre' => 'Ana López']],
            ]),
            'api.example.test/api/config/horas-lab/find/7' => $scheduleResponse,
            'api.example.test/api/reportes/worklunch/filter*' => Http::response($this->page([
                [
                    'id' => 41,
                    'work_date' => '2026-07-30',
                    'usuario' => ['id' => 7, 'nombre' => 'Ana López'],
                    'device' => [
                        'id' => 3,
                        'displayname' => 'CAJA-01',
                        'stock' => ['id' => 5, 'descripcion' => 'Sucursal Centro'],
                    ],
                    'wkstart_time' => '2026-07-30 08:00:00',
                    'wkend_time' => $openSession ? null : '2026-07-30 16:15:00',
                    'lunch_start_time' => $openSession ? null : '2026-07-30 12:00:00',
                    'lunch_end_time' => $openSession ? null : '2026-07-30 12:45:00',
                    'net_work_minutes' => $openSession ? null : 450,
                    'lunch_minutes' => $openSession ? null : 45,
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
                'vistai' => ['codigo' => 'rptwl'],
                'accionesi' => collect([
                    'rptwlbtn01',
                    'rptwlbtn02',
                    'rptwlbtn03',
                    'rptwlbtn04',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
