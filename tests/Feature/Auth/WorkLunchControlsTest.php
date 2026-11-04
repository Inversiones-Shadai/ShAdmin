<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\WorkLunchControls;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WorkLunchControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.work_lunch_path' => '/auth/worklunch',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_is_hidden_without_work_lunch_permissions(): void
    {
        session()->put('auth.api.spermisos', []);

        Livewire::test(WorkLunchControls::class)
            ->assertDontSee('data-test="work-lunch-menu-button"', false);

        Http::assertNothingSent();
    }

    public function test_it_shows_the_next_actions_allowed_by_permissions(): void
    {
        $this->putPermissions(['eswlbtn01', 'eswlbtn02']);
        Http::fake([
            'api.example.test/api/auth/worklunch/today' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => $this->record(),
            ]),
        ]);

        Livewire::test(WorkLunchControls::class)
            ->assertSee('data-test="work-lunch-menu-button"', false)
            ->assertSee('Jornada activa')
            ->assertSee('Registrar salida')
            ->assertSee('Iniciar almuerzo')
            ->assertSee('8:00 AM');
    }

    public function test_it_updates_the_header_after_starting_lunch(): void
    {
        $this->putPermissions(['eswlbtn01', 'eswlbtn02']);
        Http::fake([
            'api.example.test/api/auth/worklunch/today' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => $this->record(),
            ]),
            'api.example.test/api/auth/worklunch/lunch' => Http::response([
                'message' => 'Inicio de almuerzo registrado correctamente.',
                'code' => 200,
                'data' => [
                    ...$this->record(),
                    'lunch_start_time' => '2026-07-29 12:00:00',
                    'lunch_status' => 'on_lunch',
                ],
            ]),
        ]);

        Livewire::test(WorkLunchControls::class)
            ->call('registerLunch')
            ->assertSet('workLunch.lunch_start_time', '2026-07-29 12:00:00')
            ->assertSee('En almuerzo')
            ->assertSee('Finalizar almuerzo')
            ->assertDispatched('sweetalert:toast');
    }

    public function test_it_forbids_calling_an_action_without_its_permission(): void
    {
        $this->putPermissions(['eswlbtn02']);
        Http::fake([
            'api.example.test/api/auth/worklunch/today' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => $this->record(),
            ]),
        ]);

        Livewire::test(WorkLunchControls::class)
            ->assertDontSee('data-test="register-work-button"', false)
            ->call('registerWork')
            ->assertForbidden();
    }

    /**
     * @param  array<int, string>  $actions
     */
    private function putPermissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [
            [
                'modulo_l' => ['codigo' => 'aht'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'eswl'],
                        'accionesi' => array_map(
                            fn (string $code): array => ['codigo' => $code],
                            $actions,
                        ),
                    ],
                ],
            ],
        ]);
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
