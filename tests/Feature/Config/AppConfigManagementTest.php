<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\AppConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AppConfigManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.app_version' => '2.5.0',
        ]);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        $this->permissions(['acfbtn01', 'acfbtn02', 'acfbtn03']);
    }

    private function fakeApi(): void
    {
        Http::fake([
            '*/config/appconfig' => Http::response(['code' => 201, 'message' => 'Configuración creada.', 'data' => $this->record()]),
            '*/config/appconfig?page=*' => Http::response(['code' => 200, 'data' => [$this->record()], 'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1]]),
            '*/config/appconfig/1' => Http::response(['code' => 200, 'message' => 'Configuración actualizada.', 'data' => $this->record()]),
        ]);
    }

    public function test_route_and_menu_show_configuration_with_list_permission(): void
    {
        $this->fakeApi();
        $this->get(route('config.appconfig'))->assertOk()->assertSee('Configuración de aplicación')->assertSee('admin@example.test');
        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="permission-view-cfg-acf"', false);
    }

    public function test_edit_preserves_version_and_sends_all_mail_fields_with_the_update_permission(): void
    {
        $this->fakeApi();
        Livewire::test(AppConfig::class)->assertSee('alerts@example.test')
            ->call('openEdit', 1)->assertSet('appv', str_repeat('a', 64))
            ->set('mail_cc1', 'new@example.test')->set('mail_cc3', 'third@example.test')
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false)->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/appconfig/1'
            && $request->hasHeader('Action-Btn', 'acfbtn03') && $request->hasHeader('Idempotency-Key')
            && $request['appv'] === str_repeat('a', 64) && $request['mail_cc1'] === 'new@example.test'
            && $request['mail_cc2'] === null && $request['mail_cc3'] === 'third@example.test');
    }

    public function test_creation_sends_empty_optional_fields_as_null(): void
    {
        $this->fakeApi();
        Livewire::test(AppConfig::class)->call('openCreate')
            ->set('mail_alert', 'alerts@example.test')->set('mail_cc1', 'admin@example.test')
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Action-Btn', 'acfbtn02')
            && $request['appv'] === null && $request['mail_cc2'] === null && $request['mail_cc3'] === null);
    }

    public function test_invalid_email_is_rejected_before_sending_a_write(): void
    {
        $this->fakeApi();
        Livewire::test(AppConfig::class)->call('openEdit', 1)->set('mail_cc1', 'invalid')->call('save')->assertHasErrors(['mail_cc1' => 'email']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
    }

    public function test_read_only_users_cannot_create_or_update_even_by_calling_actions_directly(): void
    {
        $this->fakeApi();
        $this->permissions(['acfbtn01']);
        Livewire::test(AppConfig::class)->assertDontSee('Nueva configuración')->assertDontSee('Editar')->call('openEdit', 1)->assertForbidden();
        Livewire::test(AppConfig::class)->call('openCreate')->assertForbidden();
        Livewire::test(AppConfig::class)->call('save')->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }

    public function test_route_denies_users_without_list_permission(): void
    {
        $this->permissions([]);
        $this->get(route('config.appconfig'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_api_validation_errors_keep_the_form_open(): void
    {
        Http::fake(['*/config/appconfig/1' => fn (Request $request) => $request->method() === 'PUT'
            ? Http::response(['code' => 422, 'message' => 'Correo inválido.', 'data' => ['errors' => ['mail_cc1' => ['Revisa el correo.']]]], 422)
            : Http::response(['code' => 200, 'data' => $this->record()])]);
        $this->fakeApi();
        Livewire::test(AppConfig::class)->call('openEdit', 1)->call('save')
            ->assertHasErrors(['mail_cc1'])->assertSet('showForm', true)->assertSet('saveError', 'Correo inválido.');
    }

    public function test_expired_api_session_redirects_to_login(): void
    {
        Http::fake(['*/config/appconfig?page=*' => Http::response(['message' => 'Sesión expirada', 'code' => 401], 401)]);
        Livewire::test(AppConfig::class)->assertRedirect(route('login'));
        $this->assertNull(session('auth.api.token'));
    }

    private function permissions(array $actions): void
    {
        session()->forget('auth.api.abilities');
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'cfg'],
            'vistas_l' => [['vistai' => ['codigo' => 'acf'], 'accionesi' => array_map(fn (string $code): array => ['codigo' => $code], $actions)]],
        ]]);
    }

    private function record(): array
    {
        return ['id' => 1, 'appv' => str_repeat('a', 64), 'mail_alert' => 'alerts@example.test', 'mail_cc1' => 'admin@example.test', 'mail_cc2' => null, 'mail_cc3' => null];
    }
}
