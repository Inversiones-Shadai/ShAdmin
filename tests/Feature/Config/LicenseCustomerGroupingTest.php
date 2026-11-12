<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\Licenses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LicenseCustomerGroupingTest extends TestCase
{
    use RefreshDatabase;

    private const DNDEV = '20000000-0000-4000-8000-000000000001';

    private const OTHER = '20000000-0000-4000-8000-000000000002';

    private const NEW_CUSTOMER = '20000000-0000-4000-8000-000000000003';

    private const LICENSE = '10000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
    }

    public function test_opening_view_loads_only_customers_and_requires_customer_for_new_license(): void
    {
        $this->permissions(['licbtn01', 'licbtn02']);
        $this->fakeApi();
        Livewire::test(Licenses::class)->assertSee('Selecciona un cliente para consultar sus licencias.')
            ->assertSet('licenses', [])->assertSet('filterCustomerId', '')
            ->assertDontSee('DNDEV workstation')->assertDontSee('Other workstation')
            ->assertDontSee('Buscar licencias')->set('search', 'test')->call('changePage', 1)
            ->call('create')->assertSet('customerId', '')->set('label', 'Named license')->call('save')->assertHasErrors('customerId');
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        Http::assertNotSent(fn ($request) => parse_url($request->url(), PHP_URL_PATH) === '/api/config/licenses');
    }

    public function test_selecting_switching_and_clearing_customer_never_loads_unfiltered_licenses(): void
    {
        $this->permissions(['licbtn01']);
        $this->fakeApi(23);
        Livewire::test(Licenses::class)->call('selectCustomer', self::OTHER)
            ->assertSee('Other workstation 1')->assertDontSee('DNDEV workstation')
            ->call('changePage', 1)->assertSet('page', 2)
            ->set('search', 'Other')->call('selectCustomer', self::DNDEV)
            ->assertSet('page', 1)->assertSet('lastPage', 1)->assertSet('search', '')
            ->assertSee('DNDEV workstation')->assertDontSee('Other workstation')
            ->call('selectCustomer')->assertSet('licenses', [])->assertSet('filterCustomerId', '')
            ->assertSet('detailId', null)->assertSet('details', [])
            ->assertSee('Selecciona un cliente para consultar sus licencias.')->assertDontSee('DNDEV workstation');
        $this->assertCount(4, Http::recorded(fn ($request) => parse_url($request->url(), PHP_URL_PATH) === '/api/config/licenses'));
        Http::assertNotSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return parse_url($request->url(), PHP_URL_PATH) === '/api/config/licenses' && empty($query['customer_id']);
        });
    }

    public function test_customer_filter_survives_license_pagination(): void
    {
        $this->permissions(['licbtn01']);
        $this->fakeApi(23);
        Livewire::test(Licenses::class)->call('selectCustomer', self::OTHER)->assertDontSee('DNDEV workstation')
            ->assertSet('lastPage', 2)->call('changePage', 1)->assertSet('page', 2)->assertSee('Cliente: Other company')
            ->assertDontSee('DNDEV workstation');
        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return parse_url($request->url(), PHP_URL_PATH) === '/api/config/licenses' && ($query['customer_id'] ?? '') === self::OTHER && ($query['page'] ?? '') === '2';
        });
    }

    public function test_selected_customer_is_used_when_generating_a_license(): void
    {
        $this->permissions(['licbtn01', 'licbtn02']);
        $this->fakeApi();
        Livewire::test(Licenses::class)->call('selectCustomer', self::OTHER)->call('create')->assertSet('customerId', self::OTHER)
            ->set('label', 'Other license')->call('save')->assertHasNoErrors()->assertSet('generatedKey', '')
            ->set('generationCode', '123456')->call('save')->assertHasNoErrors()->assertSet('generatedKey', 'SHD-SYNTHETIC');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && parse_url($request->url(), PHP_URL_PATH) === '/api/config/licenses'
            && $request['customer_id'] === self::OTHER);
    }

    public function test_can_create_customer_and_filter_its_licenses_after_saving(): void
    {
        $this->permissions(['licbtn01', 'licbtn02']);
        $this->fakeApi();
        Livewire::test(Licenses::class)->call('createCustomer')->set('customerName', 'New company')->call('saveCustomer')
            ->assertHasNoErrors()->assertSet('filterCustomerId', self::NEW_CUSTOMER)->assertSee('Licencias de New company');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('Action-Btn', 'licbtn02') && $request['name'] === 'New company');
    }

    public function test_can_rename_customer_without_changing_its_license(): void
    {
        $this->permissions(['licbtn01', 'licbtn03']);
        $this->fakeApi();
        Livewire::test(Licenses::class)->call('editCustomer', self::DNDEV)->set('customerName', 'DNDEV Group')->call('saveCustomer')
            ->assertHasNoErrors()->assertSee('Cliente: DNDEV Group')->assertSee('DNDEV workstation');
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->hasHeader('Action-Btn', 'licbtn03') && $request['name'] === 'DNDEV Group');
    }

    public function test_duplicate_customer_error_is_shown_on_name_field(): void
    {
        $this->permissions(['licbtn01', 'licbtn02']);
        $this->fakeApi();
        Livewire::test(Licenses::class)->call('createCustomer')->set('customerName', 'dndev')->call('saveCustomer')
            ->assertHasErrors('customerName')->assertSee('Ya existe un cliente de licencias con ese nombre.')->assertSet('showCustomerForm', true);
    }

    public function test_list_permission_cannot_create_or_rename_customers(): void
    {
        $this->permissions(['licbtn01']);
        $this->fakeApi();
        Livewire::test(Licenses::class)->call('createCustomer')->assertForbidden();
        Livewire::test(Licenses::class)->call('editCustomer', self::DNDEV)->assertForbidden();
        Http::assertNotSent(fn ($request) => in_array($request->method(), ['POST', 'PUT'], true));
    }

    public function test_can_reassign_license_to_another_customer(): void
    {
        $this->permissions(['licbtn01', 'licbtn03']);
        $this->fakeApi();
        Livewire::test(Licenses::class)->call('selectCustomer', self::DNDEV)->call('edit', self::LICENSE)->set('customerId', self::OTHER)->call('save')
            ->assertHasNoErrors()->assertDontSee('DNDEV workstation')->call('selectCustomer', self::OTHER)
            ->assertSee('Cliente: Other company')->assertSee('DNDEV workstation');
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/'.self::LICENSE)
            && $request['customer_id'] === self::OTHER && ! array_key_exists('product', $request->data()));
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'cfg'], 'vistas_l' => [[
            'vistai' => ['codigo' => 'lic'], 'accionesi' => array_map(fn ($code) => ['codigo' => $code], $actions),
        ]]]]);
    }

    private function fakeApi(int $otherLicenses = 1): void
    {
        $customers = [['id' => self::DNDEV, 'name' => 'DNDEV'], ['id' => self::OTHER, 'name' => 'Other company']];
        $license = ['id' => self::LICENSE, 'customer_id' => self::DNDEV, 'label' => 'DNDEV workstation', 'product' => 'shpos',
            'term_months' => 12, 'key_suffix' => '12345678', 'seats' => 1, 'used_seats' => 0, 'enabled' => true, 'expires_at' => '2030-01-01 12:00:00'];
        $licenses = [$license];
        for ($i = 1; $i <= $otherLicenses; $i++) {
            $licenses[] = array_replace($license, ['id' => '30000000-0000-4000-8000-'.sprintf('%012d', $i), 'customer_id' => self::OTHER, 'label' => 'Other workstation '.$i]);
        }
        Http::fake(['api.example.test/api/config/licenses*' => function ($request) use (&$customers, &$licenses) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            if ($path === '/api/config/licenses/generation-code') {
                return Http::response(['code' => 200, 'data' => ['challenge_id' => '30000000-0000-4000-8000-000000000001']]);
            }
            $page = max(1, (int) ($query['page'] ?? 1));
            if (str_starts_with($path, '/api/config/licenses/customers')) {
                if ($request->method() === 'GET') {
                    $rows = array_map(function ($customer) use ($licenses) {
                        $customer['license_count'] = count(array_filter($licenses, fn ($license) => $license['customer_id'] === $customer['id']));

                        return $customer;
                    }, $customers);
                    if ($query['search'] ?? '') {
                        $rows = array_values(array_filter($rows, fn ($customer) => str_contains(strtolower($customer['name']), strtolower($query['search']))));
                    }

                    return Http::response(['code' => 200, 'data' => array_slice($rows, ($page - 1) * 10, 10), 'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil(count($rows) / 10)), 'total' => count($rows)]]);
                }
                $name = trim($request['name']);
                $id = $request->method() === 'POST' ? self::NEW_CUSTOMER : basename($path);
                if (array_filter($customers, fn ($customer) => strtolower($customer['name']) === strtolower($name) && $customer['id'] !== $id)) {
                    return Http::response(['code' => 422, 'message' => 'Revisa los datos.', 'errors' => ['name' => ['Ya existe un cliente de licencias con ese nombre.']]], 422);
                }
                $customers = array_values(array_filter($customers, fn ($customer) => $customer['id'] !== $id));
                $customers[] = ['id' => $id, 'name' => $name];

                return Http::response(['code' => 200, 'data' => ['id' => $id, 'name' => $name]]);
            }
            if ($request->method() === 'POST') {
                return Http::response(['code' => 200, 'data' => ['id' => self::LICENSE, 'license_key' => 'SHD-SYNTHETIC']]);
            }
            if ($request->method() === 'PUT') {
                foreach ($licenses as &$item) {
                    if ($item['id'] === basename($path)) {
                        $item = array_replace($item, $request->data());
                    }
                }
                unset($item);

                return Http::response(['code' => 200, 'data' => null]);
            }
            $rows = array_values(array_filter($licenses, fn ($license) => empty($query['customer_id']) || $license['customer_id'] === $query['customer_id']));
            foreach ($rows as &$row) {
                $row['customer_name'] = collect($customers)->firstWhere('id', $row['customer_id'])['name'];
            }
            unset($row);

            return Http::response(['code' => 200, 'data' => array_slice($rows, ($page - 1) * 20, 20), 'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil(count($rows) / 20)), 'total' => count($rows)]]);
        }]);
    }
}
