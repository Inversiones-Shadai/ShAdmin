<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\Views;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ViewManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.view_path' => '/config/vista',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->allViewPermissions());
        Http::preventStrayRequests();
    }

    public function test_component_lists_views_without_loading_catalogs_or_actions(): void
    {
        $this->fakeViewApi();

        Livewire::test(Views::class)
            ->assertSee('USUARIOS')
            ->assertSee('CONFIG')
            ->assertSee('usr')
            ->assertSee('ACTIVO')
            ->assertSee('INACTIVO')
            ->assertSee('Página 1 de 2')
            ->assertSee('data-test="create-view-button"', false)
            ->assertSee('data-test="edit-view-button"', false)
            ->assertSet('modules', [])
            ->assertSet('states', [])
            ->assertSet('actions', []);

        Http::assertNotSent(fn (Request $request): bool => in_array($request->url(), [
            'https://api.example.test/api/config/vista/listarModulos',
            'https://api.example.test/api/config/vista/estados',
            'https://api.example.test/api/config/vista/acciones/5',
        ], true));
    }

    public function test_component_loads_form_catalogs_and_creates_a_view(): void
    {
        $this->fakeViewApi();

        Livewire::test(Views::class)
            ->call('openCreate')
            ->assertSet('modules', [])
            ->assertSet('states', [])
            ->set('modulo', '2')
            ->set('nombre', 'PERMISOS')
            ->set('codigo', 'PRM')
            ->set('estado', '1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/vista/crear'
            && $request->data() === [
                'modulo' => ['id' => 2],
                'nombre' => 'PERMISOS',
                'codigo' => 'prm',
                'estado' => ['id' => 1],
            ]);
    }

    public function test_component_filters_views_by_module_on_demand(): void
    {
        $this->fakeViewApi();

        Livewire::test(Views::class)
            ->call('openModuleFilter')
            ->assertSet('showModuleFilter', true)
            ->assertSet('modules', [])
            ->set('filterModule', '2')
            ->assertSet('total', 1)
            ->assertSee('PERMISOS')
            ->assertDontSee('USUARIOS');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/vista/findbymodule/2?page=1');
    }

    public function test_component_finds_and_updates_only_the_view_state(): void
    {
        $this->fakeViewApi();

        Livewire::test(Views::class)
            ->call('openEdit', 5)
            ->assertSet('nombre', 'USUARIOS')
            ->assertSet('codigo', 'usr')
            ->assertSet('estado', '1')
            ->set('estado', '2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/vista/actualizar/5'
            && $request->data() === [
                'id' => 5,
                'estado' => ['id' => 2],
            ]);
    }

    public function test_component_manages_action_names_and_button_codes(): void
    {
        $this->fakeViewApi();

        Livewire::test(Views::class)
            ->call('openActions', 5)
            ->assertSet('showActionsModal', true)
            ->assertSet('actionsViewName', 'USUARIOS')
            ->assertSee('listar')
            ->assertSee('usrbnt02')
            ->set('actionName', 'crear')
            ->set('actionCode', 'USRBNT01')
            ->call('saveAction')
            ->assertHasNoErrors()
            ->assertSet('actionName', '')
            ->assertSet('actionCode', '')
            ->assertDispatched('sweetalert:toast')
            ->call('confirmActionDelete', 9)
            ->assertSet('showActionDeleteModal', true)
            ->call('deleteAction')
            ->assertSet('showActionDeleteModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/vista/createaction'
            && $request->data() === [
                'vista' => ['id' => 5],
                'nombre' => 'crear',
                'codigo' => 'usrbnt01',
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.example.test/api/config/vista/deleteaction/9');
    }

    public function test_component_deletes_a_view_and_navigates_pages(): void
    {
        $this->fakeViewApi();

        Livewire::test(Views::class)
            ->call('confirmDelete', 5)
            ->assertSet('showDeleteModal', true)
            ->call('delete')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('sweetalert:toast')
            ->call('nextPage')
            ->assertSet('page', 2)
            ->assertSee('REPORTES');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/vista/listar?page=2');
    }

    public function test_route_and_menu_are_available_with_the_list_permission(): void
    {
        $this->fakeViewApi();

        $this->get(route('config.views'))
            ->assertOk()
            ->assertSee('Vistas');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-cfg-vs"', false)
            ->assertSee('data:image/png;base64,', false);
    }

    private function fakeViewApi(): void
    {
        Http::fake([
            'api.example.test/api/config/vista/listar?*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'success',
                    'code' => 200,
                    'data' => $page === 2
                        ? [[
                            'id' => 8,
                            'modulo' => ['id' => 3, 'nombre' => 'REPORTES'],
                            'nombre' => 'REPORTE DE SORTEOS',
                            'codigo' => 'rptstr',
                            'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                        ]]
                        : [
                            [
                                'id' => 5,
                                'modulo' => ['id' => 2, 'nombre' => 'CONFIG'],
                                'nombre' => 'USUARIOS',
                                'codigo' => 'usr',
                                'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                            ],
                            [
                                'id' => 6,
                                'modulo' => ['id' => 2, 'nombre' => 'CONFIG'],
                                'nombre' => 'MÓDULOS',
                                'codigo' => 'mdl',
                                'estado' => ['id' => 2, 'descripcion' => 'INACTIVO'],
                            ],
                        ],
                    'meta' => [
                        'total' => 21,
                        'current_page' => $page,
                        'last_page' => 2,
                    ],
                ]);
            },
            'api.example.test/api/config/vista/findbymodule/2*' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [[
                    'id' => 7,
                    'modulo' => ['id' => 2, 'nombre' => 'CONFIG'],
                    'nombre' => 'PERMISOS',
                    'codigo' => 'prm',
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ]],
                'meta' => [
                    'total' => 1,
                    'current_page' => 1,
                    'last_page' => 1,
                ],
            ]),
            'api.example.test/api/config/vista/listarModulos' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    ['id' => 2, 'nombre' => 'CONFIG'],
                    ['id' => 3, 'nombre' => 'REPORTES'],
                ],
            ]),
            'api.example.test/api/config/vista/estados' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ACTIVO'],
                    ['id' => 2, 'descripcion' => 'INACTIVO'],
                ],
            ]),
            'api.example.test/api/config/vista/buscar/5' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    'id' => 5,
                    'modulo' => ['id' => 2, 'nombre' => 'CONFIG'],
                    'nombre' => 'USUARIOS',
                    'codigo' => 'usr',
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
            'api.example.test/api/config/vista/crear' => Http::response([
                'message' => 'Vista creada',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/vista/actualizar/5' => Http::response([
                'message' => 'Vista actualizada',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/vista/eliminar/5' => Http::response([
                'message' => 'Vista eliminada',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/vista/acciones/5' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    ['id' => 9, 'nombre' => 'listar', 'codigo' => 'usrbnt02'],
                ],
            ]),
            'api.example.test/api/config/vista/createaction' => Http::response([
                'message' => 'Accion creada',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/config/vista/deleteaction/9' => Http::response([
                'message' => 'Accion eliminada',
                'code' => 200,
                'data' => true,
            ]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allViewPermissions(): array
    {
        return [
            [
                'modulo_l' => ['codigo' => 'cfg'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'vs'],
                        'accionesi' => collect([
                            'vsbtn01',
                            'vsbtn02',
                            'vsbtn03',
                            'vsbtn04',
                            'vsbtn05',
                            'vsbtn06',
                            'vsbtn07',
                            'vsbtn08',
                            'vsbtn09',
                            'vsbtn10',
                        ])->map(fn (string $code): array => ['codigo' => $code])->all(),
                    ],
                ],
            ],
        ];
    }
}
