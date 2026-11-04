<?php

namespace Tests\Feature;

use App\Livewire\Config\Modules;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RemoteSelectorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'cfg'], 'vistas_l' => [
            ['vistai' => ['codigo' => 'mdl'], 'accionesi' => array_map(fn ($code) => ['codigo' => $code], ['mdlbtn01', 'mdlbtn02', 'mdlbtn06'])],
        ]]]);
    }

    public function test_proxy_preserves_query_scope_and_permission_without_exposing_the_api_token(): void
    {
        $this->fakeSelectorChoice('module-states', ['id' => 31, 'descripcion' => 'ACTIVO']);
        $this->getJson('/ui/selectors/module-states?search=activo&page=2')->assertOk()->assertJsonPath('data.0.id', '31')->assertDontSee('api-token');
        Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/api/selectors/module-states'
            && $request->hasHeader('Action-Btn', 'mdlbtn06') && $request->hasHeader('Authorization', 'Bearer api-token')
            && $request['search'] === 'activo' && $request['page'] === '2');
        Http::assertSentCount(1);
    }

    public function test_proxy_denies_unpermitted_sources_and_invalid_queries_without_sending_requests(): void
    {
        $this->getJson('/ui/selectors/permission-actions')->assertForbidden();
        $this->getJson('/ui/selectors/unknown-source')->assertNotFound();
        $this->getJson('/ui/selectors/module-states?selected=bad')->assertUnprocessable();
        $this->getJson('/ui/selectors/module-states?filters[parent]=bad')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_only_the_verified_choice_is_remembered_and_the_form_draft_is_preserved(): void
    {
        Http::fake(['*/config/modulo/listar*' => Http::response(['code' => 200, 'data' => []])]);
        $this->fakeSelectorChoice('module-states', ['id' => 31, 'descripcion' => 'ACTIVO']);
        Livewire::test(Modules::class)->call('openCreate')->set('nombre', 'Borrador')
            ->assertSet('states', [])->call('rememberSelectorChoice', 'module-states', '31')
            ->assertCount('states', 1)->assertSet('states.0.id', 31)->assertSet('nombre', 'Borrador');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/selectors/module-states') && $request['selected'] === '31');
    }

    public function test_a_component_cannot_write_other_selector_caches(): void
    {
        Http::fake(['*/config/modulo/listar*' => Http::response(['code' => 200, 'data' => []])]);
        Livewire::test(Modules::class)->call('rememberSelectorChoice', 'permission-modules', '1')->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/selectors/'));
    }

    public function test_expired_sessions_and_unavailable_servers_produce_retryable_json_errors(): void
    {
        Http::fake(['*/selectors/module-states*' => Http::sequence()->push(['code' => 503], 503)->push(['code' => 401], 401)]);
        $this->getJson('/ui/selectors/module-states')->assertStatus(503);
        $this->getJson('/ui/selectors/module-states')->assertUnauthorized();
    }
}
