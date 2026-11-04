<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\Users;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.user_path' => '/config/user',
            'services.shadmin_api.work_hours_path' => '/config/horas-lab',
            'services.shadmin_api.permission_path' => '/config/permisos',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.device_name' => 'DESKTOP-TEST',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->allUserManagementPermissions());
        Http::preventStrayRequests();
    }

    public function test_component_lists_users_without_loading_form_catalogs(): void
    {
        $this->fakeCatalogsAndUsers();

        Livewire::test(Users::class)
            ->assertSee('Ana López')
            ->assertSee('alopez')
            ->assertSee('ADMINISTRADOR')
            ->assertSee('ACTIVO')
            ->assertSee('INACTIVO')
            ->assertSee('bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300', false)
            ->assertSee('data-test="users-table-container"', false);

        Http::assertNotSent(fn (Request $request): bool => in_array($request->url(), [
            'https://api.example.test/api/config/user/roles',
            'https://api.example.test/api/config/user/estados',
        ], true));
    }

    public function test_component_loads_form_catalogs_when_opening_create(): void
    {
        $this->fakeCatalogsAndUsers();

        Livewire::test(Users::class)
            ->assertSet('roles', [])
            ->assertSet('states', [])
            ->call('openCreate')
            ->assertSet('roles', [])
            ->assertSet('states', [])
            ->assertSet('showFormModal', true);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/user/roles');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/user/estados');
    }

    public function test_component_creates_a_user(): void
    {
        $this->fakeCatalogsAndUsers();

        Http::fake([
            'api.example.test/api/config/user/crear' => Http::response([
                'message' => 'Usuario creado correctamente',
                'code' => 201,
                'data' => ['id' => 8],
            ], 201),
        ]);

        Livewire::test(Users::class)
            ->call('openCreate')
            ->set('nombre', 'Carlos Ruiz')
            ->set('name', 'cruiz')
            ->set('email', 'carlos@example.test')
            ->set('rol', '1')
            ->set('estado', '1')
            ->set('password', 'secret123')
            ->set('passwordConfirmation', 'secret123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/user/crear'
            && $request->data() === [
                'nombre' => 'Carlos Ruiz',
                'name' => hash_hmac('sha256', 'cruiz', 'test-secret'),
                'email' => 'carlos@example.test',
                'rol' => ['id' => 1],
                'estado' => ['id' => 1],
                'password' => hash_hmac('sha256', 'secret123', 'test-secret'),
                'password_confirmation' => hash_hmac('sha256', 'secret123', 'test-secret'),
            ]);
    }

    public function test_component_requires_password_when_creating_a_user(): void
    {
        $this->fakeCatalogsAndUsers();

        Livewire::test(Users::class)
            ->call('openCreate')
            ->set('nombre', 'Carlos Ruiz')
            ->set('name', 'cruiz')
            ->set('email', 'carlos@example.test')
            ->set('rol', '1')
            ->set('estado', '1')
            ->call('save')
            ->assertHasErrors(['password', 'passwordConfirmation']);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/user/crear');
    }

    public function test_component_only_requires_role_and_state_when_updating_a_user(): void
    {
        $this->fakeCatalogsAndUsers();

        Http::fake([
            'api.example.test/api/config/user/actualizar/7' => Http::response([
                'message' => 'Usuario actualizado correctamente',
                'code' => 200,
                'data' => null,
            ]),
        ]);

        Livewire::test(Users::class)
            ->set('editingId', 7)
            ->set('nombre', '')
            ->set('name', '')
            ->set('email', '')
            ->set('password', '')
            ->set('passwordConfirmation', '')
            ->set('rol', '1')
            ->set('estado', '1')
            ->call('save')
            ->assertHasNoErrors(['nombre', 'name', 'email', 'password', 'passwordConfirmation'])
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/user/actualizar/7'
            && $request['rol'] === ['id' => 1]
            && $request['estado'] === ['id' => 1]);
    }

    public function test_component_dispatches_an_alert_when_the_api_rejects_creation(): void
    {
        $this->fakeCatalogsAndUsers();

        Http::fake([
            'api.example.test/api/config/user/crear' => Http::response([
                'message' => 'El usuario ya existe.',
                'code' => 422,
                'data' => null,
            ], 422),
        ]);

        Livewire::test(Users::class)
            ->call('openCreate')
            ->set('nombre', 'Carlos Ruiz')
            ->set('name', 'cruiz')
            ->set('email', 'carlos@example.test')
            ->set('rol', '1')
            ->set('estado', '1')
            ->set('password', 'secret123')
            ->set('passwordConfirmation', 'secret123')
            ->call('save')
            ->assertDispatched('sweetalert:alert')
            ->assertSet('showFormModal', true);
    }

    public function test_component_creates_work_hours_for_a_user_without_a_schedule(): void
    {
        $this->fakeCatalogsAndUsers();

        Http::fake([
            'api.example.test/api/config/horas-lab/find/7' => Http::response([
                'message' => 'El usuario no tiene una configuración de horas.',
                'code' => 404,
                'data' => null,
            ], 404),
            'api.example.test/api/config/horas-lab/save' => Http::response([
                'message' => 'Configuración de horas creada correctamente.',
                'code' => 201,
                'data' => [
                    'id' => 15,
                    'horas_lab' => 8,
                    'horas_lunch' => 60,
                ],
            ], 201),
        ]);

        Livewire::test(Users::class)
            ->assertSee('data-test="manage-work-hours-button"', false)
            ->call('openWorkHours', 7)
            ->assertSet('showWorkHoursModal', true)
            ->assertSet('workHoursUserName', 'Ana López')
            ->assertSet('workHoursId', null)
            ->assertSee('Horario laboral de Ana López')
            ->set('workHours', '8')
            ->set('lunchHours', '60')
            ->call('saveWorkHours')
            ->assertHasNoErrors()
            ->assertSet('showWorkHoursModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/horas-lab/save'
            && $request->data() === [
                'usuario' => ['id' => 7],
                'horas_lab' => 8,
                'horas_lunch' => 60,
            ]);
    }

    public function test_component_loads_and_updates_existing_work_hours(): void
    {
        $this->fakeCatalogsAndUsers();

        Http::fake([
            'api.example.test/api/config/horas-lab/find/7' => Http::response([
                'message' => 'Configuración de horas consultada.',
                'code' => 200,
                'data' => [
                    'id' => 15,
                    'usuario' => ['id' => 7, 'nombre' => 'Ana López'],
                    'horas_lab' => 8,
                    'horas_lunch' => 60,
                ],
            ]),
            'api.example.test/api/config/horas-lab/save' => Http::response([
                'message' => 'Configuración de horas actualizada correctamente.',
                'code' => 200,
                'data' => [
                    'id' => 15,
                    'horas_lab' => 9,
                    'horas_lunch' => 90,
                ],
            ]),
        ]);

        Livewire::test(Users::class)
            ->call('openWorkHours', 7)
            ->assertSet('showWorkHoursModal', true)
            ->assertSet('workHoursId', 15)
            ->assertSet('workHours', '8')
            ->assertSet('lunchHours', '60')
            ->set('workHours', '9')
            ->set('lunchHours', '90')
            ->call('saveWorkHours')
            ->assertHasNoErrors()
            ->assertSet('showWorkHoursModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/horas-lab/save'
            && $request->data() === [
                'usuario' => ['id' => 7],
                'horas_lab' => 9,
                'horas_lunch' => 90,
            ]);
    }

    public function test_component_validates_work_and_lunch_hours_before_saving(): void
    {
        $this->fakeCatalogsAndUsers();

        Http::fake([
            'api.example.test/api/config/horas-lab/find/7' => Http::response([
                'message' => 'El usuario no tiene una configuración de horas.',
                'code' => 404,
                'data' => null,
            ], 404),
        ]);

        Livewire::test(Users::class)
            ->call('openWorkHours', 7)
            ->set('workHours', '25')
            ->set('lunchHours', '0')
            ->call('saveWorkHours')
            ->assertHasErrors(['workHours', 'lunchHours'])
            ->set('workHours', '8')
            ->set('lunchHours', '161')
            ->call('saveWorkHours')
            ->assertHasNoErrors(['workHours'])
            ->assertHasErrors(['lunchHours'])
            ->assertSet('showWorkHoursModal', true);

        Http::assertNotSent(fn (Request $request): bool => in_array(
            $request->url(),
            [
                'https://api.example.test/api/config/horas-lab/save',
                'https://api.example.test/api/config/horas-lab/save',
            ],
            true,
        ));
    }

    public function test_component_loads_permissions_and_dependent_catalogs(): void
    {
        $this->fakeCatalogsAndUsers();
        $this->fakePermissions();

        Livewire::test(Users::class)
            ->call('openPermissions', 7)
            ->assertSet('showPermissionsModal', true)
            ->assertSet('permissionsUserId', 7)
            ->assertSet('permissionTotal', 23)
            ->assertSet('permissionPage', 1)
            ->assertSet('permissionLastPage', 3)
            ->assertSee('Permisos de Ana López')
            ->assertSee('w-[calc(100vw-2rem)] min-w-0 max-w-[76rem]', false)
            ->assertSee('data-test="permissions-table-container"', false)
            ->assertSee('overflow-x-auto px-4 pb-4 pt-2 sm:px-6', false)
            ->assertSee('Página 1 de 3')
            ->assertSee('CONFIG')
            ->assertSee('USUARIOS')
            ->assertSee('CREAR')
            ->assertSee('PERMANENTE')
            ->call('rememberSelectorChoice', 'permission-modules', '2')->set('permissionModule', '2')
            ->assertSet('permissionViews', [])
            ->call('rememberSelectorChoice', 'permission-views', '5')->set('permissionView', '5')
            ->assertSet('permissionActions', []);
    }

    public function test_component_creates_and_deletes_user_permissions(): void
    {
        $this->fakeCatalogsAndUsers();
        $this->fakePermissions();

        $component = Livewire::test(Users::class)
            ->call('openPermissions', 7)
            ->call('rememberSelectorChoice', 'permission-modules', '2')->set('permissionModule', '2')
            ->call('rememberSelectorChoice', 'permission-views', '5')->set('permissionView', '5')
            ->call('rememberSelectorChoice', 'permission-actions', '9')->set('permissionAction', '9')
            ->call('rememberSelectorChoice', 'permission-times', '1')->set('permissionTimeType', '1')
            ->call('savePermission')
            ->assertHasNoErrors()
            ->assertDispatched('sweetalert:toast')
            ->assertSet('permissionModule', '2')
            ->assertSet('permissionView', '5')
            ->assertSet('permissionAction', '9')
            ->assertSet('permissionTimeType', '1')
            ->assertSet('permissionViews.0.label', 'USUARIOS')
            ->assertSet('permissionActions.0.label', 'CREAR')
            ->call('confirmPermissionDelete', 41)
            ->assertSet('showPermissionDeleteModal', true)
            ->call('deletePermission')
            ->assertSet('showPermissionDeleteModal', false)
            ->assertDispatched('sweetalert:toast');

        $component->assertSet('showPermissionsModal', true);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/permisos/crearpermiso'
            && $request->data() === [
                'usuario' => ['id' => 7],
                'modulo' => ['id' => 2],
                'vista' => ['id' => 5],
                'actionvista' => ['id' => 9],
                'tipo_tiempo' => ['id' => 1],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.example.test/api/config/permisos/eliminarpermiso/41');
    }

    public function test_changing_a_cached_module_does_not_reinitialize_the_alpine_selector(): void
    {
        $this->fakeCatalogsAndUsers();
        Http::fake([
            '*/config/permisos/listarpermisos/7*' => Http::response([
                'code' => 200, 'data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]),
            '*/selectors/permission-modules*' => function (Request $request) {
                $id = (string) $request['selected'];
                $label = $id === '2' ? 'CONFIG' : 'FARMA';

                return Http::response([
                    'code' => 200,
                    'data' => [['id' => $id, 'label' => $label, 'record' => ['id' => $id, 'nombre' => $label, 'label' => $label]]],
                    'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
                ]);
            },
        ]);

        $component = Livewire::test(Users::class)->call('openPermissions', 7);
        $initialization = function () use ($component): string {
            $this->assertSame(1, preg_match('/x-data="([^"]+)"[^>]*data-remote-select="permission-modules"/s', $component->html(), $matches));

            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        };
        $original = $initialization();

        $component->call('rememberSelectorChoice', 'permission-modules', '2')->set('permissionModule', '2')
            ->assertSet('permissionModule', '2')->assertSet('permissionModules.0.label', 'CONFIG');
        $this->assertSame($original, $initialization());

        // The validation response arrives before Alpine commits the newly clicked module.
        $component->call('rememberSelectorChoice', 'permission-modules', '4')
            ->assertSet('permissionModule', '2')->assertSet('permissionModules.0.label', 'FARMA');
        $this->assertSame($original, $initialization());

        $component->set('permissionModule', '4')
            ->assertSet('permissionModule', '4')->assertSet('permissionView', '')->assertSet('permissionAction', '');
        $this->assertSame($original, $initialization());
    }

    public function test_component_navigates_server_paginated_permissions(): void
    {
        $this->fakeCatalogsAndUsers();

        Http::fake([
            'api.example.test/api/config/permisos/listarpermisos/7*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    [
                        'id' => 42,
                        'modulo' => ['nombre' => 'VENTAS'],
                        'vista' => ['nombre' => 'PEDIDOS'],
                        'actionvista' => ['nombre' => 'EDITAR'],
                        'tipo_tiempo' => ['nombre' => 'TEMPORAL'],
                    ],
                ],
                'meta' => [
                    'total' => 23,
                    'current_page' => 2,
                    'last_page' => 3,
                ],
            ]),
        ]);

        Livewire::test(Users::class)
            ->set('permissionsUserId', 7)
            ->set('permissionPage', 1)
            ->set('permissionLastPage', 3)
            ->call('nextPermissionPage')
            ->assertSet('permissionPage', 2)
            ->assertSee('Página 2 de 3')
            ->assertSee('VENTAS')
            ->assertSee('PEDIDOS');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/permisos/listarpermisos/7?page=2');
    }

    public function test_component_redirects_to_login_when_the_api_reports_an_expired_session(): void
    {
        Http::fake([
            'api.example.test/api/config/user/listar' => Http::response([
                'message' => 'Sesion expirada',
                'code' => 200,
                'data' => null,
            ]),
        ]);

        Livewire::test(Users::class)
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session('auth.api.token'));
    }

    private function fakeCatalogsAndUsers(): void
    {
        Http::fake([
            'api.example.test/api/config/user/roles' => Http::response([
                'message' => 'Roles encontrados',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ADMINISTRADOR'],
                ],
            ]),
            'api.example.test/api/config/user/estados' => Http::response([
                'message' => 'Estados encontrados',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ACTIVO'],
                    ['id' => 2, 'descripcion' => 'INACTIVO'],
                ],
            ]),
            'api.example.test/api/config/user/listar' => Http::response([
                'message' => 'Usuarios encontrados',
                'code' => 200,
                'data' => [
                    [
                        'id' => 7,
                        'nombre' => 'Ana López',
                        'name' => 'alopez',
                        'email' => 'ana@example.test',
                        'rol' => ['id' => 1, 'descripcion' => 'ADMINISTRADOR'],
                        'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                    ],
                    [
                        'id' => 8,
                        'nombre' => 'Luis Pérez',
                        'name' => 'lperez',
                        'email' => 'luis@example.test',
                        'rol' => ['id' => 1, 'descripcion' => 'ADMINISTRADOR'],
                        'estado' => ['id' => 2, 'descripcion' => 'INACTIVO'],
                    ],
                ],
            ]),
        ]);
    }

    private function fakePermissions(): void
    {
        $this->fakeSelectorChoice('permission-modules', ['id' => 2, 'nombre' => 'CONFIG']);
        $this->fakeSelectorChoice('permission-views', ['id' => 5, 'nombre' => 'USUARIOS']);
        $this->fakeSelectorChoice('permission-actions', ['id' => 9, 'nombre' => 'CREAR']);
        $this->fakeSelectorChoice('permission-times', ['id' => 1, 'nombre' => 'PERMANENTE']);

        Http::fake([
            'api.example.test/api/config/permisos/listarpermisos/7*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    [
                        'id' => 41,
                        'modulo' => ['nombre' => 'CONFIG'],
                        'vista' => ['nombre' => 'USUARIOS'],
                        'actionvista' => ['nombre' => 'CREAR'],
                        'tipo_tiempo' => ['nombre' => 'PERMANENTE'],
                    ],
                ],
                'meta' => [
                    'total' => 23,
                    'current_page' => 1,
                    'last_page' => 3,
                ],
            ]),
            'api.example.test/api/config/permisos/listarmodulos' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 2, 'nombre' => 'CONFIG'],
                ],
            ]),
            'api.example.test/api/config/permisos/listarVistas/2' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 5, 'nombre' => 'USUARIOS'],
                ],
            ]),
            'api.example.test/api/config/permisos/listarAcciones/5' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 9, 'nombre' => 'CREAR'],
                ],
            ]),
            'api.example.test/api/config/permisos/tipoTiempo' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'nombre' => 'PERMANENTE'],
                ],
            ]),
            'api.example.test/api/config/permisos/crearpermiso' => Http::response([
                'message' => 'Permiso creado exitosamente',
                'code' => 201,
                'data' => true,
            ], 201),
            'api.example.test/api/config/permisos/eliminarpermiso/41' => Http::response([
                'message' => 'Permiso eliminado exitosamente',
                'code' => 200,
                'data' => true,
            ]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allUserManagementPermissions(): array
    {
        return [
            [
                'modulo_l' => ['codigo' => 'cfg'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'usr'],
                        'accionesi' => collect([
                            'usrbnt01',
                            'usrbnt02',
                            'usrbnt03',
                            'usrbnt04',
                            'usrbnt05',
                            'usrbtn06',
                            'usrbtn07',
                            'usrbtn08',
                            'usrbtn09',
                            'usrbtn10',
                        ])->map(fn (string $code): array => ['codigo' => $code])->all(),
                    ],
                    [
                        'vistai' => ['codigo' => 'prm'],
                        'accionesi' => collect([
                            'prmbtn08',
                            'prmbtn09',
                            'prmbtn10',
                            'prmbtn11',
                            'prmbtn12',
                            'prmbtn13',
                            'prmbtn14',
                        ])->map(fn (string $code): array => ['codigo' => $code])->all(),
                    ],
                ],
            ],
        ];
    }
}
