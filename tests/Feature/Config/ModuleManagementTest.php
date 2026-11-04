<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\Modules;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.module_path' => '/config/modulo',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->allModulePermissions());
        Http::preventStrayRequests();
    }

    public function test_component_lists_paginated_modules_without_loading_states(): void
    {
        $this->fakeModules();

        Livewire::test(Modules::class)
            ->assertSee('CONFIG')
            ->assertSee('cfg')
            ->assertSee('ACTIVO')
            ->assertSee('INACTIVO')
            ->assertSee('bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300', false)
            ->assertSee('Página 1 de 2')
            ->assertSet('total', 18)
            ->assertSet('states', []);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/modulo/estados');
    }

    public function test_component_loads_states_only_when_opening_the_form(): void
    {
        $this->fakeModules();

        Livewire::test(Modules::class)
            ->call('openCreate')
            ->assertSet('states', [])
            ->assertSet('showFormModal', true);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/modulo/estados');
    }

    public function test_component_creates_a_module_with_the_api_contract(): void
    {
        $this->fakeModules();

        Http::fake([
            'api.example.test/api/config/modulo/crear' => Http::response([
                'message' => 'Modulo creado con exito',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Modules::class)
            ->call('openCreate')
            ->set('nombre', 'REPORTES')
            ->set('codigo', 'RPT')
            ->set('estado', '1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/modulo/crear'
            && $request->data() === [
                'nombre' => 'REPORTES',
                'codigo' => 'rpt',
                'estado' => ['id' => 1],
            ]);
    }

    public function test_component_finds_and_updates_only_the_module_state(): void
    {
        $this->fakeModules();

        Http::fake([
            'api.example.test/api/config/modulo/buscar/2' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    'id' => 2,
                    'nombre' => 'CONFIG',
                    'codigo' => 'cfg',
                    'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
            'api.example.test/api/config/modulo/actualizar/2' => Http::response([
                'message' => 'Modulo actualizado con exito',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Modules::class)
            ->call('openEdit', 2)
            ->assertSet('nombre', 'CONFIG')
            ->assertSet('codigo', 'cfg')
            ->assertSet('estado', '1')
            ->set('estado', '2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/modulo/actualizar/2'
            && $request->data() === [
                'id' => 2,
                'estado' => ['id' => 2],
            ]);
    }

    public function test_component_deletes_and_navigates_module_pages(): void
    {
        $this->fakeModules();

        Http::fake([
            'api.example.test/api/config/modulo/eliminar/2' => Http::response([
                'message' => 'Modulo eliminado con exito',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Modules::class)
            ->call('confirmDelete', 2)
            ->assertSet('showDeleteModal', true)
            ->call('delete')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('sweetalert:toast')
            ->call('nextPage')
            ->assertSet('page', 2)
            ->assertSee('REPORTES');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/modulo/listar?page=2');
    }

    public function test_route_and_menu_are_available_with_the_list_permission(): void
    {
        $this->fakeModules();

        $this->get(route('config.modules'))
            ->assertOk()
            ->assertSee('Módulos');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-cfg-mdl"', false)
            ->assertSee('data:image/png;base64,', false);
    }

    private function fakeModules(): void
    {
        Http::fake([
            'api.example.test/api/config/modulo/listar*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'success',
                    'code' => 200,
                    'data' => $page === 2
                        ? [[
                            'id' => 3,
                            'nombre' => 'REPORTES',
                            'codigo' => 'rpt',
                            'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                        ]]
                        : [
                            [
                                'id' => 2,
                                'nombre' => 'CONFIG',
                                'codigo' => 'cfg',
                                'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                            ],
                            [
                                'id' => 1,
                                'nombre' => 'AUTENTICACIÓN',
                                'codigo' => 'aht',
                                'estado' => ['id' => 2, 'descripcion' => 'INACTIVO'],
                            ],
                        ],
                    'meta' => [
                        'total' => 18,
                        'current_page' => $page,
                        'last_page' => 2,
                    ],
                ]);
            },
            'api.example.test/api/config/modulo/estados' => Http::response([
                'message' => 'success',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ACTIVO'],
                    ['id' => 2, 'descripcion' => 'INACTIVO'],
                ],
            ]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allModulePermissions(): array
    {
        return [
            [
                'modulo_l' => ['codigo' => 'cfg'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'mdl'],
                        'accionesi' => collect([
                            'mdlbtn01',
                            'mdlbtn02',
                            'mdlbtn03',
                            'mdlbtn04',
                            'mdlbtn05',
                            'mdlbtn06',
                        ])->map(fn (string $code): array => ['codigo' => $code])->all(),
                    ],
                ],
            ],
        ];
    }
}
