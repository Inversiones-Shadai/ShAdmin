<?php

namespace Tests\Feature\Config;

use App\Services\Api\WorkHoursApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkHoursApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.work_hours_path' => '/config/horas-lab',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.device_name' => 'DESKTOP-TEST',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_loads_a_schedule_by_user_and_treats_not_found_as_unassigned(): void
    {
        Http::fake([
            'api.example.test/api/config/horas-lab/find/7' => Http::response([
                'message' => 'Configuración de horas consultada.',
                'code' => 200,
                'data' => [
                    'id' => 15,
                    'usuario' => ['id' => 7],
                    'horas_lab' => 8,
                    'horas_lunch' => 60,
                ],
            ]),
            'api.example.test/api/config/horas-lab/find/8' => Http::response([
                'message' => 'El usuario no tiene una configuración de horas.',
                'code' => 404,
                'data' => null,
            ], 404),
        ]);

        $service = app(WorkHoursApiService::class);

        $this->assertSame(15, $service->findByUser(7)['id']);
        $this->assertNull($service->findByUser(8));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/horas-lab/find/7'
            && $request->hasHeader('Authorization', 'Bearer api-token'));
    }

    public function test_it_creates_and_updates_work_hours_with_the_api_contract(): void
    {
        Http::fake([
            'api.example.test/api/config/horas-lab/save' => Http::sequence()->push([
                'message' => 'Configuración de horas creada correctamente.',
                'code' => 201,
                'data' => ['id' => 15],
            ], 201)->push([
                'message' => 'Configuración de horas actualizada correctamente.',
                'code' => 200,
                'data' => ['id' => 15],
            ]),
        ]);

        $service = app(WorkHoursApiService::class);

        $this->assertSame(
            'Configuración de horas creada correctamente.',
            $service->create([
                'usuario' => 7,
                'horas_lab' => 8,
                'horas_lunch' => 60,
            ]),
        );
        $this->assertSame(
            'Configuración de horas actualizada correctamente.',
            $service->update([
                'usuario' => 7,
                'horas_lab' => 9,
                'horas_lunch' => 90,
            ]),
        );

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/horas-lab/save'
            && $request->data() === [
                'usuario' => ['id' => 7],
                'horas_lab' => 8,
                'horas_lunch' => 60,
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/horas-lab/save'
            && $request->data() === [
                'usuario' => ['id' => 7],
                'horas_lab' => 9,
                'horas_lunch' => 90,
            ]);
    }
}
