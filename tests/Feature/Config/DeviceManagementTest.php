<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\Devices;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.device_path' => '/config/device',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->allDevicePermissions());
        Http::preventStrayRequests();
    }

    public function test_component_lists_devices_without_loading_catalogs(): void
    {
        $this->fakeDevices();

        Livewire::test(Devices::class)
            ->assertSee('Caja principal')
            ->assertSee('Sucursal centro')
            ->assertSee('INACTIVO')
            ->assertSee('bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300', false)
            ->assertSee('Página 1 de 2')
            ->assertSet('total', 40)
            ->assertSet('states', [])
            ->assertSet('stocks', []);

        Http::assertNotSent(fn (Request $request): bool => in_array($request->url(), [
            'https://api.example.test/api/config/device/estados',
            'https://api.example.test/api/config/device/stocks',
        ], true));
    }

    public function test_component_uses_request_ip_when_no_device_ip_is_configured(): void
    {
        config(['services.shadmin_api.device_ip' => null]);
        $this->fakeDevices();

        Livewire::test(Devices::class)
            ->assertSet('loadError', null)
            ->assertSee('Caja principal');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'X-Device-Ip', hash_hmac('sha256', '127.0.0.1', 'test-secret'),
        ));
    }

    public function test_component_loads_catalogs_only_when_opening_create(): void
    {
        $this->fakeDevices();

        Livewire::test(Devices::class)
            ->call('openCreate')
            ->assertSet('states', [])
            ->assertSet('stocks', [])
            ->assertSet('showFormModal', true);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/device/estados');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/device/stocks');
    }

    public function test_component_creates_and_updates_a_device_with_the_api_contract(): void
    {
        $this->fakeDevices();

        Http::fake([
            'api.example.test/api/config/device/crear' => Http::response([
                'message' => 'Dispositivo creado correctamente',
                'code' => 201,
                'data' => true,
            ]),
            'api.example.test/api/config/device/buscar/8' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    'id' => 8,
                    'displayname' => 'Caja principal',
                    'stock' => ['id' => 3, 'descripcion' => 'Sucursal centro'],
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
            'api.example.test/api/config/device/actualizar/8' => Http::response([
                'message' => 'Dispositivo actualizado correctamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Devices::class)
            ->call('openCreate')
            ->set('ip', '192.168.1.20')
            ->set('ip2', '192.168.1.21')
            ->set('displayname', 'Caja secundaria')
            ->set('deviceName', 'DESKTOP-CAJA-2')
            ->set('stock', '3')
            ->set('estado', '1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('sweetalert:toast')
            ->call('openEdit', 8)
            ->assertSet('displayname', 'Caja principal')
            ->set('estado', '2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/device/crear'
            && $request->data()['displayname'] === 'Caja secundaria'
            && $request->data()['ip'] !== '192.168.1.20'
            && $request->data()['name'] !== 'DESKTOP-CAJA-2'
            && $request->data()['stock'] === ['id' => 3]
            && $request->data()['estado'] === ['id' => 1]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/device/actualizar/8'
            && $request->data() === [
                'id' => 8,
                'estado' => ['id' => 2],
                'hq_pairing_uuid' => null,
            ]);
    }

    public function test_component_deletes_and_navigates_device_pages(): void
    {
        $this->fakeDevices();

        Http::fake([
            'api.example.test/api/config/device/eliminar/8' => Http::response([
                'message' => 'Dispositivo eliminado correctamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Devices::class)
            ->call('confirmDelete', 8)
            ->assertSet('showDeleteModal', true)
            ->call('delete')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('sweetalert:toast')
            ->call('nextPage')
            ->assertSet('page', 2)
            ->assertSee('Caja secundaria');
    }

    public function test_pairing_uuid_is_displayed_searched_loaded_and_saved_without_hashing(): void
    {
        $uuid = 'fe06ef21-2588-41b0-9f42-5934316ae2c8';
        $record = ['id' => 8, 'displayname' => 'Caja principal', 'hq_pairing_uuid' => $uuid,
            'stock' => ['id' => 3, 'descripcion' => 'Sucursal centro'], 'estado' => ['id' => 1, 'descripcion' => 'ACTIVO']];
        Http::fake([
            'api.example.test/api/config/device/listar*' => Http::response(['code' => 200, 'data' => [$record]]),
            'api.example.test/api/config/device/estados' => Http::response(['code' => 200, 'data' => [
                ['id' => 1, 'descripcion' => 'ACTIVO'],
            ]]),
            'api.example.test/api/config/device/buscar/8' => Http::response(['code' => 200, 'data' => $record]),
            'api.example.test/api/config/device/actualizar/8' => Http::response(['code' => 200, 'data' => true]),
        ]);
        Livewire::test(Devices::class)->assertSee($uuid)->set('search', 'fe06ef21')->assertSee('Caja principal')
            ->call('openEdit', 8)->assertSet('hqPairingUuid', $uuid)
            ->set('hqPairingUuid', ' '.strtoupper($uuid).' ')->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->data()['hq_pairing_uuid'] === $uuid);
    }

    public function test_invalid_pairing_uuid_is_rejected_locally_and_api_duplicate_errors_are_shown(): void
    {
        $this->fakeDevices();
        Http::fake([
            'api.example.test/api/config/device/buscar/8' => Http::response(['code' => 200, 'data' => [
                'id' => 8, 'displayname' => 'Caja principal', 'estado' => ['id' => 1],
            ]]),
            'api.example.test/api/config/device/actualizar/8' => Http::response(['code' => 422,
                'data' => ['errors' => ['hq_pairing_uuid' => ['Este UUID de HQ ya está vinculado a otro dispositivo.']]],
            ], 422),
        ]);
        $component = Livewire::test(Devices::class)->call('openEdit', 8)->set('hqPairingUuid', 'invalid')
            ->call('save')->assertHasErrors(['hqPairingUuid' => 'uuid']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
        $component->set('hqPairingUuid', 'fe06ef21-2588-41b0-9f42-5934316ae2c8')->call('save')
            ->assertHasErrors('hqPairingUuid')->assertSee('Este UUID de HQ ya está vinculado a otro dispositivo.');
    }

    public function test_route_and_menu_are_available_with_the_list_permission(): void
    {
        $this->fakeDevices();

        $this->get(route('config.devices'))
            ->assertOk()
            ->assertSee('Dispositivos');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-cfg-dvc"', false)
            ->assertSee('data:image/png;base64,', false);
    }

    private function fakeDevices(): void
    {
        Http::fake([
            'api.example.test/api/config/device/listar*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'ok',
                    'code' => 200,
                    'data' => $page === 2
                        ? [[
                            'id' => 9,
                            'displayname' => 'Caja secundaria',
                            'stock' => ['id' => 3, 'descripcion' => 'Sucursal centro'],
                            'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                            'updated_at' => '2026-07-27 12:00:00',
                        ]]
                        : [[
                            'id' => 8,
                            'displayname' => 'Caja principal',
                            'stock' => ['id' => 3, 'descripcion' => 'Sucursal centro'],
                            'estado' => ['id' => 2, 'descripcion' => 'INACTIVO'],
                            'updated_at' => '2026-07-27 11:00:00',
                        ]],
                    'meta' => [
                        'total' => 40,
                        'current_page' => $page,
                        'last_page' => 2,
                    ],
                ]);
            },
            'api.example.test/api/config/device/estados' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ACTIVO'],
                    ['id' => 2, 'descripcion' => 'INACTIVO'],
                ],
            ]),
            'api.example.test/api/config/device/stocks' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 3, 'descripcion' => 'Sucursal centro'],
                ],
            ]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allDevicePermissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'cfg'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'dvc'],
                'accionesi' => collect([
                    'dvcbtn01',
                    'dvcbtn02',
                    'dvcbnt03',
                    'dvcbnt04',
                    'dvcbnt05',
                    'dvcbnt06',
                    'dvcbtn07',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
