<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_header_shows_the_api_user_name_and_role(): void
    {
        $user = User::factory()->create(['name' => 'Usuario Local']);
        $this->actingAs($user);
        $this->withSession([
            'auth.api' => [
                'uname' => 'Juan Pérez',
                'urol' => 'Administrador',
            ],
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-test="header-user-identity"', false)
            ->assertSee('Juan Pérez')
            ->assertSee('Administrador')
            ->assertDontSee('Cuenta');
    }

    public function test_header_replaces_notifications_with_the_assigned_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession([
            'auth.api' => [
                'stockname' => 'Sucursal Centro',
                'stockid' => 7,
            ],
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-test="header-stock-info"', false)
            ->assertSee('Stock')
            ->assertSee('Sucursal Centro')
            ->assertDontSee('Notificaciones');
    }

    public function test_header_identifies_when_the_session_has_no_assigned_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-test="header-stock-info"', false)
            ->assertSee('Sin stock asignado');
    }

    public function test_header_contains_web_logout_option(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-test="session-options-button"', false)
            ->assertSee('Cerrar sesión')
            ->assertDontSee('exit-application-button');
    }

    public function test_work_lunch_control_is_deferred_and_persisted_between_navigations(): void
    {
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession([
            'auth.api.token' => 'api-token',
            'auth.api.spermisos' => [
                [
                    'modulo_l' => ['codigo' => 'aht'],
                    'vistas_l' => [
                        [
                            'vistai' => ['codigo' => 'eswl'],
                            'accionesi' => [
                                ['codigo' => 'eswlbtn01'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Http::preventStrayRequests();

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('x-persist="work-lunch-controls"', false)
            ->assertSee('x-init=', false);
        Http::assertNothingSent();
    }

    public function test_logout_closes_the_session_and_returns_to_login(): void
    {
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.auth_logout_path' => '/auth/session/logout',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        Http::fake([
            'api.example.test/api/auth/session/logout' => Http::response([
                'message' => 'logout success',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession(['auth.api.token' => 'api-token']);

        $response = $this->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull(session('auth.api.token'));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/auth/session/logout'
            && $request->hasHeader('Authorization', 'Bearer api-token')
            && $request->hasHeader('Idempotency-Key'));
    }

    public function test_legacy_exit_url_closes_the_web_session(): void
    {
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.auth_logout_path' => '/auth/session/logout',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        Http::fake([
            'api.example.test/api/auth/session/logout' => Http::response([
                'message' => 'logout success',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession(['auth.api.token' => 'api-token']);

        $response = $this->post(route('app.exit'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('auth.api.token'));
        Http::assertSentCount(1);
    }

    public function test_authenticated_users_can_visit_config_users(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession([
            'auth.api.spermisos' => [
                [
                    'modulo_l' => ['codigo' => 'cfg'],
                    'vistas_l' => [
                        [
                            'vistai' => ['codigo' => 'usr'],
                            'accionesi' => [
                                ['codigo' => 'usrbnt02'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->get(route('config.users'));
        $response
            ->assertOk()
            ->assertSee('data-test="config-accordion"', false)
            ->assertSee('Config')
            ->assertSee('Usuarios')
            ->assertSee('data:image/png;base64,', false);
    }
}
