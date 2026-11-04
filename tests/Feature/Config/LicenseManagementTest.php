<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\Licenses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LicenseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
    }

    public function test_denies_route_and_direct_livewire_access_without_license_permission(): void
    {
        $this->permissions([]);
        $this->fakeCustomers();
        $this->get(route('config.licenses'))->assertForbidden();
        Livewire::test(Licenses::class)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_renders_menu_and_list_but_cannot_generate_with_only_list_permission(): void
    {
        $this->permissions(['licbtn01']);
        $this->fakeCustomers();
        $this->fakeList();
        $this->get(route('dashboard'))->assertOk()->assertSee('Licencias de escritorio');
        Livewire::test(Licenses::class)->assertSet('licenses', [])->assertSet('filterCustomerId', '')
            ->assertDontSee('Synthetic license')->call('selectCustomer', '20000000-0000-4000-8000-000000000001')
            ->assertSee('Synthetic license')->assertDontSee('Generar licencia')
            ->call('create')->assertForbidden();
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->hasHeader('Action-Btn', 'licbtn01'));
    }

    public function test_generates_named_license_with_selected_term_and_displays_key_once(): void
    {
        $this->permissions(['licbtn01', 'licbtn02']);
        $this->fakeCustomers();
        Http::fake(['api.example.test/api/config/licenses*' => function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['code' => 200, 'data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1]]);
            }

            return Http::response(['code' => 200, 'data' => ['id' => '10000000-0000-4000-8000-000000000001', 'license_key' => 'SHD-SYNTHETIC-ONCE']]);
        }]);
        Livewire::test(Licenses::class)->call('create')->set('label', 'Synthetic license')
            ->set('termMonths', 6)->set('seats', 2)->call('save')->assertHasNoErrors()
            ->assertSet('generatedKey', 'SHD-SYNTHETIC-ONCE')->assertSee('SHD-SYNTHETIC-ONCE')
            ->set('generatedKey', '')->assertDontSee('SHD-SYNTHETIC-ONCE');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('Action-Btn', 'licbtn02')
            && $request['customer_id'] === '20000000-0000-4000-8000-000000000001' && $request['term_months'] === 6 && $request['label'] === 'Synthetic license' && $request['product'] === 'shpos'
            && ! array_key_exists('expires_at', $request->data()) && $request['seats'] === 2);
    }

    public function test_details_and_unlink_require_separate_permissions(): void
    {
        $this->permissions(['licbtn01', 'licbtn04']);
        $this->fakeCustomers();
        Http::fake(['api.example.test/api/config/licenses*' => function ($request) {
            if (parse_url($request->url(), PHP_URL_PATH) === '/api/config/licenses') {
                return Http::response(['code' => 200, 'data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1]]);
            }

            return Http::response(['code' => 200, 'data' => ['devices' => ['data' => [], 'last_page' => 1], 'events' => ['data' => [], 'last_page' => 1]]]);
        }]);
        Livewire::test(Licenses::class)->call('show', '10000000-0000-4000-8000-000000000001')
            ->assertSee('Historial de generación')->call('unlink', '20000000-0000-4000-8000-000000000001')->assertForbidden();
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_form_offers_only_named_application_specific_fixed_terms(): void
    {
        $this->permissions(['licbtn01', 'licbtn02']);
        $this->fakeCustomers();
        $this->fakeList();
        Livewire::test(Licenses::class)->call('create')->assertSee('Nombre de la licencia')
            ->assertSee('Esta licencia solo permite activar la aplicación seleccionada.')
            ->assertSee('1 mes')->assertSee('6 meses')->assertSee('1 año')->assertDontSee('datetime-local');
    }

    public function test_name_and_supported_term_are_required_before_generating(): void
    {
        $this->permissions(['licbtn01', 'licbtn02']);
        $this->fakeCustomers();
        $this->fakeList();
        Livewire::test(Licenses::class)->call('create')->set('label', '')->set('termMonths', 2)
            ->call('save')->assertHasErrors(['label', 'termMonths']);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_editing_name_does_not_change_application_or_extend_expiration(): void
    {
        $this->permissions(['licbtn01', 'licbtn03']);
        $this->fakeCustomers();
        $this->fakeList();
        Livewire::test(Licenses::class)->call('selectCustomer', '20000000-0000-4000-8000-000000000001')
            ->call('edit', '10000000-0000-4000-8000-000000000001')
            ->assertSet('renewalMonths', 0)->assertSee('Conservar vencimiento actual')
            ->set('label', 'Renamed license')->set('product', 'hq-client')->call('save')->assertHasNoErrors();
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['label'] === 'Renamed license'
            && ! array_key_exists('product', $request->data()) && ! array_key_exists('expires_at', $request->data())
            && ! array_key_exists('renewal_months', $request->data()));
    }

    public function test_renewal_sends_selected_term_and_original_expiration_for_concurrency_control(): void
    {
        $this->permissions(['licbtn01', 'licbtn03']);
        $this->fakeCustomers();
        $this->fakeList();
        Livewire::test(Licenses::class)->call('selectCustomer', '20000000-0000-4000-8000-000000000001')
            ->call('edit', '10000000-0000-4000-8000-000000000001')
            ->set('renewalMonths', 12)->call('save')->assertHasNoErrors();
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['renewal_months'] === 12
            && $request['expected_expires_at'] === '2030-01-01T12:00:00+00:00'
            && ! array_key_exists('expires_at', $request->data()));
    }

    public function test_deletion_requires_its_own_permission_even_when_unlink_is_allowed(): void
    {
        $this->permissions(['licbtn01', 'licbtn05']);
        $this->fakeCustomers();
        $this->fakeList();
        Livewire::test(Licenses::class)->call('selectCustomer', '20000000-0000-4000-8000-000000000001')
            ->assertDontSee('>Eliminar<', false)
            ->call('delete', '10000000-0000-4000-8000-000000000001')->assertForbidden();
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_deletes_license_with_confirmation_and_refreshes_list_customers_and_open_panels(): void
    {
        $this->permissions(['licbtn01', 'licbtn03', 'licbtn04', 'licbtn06']);
        $this->fakeCustomers();
        Http::fake(['api.example.test/api/config/licenses/10000000-0000-4000-8000-000000000001*' => function ($request) {
            return $request->method() === 'DELETE'
                ? Http::response(['code' => 200, 'message' => 'Licencia eliminada.', 'data' => null])
                : Http::response(['code' => 200, 'data' => ['devices' => ['data' => []], 'events' => ['data' => []]]]);
        }]);
        $this->fakeList();
        Livewire::test(Licenses::class)->call('selectCustomer', '20000000-0000-4000-8000-000000000001')
            ->assertSee('¿Eliminar esta licencia definitivamente?')
            ->call('show', '10000000-0000-4000-8000-000000000001')
            ->call('edit', '10000000-0000-4000-8000-000000000001')
            ->call('delete', '10000000-0000-4000-8000-000000000001')
            ->assertSet('message', 'Licencia eliminada.')->assertSet('error', null)
            ->assertSet('detailId', null)->assertSet('details', [])->assertSet('editingId', null)->assertSet('showForm', false);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'https://api.example.test/api/config/licenses/10000000-0000-4000-8000-000000000001'
            && $request->hasHeader('Action-Btn', 'licbtn06'));
        $this->assertCount(3, Http::recorded(fn ($request) => str_contains($request->url(), '/customers')));
        $this->assertCount(2, Http::recorded(fn ($request) => $request->method() === 'GET' && parse_url($request->url(), PHP_URL_PATH) === '/api/config/licenses'));
    }

    public function test_hides_delete_for_linked_license_and_shows_api_rejection_for_stale_list(): void
    {
        $this->permissions(['licbtn01', 'licbtn06']);
        $this->fakeCustomers();
        Http::fake(['api.example.test/api/config/licenses/10000000-0000-4000-8000-000000000001' => Http::response([
            'message' => 'Desvincula todos los equipos antes de eliminar la licencia.',
        ], 422)]);
        $this->fakeList(canDelete: false);
        Livewire::test(Licenses::class)->call('selectCustomer', '20000000-0000-4000-8000-000000000001')
            ->assertDontSee('¿Eliminar esta licencia definitivamente?')
            ->call('delete', '10000000-0000-4000-8000-000000000001')
            ->assertSet('error', 'Desvincula todos los equipos antes de eliminar la licencia.')
            ->assertSee('Synthetic license');
    }

    public function test_deleting_last_license_on_a_later_page_returns_to_previous_page(): void
    {
        $this->permissions(['licbtn01', 'licbtn06']);
        $this->fakeCustomers();
        Http::fake(['api.example.test/api/config/licenses/10000000-0000-4000-8000-000000000001' => Http::response([
            'code' => 200, 'message' => 'Licencia eliminada.',
        ])]);
        $this->fakeList();
        Livewire::test(Licenses::class)->call('selectCustomer', '20000000-0000-4000-8000-000000000001')
            ->set('page', 2)->set('lastPage', 2)
            ->call('delete', '10000000-0000-4000-8000-000000000001')->assertSet('page', 1);
        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET' && parse_url($request->url(), PHP_URL_PATH) === '/api/config/licenses'
                && ($query['page'] ?? '') === '1';
        });
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'cfg'], 'vistas_l' => [[
            'vistai' => ['codigo' => 'lic'], 'accionesi' => array_map(fn ($code) => ['codigo' => $code], $actions),
        ]]]]);
    }

    private function fakeCustomers(): void
    {
        Http::fake(['api.example.test/api/config/licenses/customers*' => Http::response([
            'code' => 200, 'data' => [['id' => '20000000-0000-4000-8000-000000000001', 'name' => 'DNDEV', 'license_count' => 1]],
            'meta' => ['current_page' => 1, 'last_page' => 1],
        ])]);
    }

    private function fakeList(bool $canDelete = true): void
    {
        Http::fake(['api.example.test/api/config/licenses*' => Http::response(['code' => 200, 'data' => [[
            'id' => '10000000-0000-4000-8000-000000000001', 'customer_id' => '20000000-0000-4000-8000-000000000001', 'customer_name' => 'DNDEV', 'label' => 'Synthetic license', 'product' => 'shpos',
            'term_months' => 12, 'key_suffix' => '12345678', 'seats' => 1, 'used_seats' => 0, 'enabled' => true, 'expires_at' => '2030-01-01 12:00:00',
            'can_delete' => $canDelete,
        ]], 'meta' => ['current_page' => 1, 'last_page' => 1]])]);
    }
}
