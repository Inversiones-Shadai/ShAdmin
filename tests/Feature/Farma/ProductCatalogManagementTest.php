<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\ProductCatalogManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.product_catalog_path' => '/farma/productos',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_manages_a_basic_catalog_and_sanitizes_text(): void
    {
        session()->put('auth.api.spermisos', $this->permissions(['pct']));
        Http::fake([
            'api.example.test/api/farma/productos/categorias/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 7, 'nombre' => 'Medicamentos']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/productos/categorias/crear' => Http::response([
                'message' => 'Registro creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/categorias/buscar/7' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => ['id' => 7, 'nombre' => 'Medicamentos'],
            ]),
            'api.example.test/api/farma/productos/categorias/actualizar/7' => Http::response([
                'message' => 'Registro actualizado correctamente.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/categorias/eliminar/7' => Http::response([
                'message' => 'Registro eliminado correctamente.', 'code' => 200, 'data' => true,
            ]),
        ]);

        Livewire::test(ProductCatalogManager::class, ['catalog' => 'categorias'])
            ->assertSee('Medicamentos')
            ->assertSee('data-test="product-catalog-table-container"', false)
            ->assertSee('w-full', false)
            ->assertSee('min-w-48', false)
            ->assertSee('w-28', false)
            ->call('openCreate')
            ->set('form.nombre', ' <b>Analgésicos</b> ')
            ->call('save')
            ->assertHasNoErrors()
            ->call('openEdit', 7)
            ->assertSet('form.nombre', 'Medicamentos')
            ->set('form.nombre', 'Medicamentos generales')
            ->call('save')
            ->assertHasNoErrors()
            ->call('confirmDelete', 7)
            ->call('delete')
            ->assertSet('showDeleteModal', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->data()['nombre'] === 'Analgésicos'
            && $request->hasHeader('Action-Btn', 'pctbtn02'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->data()['id'] === 7
            && $request->hasHeader('Action-Btn', 'pctbtn04'));
    }

    public function test_active_ingredients_page_can_be_navigated_to_after_creating_a_concentration(): void
    {
        session()->put('auth.api.spermisos', $this->permissions(['cnc', 'pac']));
        Http::fake([
            'api.example.test/api/farma/productos/productos/catalogos/concentraciones/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 7, 'valor' => '500 mg']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/productos/productos/catalogos/concentraciones/crear' => Http::response([
                'message' => 'Registro creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/principios-activos/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [[
                    'id' => 9, 'nombre' => 'Paracetamol',
                    'concentracion_detalle' => ['id' => 7, 'valor' => '500 mg'],
                ]],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        Livewire::test(ProductCatalogManager::class, ['catalog' => 'concentraciones'])
            ->call('openCreate')
            ->set('form.valor', '500 mg')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertDispatched('sweetalert:toast');

        $this->get(route('farma.productos.principios-activos'), ['X-Livewire-Navigate' => '1'])
            ->assertOk()
            ->assertSee('Principios activos')
            ->assertSee('Paracetamol')
            ->assertSee('500 mg')
            ->assertDontSee('id="global-loader"', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/principios-activos/listar')
            && $request->hasHeader('Action-Btn', 'pacbtn01'));
    }

    public function test_family_form_loads_its_required_catalogs_with_their_codes(): void
    {
        $this->fakeFamilyPickers();
        Livewire::test(ProductCatalogManager::class, ['catalog' => 'familias'])->call('openCreate')
            ->assertSet('options', [])->assertSet('showFormModal', true)
            ->assertSee('data-remote-select="family-presentations"', false)
            ->assertSee('data-remote-select="family-administrations"', false);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/presentaciones/listar') || str_contains($request->url(), '/administraciones/listar'));
    }

    public function test_family_tables_search_and_paginate_independently_and_preserve_selection(): void
    {
        $this->fakeFamilyPickers();
        $this->fakeSelectorChoice('family-presentations', ['id' => 7, 'descripcion' => 'Presentación 7']);
        $this->fakeSelectorChoice('family-administrations', ['id' => 23, 'descripcion' => 'Vía 23']);
        Livewire::test(ProductCatalogManager::class, ['catalog' => 'familias'])->call('openCreate')
            ->set('form.descripcion', 'Familia de prueba')
            ->call('rememberSelectorChoice', 'family-presentations', '7')->set('form.presentacion', 7)
            ->call('rememberSelectorChoice', 'family-administrations', '23')->set('form.administracion', 23)
            ->assertCount('options.presentacion', 1)->assertCount('options.administracion', 1)
            ->assertSet('form.descripcion', 'Familia de prueba')->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/familias/crear') && $request['presentacion'] === 7 && $request['administracion'] === 23);
    }

    public function test_family_tables_validate_required_selection_and_preserve_edit_values(): void
    {
        $this->fakeFamilyPickers();
        $this->fakeSelectorChoice('family-presentations', ['id' => 1, 'descripcion' => 'Presentación 1']);
        $this->fakeSelectorChoice('family-administrations', ['id' => 2, 'descripcion' => 'Vía 2']);
        Livewire::test(ProductCatalogManager::class, ['catalog' => 'familias'])->call('openCreate')
            ->set('form.descripcion', 'Familia de prueba')->call('save')
            ->assertHasErrors(['form.presentacion' => 'required', 'form.administracion' => 'required'])
            ->call('rememberSelectorChoice', 'family-presentations', '1')->set('form.presentacion', 1)
            ->call('rememberSelectorChoice', 'family-administrations', '2')->set('form.administracion', 2)
            ->call('save')->assertHasNoErrors()->assertSet('showFormModal', false);
    }

    private function fakeFamilyPickers(): void
    {
        session()->put('auth.api.spermisos', $this->permissions(['fam', 'fpr', 'fad']));
        $responses = [];

        foreach (['presentaciones' => 'Presentación', 'administraciones' => 'Vía'] as $catalog => $label) {
            $responses['api.example.test/api/farma/productos/'.$catalog.'/listar*'] = function (Request $request) use ($label) {
                $page = (int) $request['page'];

                return Http::response([
                    'message' => 'ok', 'code' => 200,
                    'data' => array_map(fn (int $id): array => ['id' => $id, 'descripcion' => "{$label} {$id}"], $page === 1 ? range(1, 20) : range(21, 23)),
                    'meta' => ['total' => 23, 'current_page' => $page, 'last_page' => 2],
                ]);
            };
        }

        Http::fake([
            ...$responses,
            'api.example.test/api/farma/productos/familias/listar*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/productos/familias/crear' => Http::response([
                'message' => 'Registro creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/familias/buscar/9' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => ['id' => 9, 'descripcion' => 'Familia de prueba', 'presentacion' => ['id' => 1], 'administracion' => ['id' => 2]],
            ]),
        ]);
    }

    public function test_concentration_picker_shows_ten_latest_options_and_searches_across_pages(): void
    {
        $this->fakeConcentrationPicker();
        $this->fakeSelectorChoice('active-concentrations', ['id' => 3914, 'valor' => '3914 mg']);
        Livewire::test(ProductCatalogManager::class, ['catalog' => 'principios-activos'])->call('openCreate')
            ->assertSet('options', [])->assertSee('data-remote-select="active-concentrations"', false)
            ->call('rememberSelectorChoice', 'active-concentrations', '3914')
            ->assertCount('options.concentracion', 1);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/concentraciones/listar'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/selectors/active-concentrations') && $request['selected'] === '3914');
    }

    public function test_active_ingredient_requires_a_concentration_and_saves_the_selected_id(): void
    {
        $this->fakeConcentrationPicker();
        $this->fakeSelectorChoice('active-concentrations', ['id' => 7, 'valor' => '7 mg']);

        Livewire::test(ProductCatalogManager::class, ['catalog' => 'principios-activos'])
            ->call('openCreate')
            ->set('form.nombre', 'Ibuprofeno')
            ->call('save')
            ->assertHasErrors(['form.concentracion' => 'required'])
            ->call('selectConcentration', 999)
            ->assertSet('form.concentracion', null)
            ->call('rememberSelectorChoice', 'active-concentrations', '7')->call('selectConcentration', 7)
            ->assertHasNoErrors('form.concentracion')
            ->set('concentrationSearch', 'sin coincidencias')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false)
            ->assertSet('concentrationSearch', '')
            ->assertSet('concentrationPage', 1)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/principios-activos/crear')
            && $request['nombre'] === 'Ibuprofeno' && $request['concentracion'] === 7);
    }

    public function test_concentration_selection_is_preserved_when_editing_and_resets_on_reopen(): void
    {
        $this->fakeConcentrationPicker();
        $this->fakeSelectorChoice('active-concentrations', ['id' => 1, 'valor' => '1 mg']);

        Livewire::test(ProductCatalogManager::class, ['catalog' => 'principios-activos'])
            ->call('openEdit', 9)
            ->assertSet('form.concentracion', 1)->call('rememberSelectorChoice', 'active-concentrations', '1')
            ->assertViewHas('selectedConcentration', fn (array $option): bool => $option['label'] === '1 mg')
            ->call('nextConcentrationPage')
            ->set('concentrationSearch', 'mg')
            ->call('closeForm')
            ->call('openCreate')
            ->assertSet('concentrationSearch', '')
            ->assertSet('concentrationPage', 1)
            ->assertSet('form.concentracion', null);
    }

    private function fakeConcentrationPicker(): void
    {
        session()->put('auth.api.spermisos', $this->permissions(['cnc', 'pac']));
        Http::fake([
            'api.example.test/api/farma/productos/productos/catalogos/concentraciones/listar*' => function (Request $request) {
                $page = (int) $request['page'];

                return Http::response([
                    'message' => 'ok', 'code' => 200,
                    'data' => array_map(fn (int $id): array => ['id' => $id, 'valor' => "{$id} mg"], $page === 1 ? range(1, 20) : range(21, 23)),
                    'meta' => ['total' => 23, 'current_page' => $page, 'last_page' => 2],
                ]);
            },
            'api.example.test/api/farma/productos/principios-activos/listar*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/productos/principios-activos/crear' => Http::response([
                'message' => 'Registro creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/principios-activos/buscar/9' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => ['id' => 9, 'nombre' => 'Ibuprofeno', 'concentracion' => 1],
            ]),
        ]);
    }

    public function test_the_eight_views_are_grouped_under_products_in_farma(): void
    {
        session()->put('auth.api.spermisos', $this->permissions([
            'pud', 'pes', 'pct', 'fpr', 'fad', 'fam', 'cnc', 'pac',
        ]));
        Http::fake([
            'api.example.test/api/farma/productos/categorias/listar*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-submenu-frm-products"', false)
            ->assertSee('data-test="permission-view-frm-pud"', false)
            ->assertSee('data-test="permission-view-frm-pac"', false);

        $this->get(route('farma.productos.categorias'))
            ->assertOk()
            ->assertSee('Categorías');
    }

    /**
     * @param  array<int, string>  $viewCodes
     * @return array<int, array<string, mixed>>
     */
    private function permissions(array $viewCodes): array
    {
        $views = [];

        foreach ($viewCodes as $viewCode) {
            $actions = config("api_permissions.catalog.frm.views.{$viewCode}.actions", []);

            if (! is_array($actions)) {
                $actions = [];
            }

            $views[] = [
                'vistai' => ['codigo' => $viewCode],
                'accionesi' => array_values(array_map(
                    fn (mixed $code): array => ['codigo' => (string) $code],
                    $actions,
                )),
            ];
        }

        return [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => $views,
        ]];
    }
}
