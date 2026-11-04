<?php

namespace Tests\Feature\Auth;

use App\Livewire\Config\Users;
use App\Models\User;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ApiPermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.user_path' => '/config/user',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_resolves_module_view_and_action_codes_from_the_api_session(): void
    {
        $this->putPermissions(['usrbnt02']);

        $permissions = app(CurrentUserPermissions::class);

        $this->assertTrue($permissions->allows('config.module'));
        $this->assertTrue($permissions->allows('users.view'));
        $this->assertTrue($permissions->allows('users.list'));
        $this->assertFalse($permissions->allows('users.create'));
        $this->assertFalse($permissions->allows('permissions.list'));
    }

    public function test_users_route_is_forbidden_without_the_list_action(): void
    {
        $this->putPermissions(['usrbnt01']);

        $this->get(route('config.users'))->assertForbidden();
    }

    public function test_menu_and_user_actions_are_hidden_when_the_session_does_not_allow_them(): void
    {
        $this->putPermissions(['usrbnt02']);
        $this->fakeUserList();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Config')
            ->assertSee('Usuarios');

        $this->get(route('config.users'))->assertOk();

        Livewire::test(Users::class)
            ->assertSee('Ana López')
            ->assertDontSee('data-test="create-user-button"', false)
            ->assertDontSee('data-test="edit-user-button"', false)
            ->assertDontSee('data-test="delete-user-button"', false)
            ->assertDontSee('data-test="manage-work-hours-button"', false)
            ->assertDontSee('data-test="manage-permissions-button"', false)
            ->call('openCreate')
            ->assertForbidden();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/api/config/user/listar');
        Http::assertNotSent(fn ($request): bool => in_array($request->url(), [
            'https://api.example.test/api/config/user/roles',
            'https://api.example.test/api/config/user/estados',
        ], true));
    }

    public function test_config_menu_is_hidden_without_the_config_module(): void
    {
        session()->put('auth.api.spermisos', []);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-test="config-accordion"', false);
    }

    public function test_future_module_codes_are_ready_without_rendering_dead_links(): void
    {
        session()->put('auth.api.spermisos', [
            [
                'modulo_l' => ['codigo' => 'frm'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'drg'],
                        'accionesi' => [
                            ['codigo' => 'drgbnt01'],
                        ],
                    ],
                ],
            ],
        ]);

        $permissions = app(CurrentUserPermissions::class);

        $this->assertTrue($permissions->hasModule('frm'));
        $this->assertTrue($permissions->hasView('frm', 'drg'));
        $this->assertTrue($permissions->hasAction('frm', 'drg', 'drgbnt01'));
        $this->assertSame([], $permissions->navigation());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-test="permission-module-frm"', false);
    }

    public function test_catalog_contains_the_known_future_modules_and_views(): void
    {
        $catalog = config('api_permissions.catalog');

        $this->assertSame(
            ['pos', 'aht', 'cfg', 'srts', 'rpt', 'frm'],
            array_keys($catalog),
        );
        $this->assertSame(['dcp', 'cpp', 'usp', 'rsp', 'cop', 'clp'], array_keys($catalog['pos']['views']));
        $this->assertSame('pos.usuarios', $catalog['pos']['views']['usp']['route']);
        $this->assertSame('pos.roles', $catalog['pos']['views']['rsp']['route']);
        $this->assertNull(config('api_permissions.abilities.usuariospos.key'));
        $this->assertArrayHasKey('ssn', $catalog['aht']['views']);
        $this->assertArrayHasKey('eswl', $catalog['aht']['views']);
        $this->assertSame(['aht', 'eswl', 'eswlbtn01'], config('api_permissions.abilities.worklunch.work'));
        $this->assertSame(['aht', 'eswl', 'eswlbtn02'], config('api_permissions.abilities.worklunch.lunch'));
        $this->assertArrayHasKey('usr', $catalog['cfg']['views']);
        $this->assertSame('config.desktop-releases', $catalog['cfg']['views']['upd']['route']);
        $this->assertSame(['cfg', 'upd', 'updbtn06'], config('api_permissions.abilities.desktop-releases.stable'));
        $this->assertContains('usrbtn10', $catalog['cfg']['views']['usr']['actions']);
        $this->assertSame(['cfg', 'usr', 'usrbtn08'], config('api_permissions.abilities.users.work-hours'));
        $this->assertSame(['cfg', 'usr', 'usrbtn09'], config('api_permissions.abilities.users.create-work-hours'));
        $this->assertSame(['cfg', 'usr', 'usrbtn10'], config('api_permissions.abilities.users.update-work-hours'));
        $this->assertArrayHasKey('tks', $catalog['srts']['views']);
        $this->assertArrayHasKey('rptstr', $catalog['rpt']['views']);
        $this->assertSame('reportes', $catalog['rpt']['icon']);
        $this->assertSame('reportes.sorteos', $catalog['rpt']['views']['rptstr']['route']);
        $this->assertSame(
            ['rpt', 'rptstr', 'rptstrbtn01'],
            config('api_permissions.abilities.reports.draws.filter'),
        );
        $this->assertSame(
            ['rpt', 'rptstr', 'rptstrbtn09'],
            config('api_permissions.abilities.reports.draws.list-draws'),
        );
        $this->assertSame(
            ['rpt', 'rptstr', 'rptstrbtn10'],
            config('api_permissions.abilities.reports.draws.export'),
        );
        $this->assertArrayHasKey('drg', $catalog['frm']['views']);
        $this->assertSame('sorteo', $catalog['srts']['icon']);
        $this->assertContains('drgbnt05', $catalog['frm']['views']['drg']['actions']);
        $this->assertSame('config.modules', $catalog['cfg']['views']['mdl']['route']);
        $this->assertSame(['cfg', 'mdl', 'mdlbtn02'], config('api_permissions.abilities.modules.list'));
        $this->assertSame('config.views', $catalog['cfg']['views']['vs']['route']);
        $this->assertContains('vsbtn11', $catalog['cfg']['views']['vs']['actions']);
        $this->assertSame(['cfg', 'vs', 'vsbtn06'], config('api_permissions.abilities.views.create-action'));
        $this->assertSame('config.stocks', $catalog['cfg']['views']['stk']['route']);
        $this->assertSame(['cfg', 'stk', 'stkbnt02'], config('api_permissions.abilities.stocks.list'));
        $this->assertSame(['cfg', 'stk', 'stkbtn05'], config('api_permissions.abilities.stocks.delete'));
        $this->assertSame(['frm', 'cmp', 'cmpbtn08'], config('api_permissions.abilities.purchases.report'));
        $this->assertSame(['frm', 'cmp', 'cmpbtn09'], config('api_permissions.abilities.purchases.kardex'));
        $this->assertSame('farma.lotes', $catalog['frm']['views']['lts']['route']);
        $this->assertSame(['frm', 'lts', 'ltsbtn01'], config('api_permissions.abilities.lots.list'));
        $this->assertSame(['frm', 'lts', 'ltsbtn02'], config('api_permissions.abilities.lots.detail'));
        $this->assertSame('farma.distribucion', $catalog['frm']['views']['dst']['route']);
        $this->assertSame(['frm', 'dst', 'dstbtn01'], config('api_permissions.abilities.distribution.list'));
        $this->assertSame(['frm', 'dst', 'dstbtn02'], config('api_permissions.abilities.distribution.detail'));
        $this->assertSame(['frm', 'dst', 'dstbtn03'], config('api_permissions.abilities.distribution.save'));
        $this->assertSame('config.devices', $catalog['cfg']['views']['dvc']['route']);
        $this->assertSame(['cfg', 'dvc', 'dvcbtn02'], config('api_permissions.abilities.devices.list'));
        $this->assertSame(['cfg', 'dvc', 'dvcbtn07'], config('api_permissions.abilities.devices.stocks'));
        $this->assertSame('sorteos.tickets', $catalog['srts']['views']['tks']['route']);
        $this->assertSame(['srts', 'tks', 'tksbnt01'], config('api_permissions.abilities.tickets.list'));
        $this->assertSame(['srts', 'tks', 'tksbnt11'], config('api_permissions.abilities.tickets.copies'));
        $this->assertSame('sorteos.draws', $catalog['srts']['views']['strl']['route']);
        $this->assertSame(['srts', 'strl', 'strlbnt02'], config('api_permissions.abilities.draws.list'));
        $this->assertSame(['srts', 'strl', 'strlbnt06'], config('api_permissions.abilities.draws.states'));
    }

    public function test_modules_route_is_forbidden_without_the_module_list_action(): void
    {
        session()->put('auth.api.spermisos', [
            [
                'modulo_l' => ['codigo' => 'cfg'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'mdl'],
                        'accionesi' => [
                            ['codigo' => 'mdlbtn01'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('config.modules'))->assertForbidden();
    }

    public function test_views_route_is_forbidden_without_the_view_list_action(): void
    {
        session()->put('auth.api.spermisos', [
            [
                'modulo_l' => ['codigo' => 'cfg'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'vs'],
                        'accionesi' => [
                            ['codigo' => 'vsbtn01'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('config.views'))->assertForbidden();
    }

    public function test_stocks_route_is_forbidden_without_the_stock_list_action(): void
    {
        session()->put('auth.api.spermisos', [
            [
                'modulo_l' => ['codigo' => 'cfg'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'stk'],
                        'accionesi' => [
                            ['codigo' => 'stkbnt01'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('config.stocks'))->assertForbidden();
    }

    public function test_devices_route_is_forbidden_without_the_device_list_action(): void
    {
        session()->put('auth.api.spermisos', [
            [
                'modulo_l' => ['codigo' => 'cfg'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'dvc'],
                        'accionesi' => [
                            ['codigo' => 'dvcbtn01'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('config.devices'))->assertForbidden();
    }

    public function test_draws_route_is_forbidden_without_the_draw_list_action(): void
    {
        session()->put('auth.api.spermisos', [
            [
                'modulo_l' => ['codigo' => 'srts'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'strl'],
                        'accionesi' => [
                            ['codigo' => 'strlbnt01'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('sorteos.draws'))->assertForbidden();
    }

    /**
     * @param  array<int, string>  $actions
     */
    private function putPermissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [
            [
                'modulo_l' => ['codigo' => 'CFG'],
                'vistas_l' => [
                    [
                        'vistai' => ['codigo' => 'USR'],
                        'accionesi' => collect($actions)
                            ->map(fn (string $code): array => ['codigo' => strtoupper($code)])
                            ->all(),
                    ],
                ],
            ],
        ]);
    }

    private function fakeUserList(): void
    {
        Http::fake([
            'api.example.test/api/config/user/listar' => Http::response([
                'message' => 'Usuarios encontrados',
                'code' => 200,
                'data' => [
                    [
                        'id' => 7,
                        'nombre' => 'Ana López',
                        'name' => 'alopez',
                        'email' => 'ana@example.test',
                        'rol' => 1,
                        'estado' => 1,
                    ],
                ],
            ]),
        ]);
    }
}
