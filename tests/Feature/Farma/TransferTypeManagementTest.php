<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\TransferTypeManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TransferTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        $this->permissions(['tftbtn01', 'tftbtn02', 'tftbtn03', 'tftbtn04', 'tftbtn05']);
        Http::fake([
            'api.example.test/api/farma/transferencias/tipos/listar*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [['id' => 7, 'descripcion' => 'Traslado']],
                'meta' => ['total' => 2, 'current_page' => 1, 'last_page' => 2],
            ]),
            'api.example.test/api/farma/transferencias/tipos/buscar/7' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['id' => 7, 'descripcion' => 'Traslado'],
            ]),
            'api.example.test/api/farma/transferencias/tipos/crear' => Http::response([
                'message' => 'Guardado.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/transferencias/tipos/*' => Http::response([
                'message' => 'Guardado.', 'code' => 200, 'data' => true,
            ]),
        ]);
    }

    public function test_screen_and_navigation_use_the_new_permission(): void
    {
        $this->get(route('farma.transferencias.tipos'))->assertOk()->assertSee('Tipos de transferencia')->assertSee('Traslado');
        $this->get(route('dashboard'))->assertOk()->assertSee(route('farma.transferencias.tipos'), false);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Action-Btn', 'tftbtn01'));
    }

    public function test_crud_uses_its_own_action_codes_and_sanitizes_descriptions(): void
    {
        Livewire::test(TransferTypeManager::class)
            ->call('openCreate')->set('descripcion', ' <b>Devolución</b> ')->call('save')->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->call('openEdit', 7)->assertSet('descripcion', 'Traslado')
            ->set('descripcion', 'Traslado interno')->call('save')->assertHasNoErrors()
            ->call('confirmDelete', 7)->call('delete')->assertSet('showDeleteModal', false);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->data() === ['descripcion' => 'Devolución'] && $request->hasHeader('Action-Btn', 'tftbtn02'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with($request->url(), '/buscar/7') && $request->hasHeader('Action-Btn', 'tftbtn03'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->data() === ['id' => 7, 'descripcion' => 'Traslado interno'] && $request->hasHeader('Action-Btn', 'tftbtn04'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/eliminar/7') && $request->hasHeader('Action-Btn', 'tftbtn05'));
    }

    public function test_search_resets_page_and_is_sent_to_the_api(): void
    {
        Livewire::test(TransferTypeManager::class)->call('nextPage')->set('search', 'Devolución');
        Http::assertSent(fn (Request $request): bool => ($request->data()['page'] ?? null) === 2);
        Http::assertSent(fn (Request $request): bool => ($request->data()['page'] ?? null) === 1
            && ($request->data()['search'] ?? null) === 'Devolución');
    }

    public function test_empty_description_is_rejected_after_sanitizing(): void
    {
        Livewire::test(TransferTypeManager::class)->call('openCreate')->set('descripcion', ' <b> </b> ')
            ->call('save')->assertHasErrors(['descripcion' => 'required']);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_without_list_permission_the_page_and_menu_are_unavailable(): void
    {
        $this->permissions([]);
        $this->get(route('farma.transferencias.tipos'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('farma.transferencias.tipos'), false);
        Http::assertNothingSent();
    }

    public function test_list_permission_does_not_allow_mutations_or_editing(): void
    {
        $this->permissions(['tftbtn01']);
        Livewire::test(TransferTypeManager::class)->assertDontSee('wire:click="openCreate"', false)->call('openCreate')->assertForbidden();
        Livewire::test(TransferTypeManager::class)->set('descripcion', 'Intento')->call('save')->assertForbidden();
        Livewire::test(TransferTypeManager::class)->call('confirmDelete', 7)->assertForbidden();
        Livewire::test(TransferTypeManager::class)->call('delete')->assertForbidden();
        Livewire::test(TransferTypeManager::class)->call('openEdit', 7)->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET' || str_contains($request->url(), '/buscar/'));
    }

    public function test_edit_requires_search_and_update_permissions(): void
    {
        $this->permissions(['tftbtn01', 'tftbtn04']);
        Livewire::test(TransferTypeManager::class)->call('openEdit', 7)->assertForbidden();
        $this->permissions(['tftbtn01', 'tftbtn03']);
        Livewire::test(TransferTypeManager::class)->call('openEdit', 7)->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/buscar/'));
    }

    public function test_api_validation_is_displayed_in_the_form(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'api.example.test/api/farma/transferencias/tipos/listar*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/transferencias/tipos/crear' => Http::response([
                'message' => 'La descripción ya existe.', 'code' => 422,
                'data' => ['errors' => ['descripcion' => ['La descripción ya existe.']]],
            ], 422),
        ]);
        Livewire::test(TransferTypeManager::class)->call('openCreate')->set('descripcion', 'Traslado')
            ->call('save')->assertHasErrors('descripcion')->assertSet('showFormModal', true);
    }

    /** @param list<string> $actions */
    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [[
            'modulo_l' => ['codigo' => 'frm'], 'vistas_l' => [[
                'vistai' => ['codigo' => 'tft'], 'accionesi' => array_map(fn ($code): array => ['codigo' => $code], $actions),
            ]],
        ]]);
    }
}
