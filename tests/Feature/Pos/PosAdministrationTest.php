<?php

namespace Tests\Feature\Pos;

use App\Exceptions\ApiOperationException;
use App\Livewire\Pos\Administracion;
use App\Models\User;
use App\Services\Api\PosManagementApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PosAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        $this->permissions(range(1, 6), range(1, 5));
        $this->fakeApi();
    }

    public function test_sales_report_permissions_from_api_are_selectable_individually(): void
    {
        $this->fakeApi([
            'api.example.test/api/pos/permisos/listarmodulos' => Http::response(['code' => 200, 'data' => [['id' => 31, 'nombre' => 'Administración']]]),
            'api.example.test/api/pos/permisos/listarVistas/31' => Http::response(['code' => 200, 'data' => [['id' => 32, 'nombre' => 'Reportes · Reporte de ventas']]]),
            'api.example.test/api/pos/permisos/listarAcciones/32' => Http::response(['code' => 200, 'data' => [['id' => 33, 'nombre' => 'Ver reporte de ventas'], ['id' => 34, 'nombre' => 'Filtrar reporte de ventas']]]),
        ]);
        foreach (['33', '34'] as $action) {
            Livewire::test(Administracion::class)->call('openPermissions', 4)
                ->assertSee('data-remote-select="pos-permission-modules"', false)->call('rememberSelectorChoice', 'pos-permission-modules', '31')->set('permissionModule', '31')->assertSee('data-remote-select="pos-permission-views"', false)
                ->call('rememberSelectorChoice', 'pos-permission-views', '32')->set('permissionView', '32')->assertSee('data-remote-select="pos-permission-actions"', false)
                ->call('rememberSelectorChoice', 'pos-permission-actions', $action)->set('permissionAction', $action)->call('rememberSelectorChoice', 'pos-permission-times', '1')->set('permissionTime', '1')->call('savePermission')->assertHasNoErrors();
        }
        foreach ([33, 34] as $action) {
            Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/pos/permisos/crearpermiso')
                && $request->hasHeader('Action-Btn', 'uspbtn04') && $request['usuario'] === ['id' => 4]
                && $request['modulo'] === ['id' => 31] && $request['vista'] === ['id' => 32]
                && $request['actionvista'] === ['id' => $action]);
        }
    }

    public function test_pages_and_navigation_include_both_pos_resources(): void
    {
        $this->get(route('pos.usuarios'))->assertOk()->assertSee('Usuarios POS')->assertSee('Operador de prueba')->assertDontSee('Clave pública por stock')->assertSee('todos los stocks')->assertSee('Cajero de prueba');
        $this->get(route('pos.roles'))->assertOk()->assertSee('Roles POS')->assertSee('Cajero de prueba');
        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="permission-view-pos-usp"', false)->assertSee('data-test="permission-view-pos-rsp"', false);
    }

    public function test_local_http_creates_user_without_retaining_password_in_snapshot(): void
    {
        $this->app->instance('env', 'local');
        config(['services.shadmin_api.base_url' => 'http://api.example.test/api']);
        $component = Livewire::test(Administracion::class)->call('openCreate')
            ->set('nombre', ' <b>Operador nuevo</b> ')->set('vendedor', ' <b>TEST vendedor</b> ')->set('usuario', ' TEST.Operator ')->call('rememberSelectorChoice', 'pos-roles', '7')->set('rolId', '7')
            ->call('save', 'Test!6', 'Test!6')
            ->assertHasNoErrors()->assertSet('showFormModal', false)->assertSet('usuario', '')->assertSet('pendingId', null)
            ->assertDispatched('sweetalert:toast');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'http://api.example.test/api/pos/usuarios/crear'
            && $request->hasHeader('Action-Btn', 'uspbtn02') && $request->hasHeader('Idempotency-Key')
            && ! array_key_exists('stock_id', $request->data()) && $request['rol_id'] === 7 && $request['nombre'] === 'Operador nuevo'
            && $request['vendedor'] === 'TEST vendedor' && $request['usuario'] === 'test.operator' && $request['password'] === 'Test!6');
        $this->assertArrayNotHasKey('password', $component->snapshot['data']);
        $this->assertStringNotContainsString('Test!6', json_encode($component->snapshot));
        $this->assertStringNotContainsString('wire:model="password"', $component->html());
    }

    public function test_edit_metadata_preserves_credentials_and_can_reset_password_explicitly(): void
    {
        Livewire::test(Administracion::class)->call('openEdit', 4)->call('rememberSelectorChoice', 'pos-roles', '7')->assertSet('usuario', '')
            ->assertSet('vendedor', 'TEST vendedor')->set('vendedor', 'TEST vendedor actualizado')->set('nombre', 'Operador actualizado')->call('save')->assertHasNoErrors()->assertSet('showFormModal', false)
            ->call('openEdit', 4)->call('rememberSelectorChoice', 'pos-roles', '7')->call('save', 'Reset6', 'Reset6')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request->hasHeader('Action-Btn', 'uspbtn04')
            && $request['vendedor'] === 'TEST vendedor actualizado' && $request['nombre'] === 'Operador actualizado' && ! array_key_exists('usuario', $request->data()) && ! array_key_exists('password', $request->data()));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && ($request->data()['password'] ?? null) === 'Reset6');
    }

    public function test_user_is_deactivated_and_not_physically_deleted(): void
    {
        Livewire::test(Administracion::class)->call('confirmDeactivate', 4)->assertSet('deactivatingName', 'Operador de prueba')
            ->call('deactivate')->assertHasNoErrors()->assertSet('showDeactivateModal', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/pos/usuarios/desactivar/4') && $request->hasHeader('Action-Btn', 'uspbtn05'));
    }

    public function test_roles_define_level_only_and_permissions_belong_to_users(): void
    {
        Livewire::test(Administracion::class, ['resource' => 'roles'])->call('openCreate')->assertDontSee('Permisos operativos')
            ->set('nombre', 'TEST nivel')->set('nivel', 2)->call('save')->assertHasNoErrors()
            ->call('openEdit', 7)->set('nivel', 8)->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Action-Btn', 'rspbtn02') && $request['nivel'] === 2 && ! array_key_exists('permisos', $request->data()));
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Action-Btn', 'rspbtn04') && $request['nivel'] === 8);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/pos/roles/permisos'));
        Livewire::test(Administracion::class)->call('openPermissions', 4)->assertSet('permissionUserId', 4)
            ->call('rememberSelectorChoice', 'pos-permission-modules', '1')->set('permissionModule', '1')->call('rememberSelectorChoice', 'pos-permission-views', '2')->set('permissionView', '2')->call('rememberSelectorChoice', 'pos-permission-actions', '3')->set('permissionAction', '3')->call('rememberSelectorChoice', 'pos-permission-times', '1')->set('permissionTime', '1')
            ->call('savePermission')->assertHasNoErrors()->call('deletePermission', 9)->assertHasNoErrors()->call('closePermissions')->assertSet('permissionUserId', null);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/pos/permisos/crearpermiso') && $request->hasHeader('Action-Btn', 'uspbtn04')
            && $request['usuario'] === ['id' => 4] && $request['modulo'] === ['id' => 1] && $request['vista'] === ['id' => 2] && $request['actionvista'] === ['id' => 3] && $request['tipo_tiempo'] === ['id' => 1]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/pos/permisos/eliminarpermiso/9') && $request->hasHeader('Action-Btn', 'uspbtn04'));
    }

    public function test_user_permissions_are_paginated_in_groups_of_ten_and_reset_when_reopened(): void
    {
        $this->fakeApi([
            'api.example.test/api/pos/permisos/listarpermisos/4' => Http::response(['code' => 200, 'data' => $this->permissionRecords(23)]),
        ]);

        $component = Livewire::test(Administracion::class)->call('openPermissions', 4)
            ->assertSet('permissionPage', 1)->assertSee('Página 1 de 3')
            ->assertViewHas('paginatedPermissions', fn (array $items): bool => count($items) === 10)
            ->assertSee('Permiso 01')->assertSee('Permiso 10')->assertDontSee('Permiso 11');
        $requestCount = count(Http::recorded());
        $component->call('goToPermissionsPage', 2)->assertSet('permissionPage', 2)->assertSet('page', 1)
            ->assertViewHas('paginatedPermissions', fn (array $items): bool => count($items) === 10)
            ->assertSee('Permiso 11')->assertSee('Permiso 20')->assertDontSee('Permiso 10')->assertDontSee('Permiso 21');
        $component->call('goToPermissionsPage', 3)->assertSee('Permiso 21')->assertSee('Permiso 23')->assertDontSee('Permiso 20')
            ->assertViewHas('paginatedPermissions', fn (array $items): bool => count($items) === 3)
            ->call('goToPermissionsPage', 4)->assertSet('permissionPage', 3)
            ->call('goToPermissionsPage', 0)->assertSet('permissionPage', 3)
            ->call('goToPermissionsPage', 2)->assertSee('Permiso 11');
        Http::assertSentCount($requestCount);

        $component->call('closePermissions')->assertSet('permissionPage', 1)
            ->call('openPermissions', 4)->assertSet('permissionPage', 1)->assertSee('Permiso 01')->assertDontSee('Permiso 11');
    }

    public function test_deleting_the_last_permission_on_a_page_returns_to_the_previous_page(): void
    {
        $this->fakeApi([
            'api.example.test/api/pos/permisos/listarpermisos/4' => Http::sequence()
                ->push(['code' => 200, 'data' => $this->permissionRecords(11)])
                ->push(['code' => 200, 'data' => $this->permissionRecords(10)]),
            'api.example.test/api/pos/permisos/eliminarpermiso/11' => Http::response(['code' => 200, 'message' => 'Permiso eliminado.']),
        ]);

        Livewire::test(Administracion::class)->call('openPermissions', 4)
            ->call('goToPermissionsPage', 2)->assertSee('Permiso 11')->assertDontSee('Permiso 10')
            ->call('deletePermission', 11)->assertHasNoErrors()->assertSet('permissionPage', 1)
            ->assertSee('Permiso 01')->assertSee('Permiso 10')->assertDontSee('Permiso 11')->assertDontSee('goToPermissionsPage', false);
    }

    public function test_permissions_pagination_is_hidden_when_no_permissions_are_assigned(): void
    {
        $this->fakeApi([
            'api.example.test/api/pos/permisos/listarpermisos/4' => Http::response(['code' => 200, 'data' => []]),
        ]);

        Livewire::test(Administracion::class)->call('openPermissions', 4)
            ->assertSet('permissionPage', 1)->assertSee('Sin permisos POS asignados.')
            ->assertDontSee('goToPermissionsPage', false);
    }

    public function test_transfer_views_offer_independent_actions_and_clear_previous_selection(): void
    {
        $this->fakeApi([
            'api.example.test/api/pos/permisos/listarmodulos' => Http::response(['code' => 200, 'data' => [['id' => 10, 'nombre' => 'Transferencias']]]),
            'api.example.test/api/pos/permisos/listarVistas/10' => Http::response(['code' => 200, 'data' => [
                ['id' => 21, 'nombre' => 'Enviadas'], ['id' => 22, 'nombre' => 'Recibidas'],
            ]]),
            'api.example.test/api/pos/permisos/listarAcciones/21' => Http::response(['code' => 200, 'data' => [
                ['id' => 31, 'nombre' => 'Ver enviadas'], ['id' => 32, 'nombre' => 'Crear y enviar'],
            ]]),
            'api.example.test/api/pos/permisos/listarAcciones/22' => Http::response(['code' => 200, 'data' => [
                ['id' => 41, 'nombre' => 'Ver recibidas'], ['id' => 42, 'nombre' => 'Recibir'],
            ]]),
        ]);
        $component = Livewire::test(Administracion::class)->call('openPermissions', 4)
            ->call('rememberSelectorChoice', 'pos-permission-modules', '10')->set('permissionModule', '10')->assertSee('data-remote-select="pos-permission-views"', false)
            ->call('rememberSelectorChoice', 'pos-permission-views', '21')->set('permissionView', '21')->assertSee('data-remote-select="pos-permission-actions"', false)->call('rememberSelectorChoice', 'pos-permission-actions', '32')->set('permissionAction', '32')
            ->call('rememberSelectorChoice', 'pos-permission-views', '22')->set('permissionView', '22')->assertSet('permissionAction', '')->assertDontSee('Crear y enviar')
            ->assertSet('permissionActions', [])->call('rememberSelectorChoice', 'pos-permission-actions', '42')->set('permissionAction', '42')->call('rememberSelectorChoice', 'pos-permission-times', '1')->set('permissionTime', '1')
            ->call('savePermission')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/pos/permisos/crearpermiso')
            && $request['vista'] === ['id' => 22] && $request['actionvista'] === ['id' => 42]);
    }

    public function test_permission_lookup_and_mutations_require_user_portal_access(): void
    {
        $this->permissions([1], []);
        Livewire::test(Administracion::class)->assertDontSee('permissions-usuariospos')->call('openPermissions', 4)->assertForbidden();
        $this->permissions([1, 3], []);
        Livewire::test(Administracion::class)->call('openPermissions', 4)->assertSee('Permisos POS')->assertDontSee('Asignar permiso')
            ->call('savePermission')->assertForbidden();
        Livewire::test(Administracion::class)->call('openPermissions', 4)->call('deletePermission', 9)->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }

    public function test_list_only_permissions_hide_and_block_mutations_and_key_registration(): void
    {
        $this->permissions([1], [1]);
        Livewire::test(Administracion::class)->assertDontSee('data-test="create-usuariospos"', false)
            ->assertDontSee('data-test="edit-usuariospos"', false)->assertDontSee('data-test="deactivate-usuariospos"', false)
            ->assertDontSee('data-test="register-pos-key"', false)->call('openCreate')->assertForbidden();
        Livewire::test(Administracion::class)->call('save', 'Synthetic-password-2026', 'Synthetic-password-2026')->assertForbidden();
        Livewire::test(Administracion::class)->call('openEdit', 4)->assertForbidden();
        Livewire::test(Administracion::class)->call('confirmDeactivate', 4)->assertForbidden();
        Livewire::test(Administracion::class, ['resource' => 'roles'])->call('save')->assertForbidden();
        $this->permissions([], []);
        $this->get(route('pos.usuarios'))->assertForbidden();
        $this->get(route('pos.roles'))->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }

    public function test_missing_lookup_permission_does_not_call_stock_or_role_endpoints(): void
    {
        $this->permissions([1, 2], [], false);
        Livewire::test(Administracion::class)->call('openCreate')->assertHasErrors(['form']);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/config/stock') || str_contains($request->url(), '/pos/roles'));
    }

    public function test_password_validation_and_permission_allowlist_prevent_invalid_writes(): void
    {
        $component = Livewire::test(Administracion::class)->call('openCreate')->set('nombre', 'TEST')->set('vendedor', 'TEST vendedor')->set('usuario', 'test')->call('rememberSelectorChoice', 'pos-roles', '7')->set('rolId', '7');
        $component->call('save', 'short', 'short')->assertHasErrors(['password'])->assertSee('Usa al menos 6 caracteres.');
        $component->call('save', 'Synthetic-password-2026', 'different')->assertHasErrors(['password']);
        Livewire::test(Administracion::class, ['resource' => 'roles'])->call('openCreate')->set('nombre', 'TEST')->set('nivel', -1)->call('save')->assertHasErrors(['nivel']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }

    public function test_user_creation_does_not_require_stock_permissions_or_keys(): void
    {
        $this->permissions([1, 2], [1], false);
        Livewire::test(Administracion::class)->call('openCreate')->assertDontSee('Clave pública por stock')
            ->set('nombre', 'TEST global')->set('vendedor', 'TEST vendedor')->set('usuario', 'test.global')->call('rememberSelectorChoice', 'pos-roles', '7')->set('rolId', '7')
            ->call('save', 'Synthetic-password-2026', 'Synthetic-password-2026')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/pos/usuarios/crear') && ! array_key_exists('stock_id', $request->data()));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/config/stock') || str_contains($request->url(), '/clave-publica'));
    }

    public function test_api_validation_is_displayed_and_allows_correcting_the_form(): void
    {
        $this->fakeApi(['api.example.test/api/pos/usuarios/crear' => Http::sequence()->push(['code' => 422, 'data' => ['errors' => ['usuario' => ['El usuario POS ya está registrado.']]]], 422)->push(['code' => 201, 'message' => 'ok'], 201)]);
        Livewire::test(Administracion::class)->call('openCreate')->set('nombre', 'TEST')->set('vendedor', 'TEST vendedor')->set('usuario', 'test')->call('rememberSelectorChoice', 'pos-roles', '7')->set('rolId', '7')
            ->call('save', 'Synthetic-password-2026', 'Synthetic-password-2026')->assertHasErrors(['usuario'])->assertSet('showFormModal', true)->assertSet('pendingId', null)
            ->call('save', 'Synthetic-password-2026', 'Synthetic-password-2026')->assertHasNoErrors();
    }

    public function test_uncertain_result_reuses_request_id_and_blocks_changed_payload(): void
    {
        $keys = [];
        $this->fakeApi(['api.example.test/api/pos/usuarios/crear' => function (Request $request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];
            if (count($keys) === 1) {
                throw new ConnectionException('Synthetic timeout');
            }

            return Http::response(['code' => 201, 'message' => 'ok'], 201);
        }]);
        $component = Livewire::test(Administracion::class)->call('openCreate')->set('nombre', 'TEST')->set('vendedor', 'TEST vendedor')->set('usuario', 'test')->call('rememberSelectorChoice', 'pos-roles', '7')->set('rolId', '7')
            ->call('save', 'Synthetic-password-2026', 'Synthetic-password-2026')->assertHasErrors(['form'])->assertSet('showFormModal', true);
        $id = $component->get('pendingId');
        $component->call('closeForm')->assertSet('showFormModal', true)->assertSet('pendingId', $id)
            ->set('nombre', 'CHANGED')->call('save', 'Synthetic-password-2026', 'Synthetic-password-2026')->assertHasErrors(['form'])->assertSet('pendingId', $id)
            ->set('nombre', 'TEST')->call('save', 'Synthetic-password-2026', 'Synthetic-password-2026')->assertHasNoErrors()->assertSet('pendingId', null);
        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
    }

    public function test_credentials_require_https_in_production(): void
    {
        $this->app->instance('env', 'production');
        config(['services.shadmin_api.base_url' => 'http://api.example.test/api']);
        $this->fakeApi(['api.example.test/api/pos/usuarios/listar*' => Http::response(['code' => 200, 'data' => []])]);
        $this->expectException(ApiOperationException::class);
        app(PosManagementApiService::class)->save('usuarios', null, ['password' => 'Synthetic-password-2026'], 'test-request');
    }

    public function test_testing_environment_allows_http_credentials(): void
    {
        $this->app->instance('env', 'testing');
        config(['services.shadmin_api.base_url' => 'http://api.example.test/api']);

        $message = app(PosManagementApiService::class)->save('usuarios', null, ['usuario' => 'test.operator', 'password' => 'Test!6'], 'test-request');

        $this->assertSame('Usuario creado.', $message);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'http://api.example.test/api/pos/usuarios/crear');
    }

    public function test_production_environment_allows_https_credentials(): void
    {
        $this->app->instance('env', 'production');

        $message = app(PosManagementApiService::class)->save('usuarios', null, ['usuario' => 'test.operator', 'password' => 'Test!6'], 'test-request');

        $this->assertSame('Usuario creado.', $message);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://api.example.test/api/pos/usuarios/crear');
    }

    public function test_expired_api_session_redirects_to_login(): void
    {
        $this->fakeApi(['api.example.test/api/pos/usuarios/listar*' => Http::response(['code' => 401, 'message' => 'Sesión expirada.'], 401)]);
        Livewire::test(Administracion::class)->assertRedirect(route('login'));
        $this->assertNull(session('auth.api.token'));
    }

    public function test_role_choices_are_loaded_on_demand_without_enumerating_pages(): void
    {
        $this->fakeApi(['api.example.test/api/pos/roles/listar*' => function (Request $request) {
            $page = (int) $request['page'];

            return Http::response(['code' => 200, 'data' => [['id' => $page, 'nombre' => 'Rol '.$page, 'activo' => $page !== 1]], 'meta' => ['total' => 2, 'current_page' => $page, 'last_page' => 2]]);
        }]);
        $component = Livewire::test(Administracion::class)->call('openCreate');
        $this->assertSame([], $component->get('roles'));
        $component->assertSee('data-remote-select="pos-roles"', false);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/pos/roles/listar'));
    }

    /** @param array<string, mixed> $overrides */
    private function fakeApi(array $overrides = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(array_replace([
            'api.example.test/api/pos/usuarios/listar*' => Http::response(['code' => 200, 'data' => [$this->userRecord()], 'meta' => ['total' => 31, 'current_page' => 1, 'last_page' => 2]]),
            'api.example.test/api/pos/usuarios/buscar/4' => Http::response(['code' => 200, 'data' => $this->userRecord()]),
            'api.example.test/api/pos/roles/listar*' => Http::response(['code' => 200, 'data' => [$this->roleRecord()], 'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1]]),
            'api.example.test/api/pos/roles/buscar/7' => Http::response(['code' => 200, 'data' => $this->roleRecord()]),
            'api.example.test/api/pos/permisos/listarpermisos/4' => Http::response(['code' => 200, 'data' => [['id' => 9, 'usuario' => 4, 'modulo' => ['id' => 1, 'nombre' => 'TEST módulo'], 'vista' => ['id' => 2, 'nombre' => 'TEST vista'], 'actionvista' => ['id' => 3, 'nombre' => 'Guardar configuración'], 'created_at' => '2026-10-05']]]),
            'api.example.test/api/pos/permisos/listarmodulos' => Http::response(['code' => 200, 'data' => [['id' => 1, 'nombre' => 'TEST módulo']]]),
            'api.example.test/api/pos/permisos/listarVistas/1' => Http::response(['code' => 200, 'data' => [['id' => 2, 'nombre' => 'TEST vista']]]),
            'api.example.test/api/pos/permisos/listarAcciones/2' => Http::response(['code' => 200, 'data' => [['id' => 3, 'nombre' => 'Guardar configuración']]]),
            'api.example.test/api/pos/permisos/tipoTiempo' => Http::response(['code' => 200, 'data' => [['id' => 1, 'nombre' => 'Permanente']]]),
            'api.example.test/api/pos/permisos/crearpermiso' => Http::response(['code' => 201, 'message' => 'Permiso creado.'], 201),
            'api.example.test/api/pos/permisos/eliminarpermiso/9' => Http::response(['code' => 200, 'message' => 'Permiso eliminado.']),
            'api.example.test/api/config/stock/listar*' => Http::response(['code' => 200, 'data' => [['id' => 1, 'descripcion' => 'Stock de prueba']], 'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1]]),
            'api.example.test/api/pos/usuarios/crear' => Http::response(['code' => 201, 'message' => 'Usuario creado.'], 201),
            'api.example.test/api/pos/usuarios/actualizar/4' => Http::response(['code' => 200, 'message' => 'Usuario actualizado.']),
            'api.example.test/api/pos/usuarios/desactivar/4' => Http::response(['code' => 200, 'message' => 'Usuario desactivado.']),
            'api.example.test/api/pos/roles/crear' => Http::response(['code' => 201, 'message' => 'Rol creado.'], 201),
            'api.example.test/api/pos/roles/actualizar/7' => Http::response(['code' => 200, 'message' => 'Rol actualizado.']),
            'api.example.test/api/pos/roles/desactivar/7' => Http::response(['code' => 200, 'message' => 'Rol desactivado.']),
        ], $overrides));
        $this->fakeSelectorsFromLegacy([
            'pos-roles' => 'pos/roles/listar', 'pos-permission-modules' => 'pos/permisos/listarmodulos',
            'pos-permission-views' => 'pos/permisos/listarVistas/{parent}', 'pos-permission-actions' => 'pos/permisos/listarAcciones/{parent}',
            'pos-permission-times' => 'pos/permisos/tipoTiempo',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function permissionRecords(int $count): array
    {
        return array_map(fn (int $id): array => [
            'id' => $id,
            'usuario' => 4,
            'modulo' => ['id' => 1, 'nombre' => 'TEST módulo'],
            'vista' => ['id' => 2, 'nombre' => 'TEST vista'],
            'actionvista' => ['id' => $id, 'nombre' => sprintf('Permiso %02d', $id)],
            'created_at' => '2026-10-05',
        ], range(1, $count));
    }

    /** @return array<string, mixed> */
    private function userRecord(): array
    {
        return ['id' => 4, 'nombre' => 'Operador de prueba', 'vendedor' => 'TEST vendedor', 'rol_id' => 7, 'rol_nombre' => 'Cajero de prueba', 'activo' => true];
    }

    /** @return array<string, mixed> */
    private function roleRecord(): array
    {
        return ['id' => 7, 'nombre' => 'Cajero de prueba', 'nivel' => 2, 'activo' => true];
    }

    /** @param array<int, int> $users @param array<int, int> $roles */
    private function permissions(array $users, array $roles, bool $stocks = true): void
    {
        session()->put('auth.api.spermisos', [
            ['modulo_l' => ['codigo' => 'pos'], 'vistas_l' => [
                ['vistai' => ['codigo' => 'usp'], 'accionesi' => array_map(fn (int $i): array => ['codigo' => 'uspbtn0'.$i], $users)],
                ['vistai' => ['codigo' => 'rsp'], 'accionesi' => array_map(fn (int $i): array => ['codigo' => 'rspbtn0'.$i], $roles)],
            ]],
            ['modulo_l' => ['codigo' => 'cfg'], 'vistas_l' => [
                ['vistai' => ['codigo' => 'stk'], 'accionesi' => $stocks ? [['codigo' => 'stkbnt02']] : []],
            ]],
        ]);
    }
}
