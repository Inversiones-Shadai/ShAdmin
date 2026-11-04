<?php

namespace Tests\Feature\Auth;

use App\Exceptions\ApiOperationException;
use App\Services\Api\WorkLunchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkLunchApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.work_lunch_path' => '/auth/worklunch',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_reads_the_current_work_lunch_state(): void
    {
        Http::fake([
            'api.example.test/api/auth/worklunch/today' => Http::response([
                'message' => 'Jornada laboral consultada.',
                'code' => 200,
                'data' => $this->record(),
            ]),
        ]);

        $session = app(WorkLunchApiService::class)->today();

        $this->assertSame(15, $session?->id);
        $this->assertSame('2026-07-29 08:00:00', $session?->workStartTime);
        $this->assertNull($session?->workEndTime);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.example.test/api/auth/worklunch/today'
            && $request->hasHeader('Authorization', 'Bearer api-token'));
    }

    public function test_it_returns_null_when_today_has_no_work_session(): void
    {
        Http::fake([
            'api.example.test/api/auth/worklunch/today' => Http::response([
                'message' => 'Aún no has registrado la entrada de hoy.',
                'code' => 200,
                'data' => null,
            ]),
        ]);

        $this->assertNull(app(WorkLunchApiService::class)->today());
    }

    public function test_it_registers_work_with_an_idempotency_key(): void
    {
        Http::fake([
            'api.example.test/api/auth/worklunch/work' => Http::response([
                'message' => 'Entrada registrada correctamente.',
                'code' => 200,
                'data' => $this->record(),
            ]),
        ]);

        $result = app(WorkLunchApiService::class)->work();

        $this->assertSame('Entrada registrada correctamente.', $result['message']);
        $this->assertSame(15, $result['session']->id);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/auth/worklunch/work'
            && $request->hasHeader('Idempotency-Key'));
    }

    public function test_it_exposes_api_domain_errors_for_lunch(): void
    {
        Http::fake([
            'api.example.test/api/auth/worklunch/lunch' => Http::response([
                'message' => 'Primero registra la entrada de la jornada laboral.',
                'code' => 400,
                'data' => null,
            ], 400),
        ]);

        $this->expectException(ApiOperationException::class);
        $this->expectExceptionMessage('Primero registra la entrada de la jornada laboral.');

        app(WorkLunchApiService::class)->lunch();
    }

    /**
     * @return array<string, int|string|null>
     */
    private function record(): array
    {
        return [
            'id' => 15,
            'work_date' => '2026-07-29',
            'wkstart_time' => '2026-07-29 08:00:00',
            'wkend_time' => null,
            'lunch_start_time' => null,
            'lunch_end_time' => null,
            'work_status' => 'working',
            'lunch_status' => 'not_started',
        ];
    }
}
