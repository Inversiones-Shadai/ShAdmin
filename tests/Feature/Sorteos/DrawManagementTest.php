<?php

namespace Tests\Feature\Sorteos;

use App\Livewire\Sorteos\Draws;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DrawManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.draw_path' => '/sorteos/sorteo',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->allDrawPermissions());
        Http::preventStrayRequests();
    }

    public function test_component_lists_draws_without_loading_states(): void
    {
        $this->fakeDraws();

        Livewire::test(Draws::class)
            ->assertSee('Sorteo Navidad')
            ->assertSee('Promoción de fin de año')
            ->assertSee('INACTIVO')
            ->assertSee('bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300', false)
            ->assertSee('Página 1 de 2')
            ->assertSet('total', 22)
            ->assertSet('states', []);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/sorteos/sorteo/estados');
    }

    public function test_component_loads_states_only_when_opening_the_form(): void
    {
        $this->fakeDraws();

        Livewire::test(Draws::class)
            ->call('openCreate')
            ->assertSet('states', [])
            ->assertSet('showFormModal', true);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/sorteos/sorteo/estados');
    }

    public function test_component_creates_and_updates_a_draw_with_the_api_contract(): void
    {
        $this->fakeDraws();

        Http::fake([
            'api.example.test/api/sorteos/sorteo/crear' => Http::response([
                'message' => 'Sorteo creado exitosamente.',
                'code' => 201,
                'data' => true,
            ]),
            'api.example.test/api/sorteos/sorteo/buscar/11' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => $this->drawData(),
            ]),
            'api.example.test/api/sorteos/sorteo/actualizar' => Http::response([
                'message' => 'Sorteo actualizado exitosamente.',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Draws::class)
            ->call('openCreate')
            ->set('nombre', 'Sorteo Verano')
            ->set('descripcion', 'Promoción de verano')
            ->set('fechaInicio', '2027-01-01')
            ->set('fechaFin', '2027-01-31')
            ->set('impresiones', '8')
            ->set('valor', '200.50')
            ->set('estado', '1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('sweetalert:toast')
            ->call('openEdit', 11)
            ->assertSet('nombre', 'Sorteo Navidad')
            ->assertSet('fechaInicio', '2026-12-01')
            ->set('descripcion', 'Promoción actualizada')
            ->set('estado', '2')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/sorteos/sorteo/crear'
            && $request->data()['fecha_inicio'] === '2027-01-01'
            && $request->data()['fecha_fin'] === '2027-01-31'
            && $request->data()['estado'] === ['id' => 1]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/sorteos/sorteo/actualizar'
            && ! array_key_exists('fecha_inicio', $request->data())
            && $request->data()['id'] === 11
            && $request->data()['estado'] === ['id' => 2]);
    }

    public function test_component_filters_draws_and_clears_the_filter(): void
    {
        $this->fakeDraws();

        Http::fake([
            'api.example.test/api/sorteos/sorteo/filter*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [$this->drawData()],
                'meta' => [
                    'total' => 1,
                    'current_page' => 1,
                    'last_page' => 1,
                ],
            ]),
        ]);

        Livewire::test(Draws::class)
            ->set('filterTerm', 'Navidad')
            ->assertSet('filterActive', true)
            ->assertSet('total', 1)
            ->assertSee('Resultados para')
            ->set('filterTerm', '')
            ->assertSet('filterActive', false)
            ->assertSet('total', 22);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/sorteos/sorteo/filter?'));
    }

    public function test_component_deletes_and_navigates_draw_pages(): void
    {
        $this->fakeDraws();

        Http::fake([
            'api.example.test/api/sorteos/sorteo/eliminar/11' => Http::response([
                'message' => 'Sorteo eliminado exitosamente.',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        Livewire::test(Draws::class)
            ->call('confirmDelete', 11)
            ->assertSet('showDeleteModal', true)
            ->call('delete')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('sweetalert:toast')
            ->call('nextPage')
            ->assertSet('page', 2)
            ->assertSee('Sorteo Verano');
    }

    public function test_route_and_sorteos_menu_are_available_with_the_list_permission(): void
    {
        $this->fakeDraws();

        $this->get(route('sorteos.draws'))
            ->assertOk()
            ->assertSee('Lista de sorteos');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-module-srts"', false)
            ->assertSee('data-test="permission-view-srts-strl"', false)
            ->assertSee('Lista de sorteos');
    }

    private function fakeDraws(): void
    {
        Http::fake([
            'api.example.test/api/sorteos/sorteo/listar*' => function (Request $request) {
                $page = (int) ($request->data()['page'] ?? 1);

                return Http::response([
                    'message' => 'ok',
                    'code' => 200,
                    'data' => $page === 2
                        ? [[
                            ...$this->drawData(),
                            'id' => 12,
                            'nombre' => 'Sorteo Verano',
                            'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
                        ]]
                        : [[
                            ...$this->drawData(),
                            'estado' => ['id' => 2, 'descripcion' => 'INACTIVO'],
                        ]],
                    'meta' => [
                        'total' => 22,
                        'current_page' => $page,
                        'last_page' => 2,
                    ],
                ]);
            },
            'api.example.test/api/sorteos/sorteo/estados' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ACTIVO'],
                    ['id' => 2, 'descripcion' => 'INACTIVO'],
                ],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function drawData(): array
    {
        return [
            'id' => 11,
            'nombre' => 'Sorteo Navidad',
            'descripcion' => 'Promoción de fin de año',
            'fecha_inicio' => '2026-12-01',
            'fecha_fin' => '2026-12-31',
            'impresiones' => 5,
            'valor' => 100.50,
            'estado' => ['id' => 1, 'descripcion' => 'ACTIVO'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allDrawPermissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'srts'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'strl'],
                'accionesi' => collect([
                    'strlbnt01',
                    'strlbnt02',
                    'strlbnt03',
                    'strlbnt04',
                    'strlbnt05',
                    'strlbnt06',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
