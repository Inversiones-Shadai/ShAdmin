<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\Clientes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ClienteposManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        $this->permissions(range(1, 5));
        Http::fake([
            'api.example.test/api/pos/clientepos/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 4, 'nombre' => 'Ana López', 'rtn' => '08011999001234', 'telefono' => null, 'direccion' => 'Centro']],
                'meta' => ['total' => 31, 'current_page' => 1, 'last_page' => 2],
            ]),
            'api.example.test/api/pos/clientepos/buscar/4' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['id' => 4, 'nombre' => 'Ana López', 'rtn' => '08011999001234', 'telefono' => null, 'direccion' => 'Centro']]),
            'api.example.test/api/pos/clientepos/actualizar/4' => Http::response(['message' => 'Cliente actualizado.', 'code' => 200, 'data' => true]),
            'api.example.test/api/pos/clientepos/eliminar/4' => Http::response(['message' => 'Cliente eliminado.', 'code' => 200, 'data' => true]),
        ]);
    }

    public function test_the_page_and_navigation_render_with_permission(): void
    {
        $this->get(route('pos.clientepos'))->assertOk()->assertSee('Ana López')->assertSee('Clientes POS');
        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="permission-view-pos-clp"', false);
    }

    public function test_crud_sanitizes_data_and_sends_each_action_header(): void
    {
        Http::fake(['api.example.test/api/pos/clientepos/crear' => Http::response(['message' => 'Cliente creado.', 'code' => 201, 'data' => true], 201)]);
        Livewire::test(Clientes::class)
            ->call('openCreate')->set('nombre', ' <b>Ana López</b> ')->set('tipo_documento', 'RTN')->set('rtn', '0801-1999-001234')->call('save')
            ->assertHasNoErrors()->assertSet('showFormModal', false)->assertDispatched('sweetalert:toast')
            ->call('openEdit', 4)->assertSet('telefono', '')->set('direccion', 'Barrio nuevo')->call('save')
            ->assertHasNoErrors()->call('confirmDelete', 4)->call('delete')->assertSet('showDeleteModal', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->hasHeader('Action-Btn', 'clpbtn02') && $request['nombre'] === 'Ana López' && $request['rtn'] === '08011999001234');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request->hasHeader('Action-Btn', 'clpbtn04') && $request['id'] === 4 && $request['direccion'] === 'Barrio nuevo');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->hasHeader('Action-Btn', 'clpbtn05'));
    }

    public function test_search_is_sent_to_the_api_and_resets_pagination(): void
    {
        Livewire::test(Clientes::class)->call('goToPage', 2)->set('search', '08011999001234');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Action-Btn', 'clpbtn01') && $request['page'] === 1 && $request['search'] === '08011999001234');
    }

    public function test_list_only_permission_hides_and_denies_mutations(): void
    {
        $this->permissions([1]);
        Livewire::test(Clientes::class)->assertDontSee('data-test="create-clientepos"', false)->assertDontSee('data-test="edit-clientepos"', false)->assertDontSee('data-test="delete-clientepos"', false)->call('openCreate')->assertForbidden();
        Livewire::test(Clientes::class)->set('nombre', 'Ana')->call('save')->assertForbidden();
        Livewire::test(Clientes::class)->call('openEdit', 4)->assertForbidden();
        Livewire::test(Clientes::class)->call('confirmDelete', 4)->assertForbidden();
        $this->permissions([]);
        $this->get(route('pos.clientepos'))->assertForbidden();
    }

    public function test_api_validation_errors_stay_in_the_form(): void
    {
        Http::fake(['api.example.test/api/pos/clientepos/crear' => Http::response(['message' => 'Datos inválidos.', 'code' => 422, 'data' => ['errors' => ['rtn' => ['El RTN ya existe.']]]], 422)]);
        Livewire::test(Clientes::class)->call('openCreate')->set('nombre', 'Ana')->set('tipo_documento', 'RTN')->set('rtn', '08011999001234')->call('save')->assertHasErrors(['rtn'])->assertSet('showFormModal', true);
    }

    public function test_identity_form_requires_birth_date_and_sends_exact_metadata(): void
    {
        Http::fake(['api.example.test/api/pos/clientepos/crear' => Http::response(['message' => 'Creado', 'code' => 201, 'data' => true], 201)]);
        Livewire::test(Clientes::class)->call('openCreate')->set('nombre', 'TEST paciente')->set('tipo_documento', 'IDENTIDAD')
            ->set('rtn', '0000-1999-00001')->call('save')->assertHasErrors(['fecha_nacimiento'])
            ->set('fecha_nacimiento', '1946-10-12')->call('save')->assertHasNoErrors()->assertSet('showFormModal', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['tipo_documento'] === 'IDENTIDAD'
            && $request['rtn'] === '0000199900001' && $request['fecha_nacimiento'] === '1946-10-12');
    }

    public function test_wrong_document_type_or_future_birth_date_does_not_send_a_write(): void
    {
        Livewire::test(Clientes::class)->call('openCreate')->set('nombre', 'TEST paciente')->set('rtn', '0000199900001')->call('save')
            ->assertHasErrors(['tipo_documento'])->set('tipo_documento', 'RTN')->call('save')->assertHasErrors(['rtn'])
            ->set('tipo_documento', 'IDENTIDAD')->set('fecha_nacimiento', '2099-01-01')->call('save')->assertHasErrors(['fecha_nacimiento']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    /** @param array<int, int> $actions */
    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'pos'],
            'vistas_l' => [['vistai' => ['codigo' => 'clp'], 'accionesi' => array_map(fn (int $number): array => ['codigo' => 'clpbtn0'.$number], $actions)]],
        ]]);
    }
}
