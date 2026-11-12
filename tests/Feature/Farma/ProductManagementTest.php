<?php

namespace Tests\Feature\Farma;

use App\Livewire\Farma\Productos;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.product_path' => '/farma/productos/producto',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');
        session()->put('auth.api.spermisos', $this->permissions());
        Http::preventStrayRequests();
    }

    public function test_products_view_is_visible_in_navigation_and_lists_records(): void
    {
        $this->fakeList();

        Livewire::test(Productos::class)
            ->assertSee('Acetaminofén 500 mg')
            ->assertSee('Laboratorio Central')
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('Presentación: Tabletas')
            ->assertSet('total', 1);

        $this->get(route('farma.productos'))
            ->assertOk()
            ->assertSee('Productos');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-test="permission-view-frm-prd"', false);
    }

    public function test_it_creates_a_child_with_its_own_permission_without_product_create_permission(): void
    {
        $this->fakeList();
        $permissions = $this->permissions();
        $permissions[0]['vistas_l'][0]['accionesi'] = [['codigo' => 'prdbtn01'], ['codigo' => 'prdbtn19']];
        session()->put('auth.api.spermisos', $permissions);
        Http::fake([
            'api.example.test/api/farma/productos/producto/crear-hijo/9' => Http::response([
                'message' => 'Producto hijo creado correctamente.', 'code' => 201, 'data' => ['codigo' => 'PRD-009H'],
            ]),
        ]);
        Livewire::test(Productos::class)
            ->assertSee('data-test="create-product-child-button"', false)
            ->call('openChild', 9)
            ->assertSet('showChildModal', true)
            ->assertSet('childCode', 'PRD-009H')
            ->assertSet('childDescription', 'Acetaminofén 500 mg')
            ->set('childDescription', '')
            ->call('createChild')
            ->assertHasErrors(['childDescription' => 'required'])
            ->set('childDescription', ' <b>Acetaminofén por tableta</b> ')
            ->call('createChild')
            ->assertHasNoErrors()
            ->assertSet('showChildModal', false)
            ->assertSet('childParentId', null)
            ->assertDispatched('sweetalert:toast');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/crear-hijo/9')
            && $request->method() === 'POST'
            && $request->hasHeader('Action-Btn', 'prdbtn19')
            && $request->data() === ['descripcion' => 'Acetaminofén por tableta']);
    }

    public function test_child_action_is_unavailable_for_children(): void
    {
        foreach ([['codigo' => 'PRDH'], ['codigo' => 'PRDh'], ['producto_padre_id' => '2']] as $overrides) {
            Http::fake(['api.example.test/api/farma/productos/producto/listar*' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => [array_replace($this->product(), $overrides)],
            ])]);
            Livewire::test(Productos::class)
                ->assertDontSee('data-test="create-product-child-button"', false)
                ->call('openChild', 9)
                ->assertSet('showChildModal', false);
        }
    }

    public function test_child_action_reports_duplicate_api_errors(): void
    {
        $this->fakeList();
        Http::fake(['api.example.test/api/farma/productos/producto/crear-hijo/9' => Http::response([
            'message' => 'Revisa los datos.', 'code' => 422, 'errors' => ['codigo' => ['Ya existe PRD-009H.']],
        ], 422)]);
        Livewire::test(Productos::class)->call('openChild', 9)->call('createChild')
            ->assertSet('showChildModal', true)->assertHasErrors('childDescription')->assertSee('Ya existe PRD-009H.')
            ->call('closeChild')->assertSet('childParentId', null);
    }

    public function test_product_create_permission_does_not_allow_child_action_on_open_or_submit(): void
    {
        $this->fakeList();
        $permissions = $this->permissions();
        $permissions[0]['vistas_l'][0]['accionesi'] = [['codigo' => 'prdbtn01'], ['codigo' => 'prdbtn02']];
        session()->put('auth.api.spermisos', $permissions);
        Livewire::test(Productos::class)
            ->assertDontSee('data-test="create-product-child-button"', false)
            ->call('openChild', 9)->assertForbidden();
        Livewire::test(Productos::class)->call('createChild')->assertForbidden();
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_it_creates_updates_and_deletes_products_with_catalog_options(): void
    {
        $this->fakeList();
        $this->fakeOptions();
        Http::fake([
            'api.example.test/api/farma/productos/producto/crear' => Http::response([
                'message' => 'Producto creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/producto/buscar/9' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => $this->product(),
            ]),
            'api.example.test/api/farma/productos/producto/actualizar/9' => Http::response([
                'message' => 'Producto actualizado correctamente.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/producto/eliminar/9' => Http::response([
                'message' => 'Producto eliminado correctamente.', 'code' => 200, 'data' => true,
            ]),
        ]);

        Livewire::test(Productos::class)
            ->call('openCreate')
            ->assertSet('showFormModal', true)
            ->set('categoria', '2')
            ->set('laboratorio', '3')
            ->set('unidad', '4')
            ->set('familia', '5')
            ->set('codigo', ' <b>PRD-010</b> ')
            ->set('codigobar', '750000000010')
            ->set('descripcion', ' <b>Ibuprofeno 400 mg</b> ')
            ->set('estado', '1')
            ->set('principiosActivos', [6])
            ->call('save')
            ->assertHasNoErrors()
            ->call('openEdit', 9)
            ->assertSet('laboratoryName', 'Laboratorio Central')
            ->assertSet('codigo', 'PRD-009')
            ->assertSet('principiosActivos', [6])
            ->assertSee('>500 mg</td>', false)
            ->assertDontSee('>7</td>', false)
            ->set('descripcion', 'Acetaminofén 650 mg')
            ->call('save')
            ->assertHasNoErrors()
            ->call('confirmDelete', 9)
            ->call('delete')
            ->assertSet('showDeleteModal', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/farma/productos/producto/crear'
            && $request->data()['codigo'] === 'PRD-010'
            && $request->data()['descripcion'] === 'Ibuprofeno 400 mg'
            && $request->data()['principios_activos'] === [6]
            && $request->hasHeader('Action-Btn', 'prdbtn02'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->data()['id'] === 9
            && $request->data()['descripcion'] === 'Acetaminofén 650 mg'
            && $request->hasHeader('Action-Btn', 'prdbtn04'));
    }

    public function test_create_requires_family_for_the_selected_category_only(): void
    {
        $this->fakeList();
        Http::fake([
            'api.example.test/api/selectors/product-categories*' => function (Request $request) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
                $id = (int) $query['selected'];
                $name = $id === 2 ? 'Farmacia' : 'Consumo';

                return Http::response(['data' => [[
                    'id' => (string) $id,
                    'label' => $name,
                    'record' => ['id' => $id, 'nombre' => $name, 'requires_family' => $id === 2],
                ]]]);
            },
            'api.example.test/api/farma/productos/producto/crear' => Http::response([
                'message' => 'Producto creado correctamente.', 'code' => 201, 'data' => true,
            ]),
        ]);

        Livewire::test(Productos::class)
            ->call('openCreate')
            ->call('rememberSelectorChoice', 'product-categories', '2')
            ->set('categoria', '2')
            ->assertSee('Familia (obligatoria)')
            ->set('laboratorio', '3')
            ->set('unidad', '4')
            ->set('codigo', 'PRD-010')
            ->set('descripcion', 'Producto de prueba')
            ->set('estado', '1')
            ->call('save')
            ->assertHasErrors(['familia' => 'required'])
            ->call('rememberSelectorChoice', 'product-categories', '8')
            ->set('categoria', '8')
            ->assertDontSee('Familia (obligatoria)')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $requests = Http::recorded(fn (Request $request): bool => $request->method() === 'POST');
        $this->assertCount(1, $requests);
        [$request] = $requests->first();
        $this->assertSame(['id' => 8], $request['categoria']);
        $this->assertNull($request['familia']);
    }

    public function test_it_opens_the_global_image_viewer_for_products(): void
    {
        $this->fakeList(hasImage: true);
        $this->fakeOptions();
        Http::fake([
            'api.example.test/api/farma/productos/producto/imagen/9' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['image' => $this->pngBase64()],
            ]),
            'api.example.test/api/farma/productos/producto/buscar/9' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => $this->product(hasImage: true),
            ]),
        ]);

        Livewire::test(Productos::class)
            ->assertSee('data-test="view-product-image-button"', false)
            ->assertSee('data-image-viewer-key="products:9"', false)
            ->assertSee('data-image-viewer-on-demand', false)
            ->assertSee('data-image-viewer-name="Acetaminofén 500 mg"', false)
            ->call('viewImage', 9)
            ->assertDispatched('image-viewer:show')
            ->call('openEdit', 9)
            ->assertSee('Imagen actual del producto')
            ->call('closeForm')
            ->call('openEdit', 9);

        foreach (['imagen/9' => 1, 'principios_activos' => 0] as $path => $expectedRequests) {
            $this->assertCount($expectedRequests, Http::recorded(fn (Request $request): bool => str_ends_with(
                (string) parse_url($request->url(), PHP_URL_PATH), '/'.$path,
            )));
        }
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/laboratorios'));
    }

    public function test_laboratory_picker_loads_only_requested_pages_and_searches_by_name(): void
    {
        $this->fakeList();
        Http::fake([
            'api.example.test/api/farma/productos/producto/laboratorios*' => function (Request $request) {
                $page = (int) $request['page'];

                return Http::response([
                    'message' => 'ok', 'code' => 200,
                    'data' => [['id' => 30 + $page, 'nombre' => 'Laboratorio página '.$page]],
                    'meta' => ['total' => 2, 'current_page' => $page, 'last_page' => 2],
                ]);
            },
            'api.example.test/api/farma/productos/producto/filter_laboratorios*' => function (Request $request) {
                $page = (int) $request['page'];
                $empty = $request['filterItems'][0]['value'] === 'Inexistente';

                return Http::response([
                    'message' => 'ok', 'code' => 200,
                    'data' => $empty ? [] : [['id' => 40 + $page, 'nombre' => 'Encontrado '.$page]],
                    'meta' => ['total' => $empty ? 0 : 2, 'current_page' => $page, 'last_page' => $empty ? 1 : 2],
                ]);
            },
            'api.example.test/api/farma/productos/producto/crear' => Http::response([
                'message' => 'Creado', 'code' => 201, 'data' => true,
            ]),
        ]);

        $this->fakeOptions();
        $component = Livewire::test(Productos::class)->call('openCreate');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/laboratorios'));

        $component->call('openLaboratoryPicker')
            ->assertSet('showLaboratoryPicker', true)
            ->assertSet('showFamilyPicker', false)
            ->assertSet('showUnitPicker', false)
            ->assertSet('showActivePicker', false)
            ->assertSet('showDeleteModal', false)
            ->assertSee('data-test="laboratory-picker-modal"', false)
            ->assertDontSee('data-test="family-picker-modal"', false)
            ->assertDontSee('data-test="unit-picker-modal"', false)
            ->assertDontSee('data-test="active-picker-modal"', false)
            ->assertDontSee('Eliminar producto')
            ->assertSee('Laboratorio página 1')
            ->assertDontSee('Laboratorio página 2')
            ->call('nextLaboratoryPage')
            ->assertSet('laboratoryPage', 2)
            ->assertSee('Laboratorio página 2')
            ->assertDontSee('Laboratorio página 1')
            ->call('previousLaboratoryPage')
            ->assertSet('laboratoryPage', 1)
            ->set('laboratorySearch', ' Encontrado ')
            ->assertSet('laboratoryPage', 1)
            ->assertSee('Encontrado 1')
            ->call('nextLaboratoryPage')
            ->assertSet('laboratoryPage', 2)
            ->assertSee('Encontrado 2')
            ->set('laboratorySearch', 'Inexistente')
            ->assertSet('laboratoryPage', 1)
            ->assertSet('laboratoryTotal', 0)
            ->assertSee('No hay laboratorios que coincidan')
            ->set('laboratorySearch', '')
            ->assertSee('Laboratorio página 1')
            ->call('selectLaboratory', 999)
            ->assertSet('laboratorio', '')
            ->call('nextLaboratoryPage')
            ->call('selectLaboratory', 32)
            ->assertSet('laboratorio', '32')
            ->assertSet('laboratoryName', 'Laboratorio página 2')
            ->assertSet('showLaboratoryPicker', false)
            ->set('categoria', '2')
            ->set('unidad', '4')
            ->set('familia', '5')
            ->set('codigo', 'PRD-010')
            ->set('descripcion', 'Producto de prueba')
            ->set('estado', '1')
            ->call('save')
            ->assertHasNoErrors();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/filter_laboratorios')
            && $request->data() === ['page' => 2, 'filterItems' => [['key' => 'nombre', 'value' => 'Encontrado']]]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'laboratorios') && isset($request['all']));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['laboratorio'] === ['id' => 32]);
        $this->assertCount(8, Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'laboratorios')));
    }

    public function test_laboratory_picker_clears_failed_results_and_can_retry(): void
    {
        $this->fakeList();
        Http::fake([
            'api.example.test/api/farma/productos/producto/laboratorios*' => Http::sequence()
                ->push(['message' => 'Servicio no disponible', 'code' => 503], 503)
                ->push(['message' => 'ok', 'code' => 200, 'data' => [['id' => 3, 'nombre' => 'Laboratorio recuperado']]]),
        ]);
        $this->fakeOptions();

        Livewire::test(Productos::class)
            ->call('openCreate')
            ->call('openLaboratoryPicker')
            ->assertSet('laboratoryOptions', [])
            ->assertSee('Servicio no disponible')
            ->call('retryLaboratoryPage')
            ->assertSet('laboratoryLoadError', null)
            ->call('selectLaboratory', 3)
            ->assertSet('laboratorio', '3')
            ->call('closeForm')
            ->assertSet('laboratoryName', '')
            ->assertSet('laboratoryOptions', [])
            ->assertSet('showLaboratoryPicker', false);
    }

    public function test_catalog_pickers_filter_paginate_and_manage_active_principles(): void
    {
        $this->fakeList();
        $families = collect(range(1, 12))->map(fn (int $number): array => [
            'id' => 100 + $number,
            'label' => "Familia {$number}",
            'nombre' => "Familia {$number}",
            'presentacion' => sprintf('Presentación %02d', $number),
            'administracion' => 'Oral',
        ])->all();
        $units = collect(range(1, 12))->map(fn (int $number): array => [
            'id' => 200 + $number,
            'abreviatura_c' => "CAJ {$number}",
            'cantidad_c' => $number * 10,
            'abreviatura_v' => "UND {$number}",
            'cantidad_v' => $number,
        ])->all();
        $actives = collect(range(1, 12))->map(fn (int $number): array => [
            'id' => 300 + $number,
            'label' => "Activo {$number}",
            'nombre' => "Activo {$number}",
            'concentracion' => 100 + $number,
            'concentracion_detalle' => ['id' => 100 + $number, 'valor' => "{$number} mg"],
        ])->all();

        Http::fake([
            ...$this->productOptionResponses([
                'message' => 'ok', 'code' => 200, 'data' => [
                    'categorias' => [['id' => 2, 'label' => 'Medicamentos']],
                    'laboratorios' => [['id' => 3, 'label' => 'Laboratorio Central']],
                    'familias' => $families,
                    'unidades' => $units,
                    'estados' => [['id' => 1, 'label' => 'Activo']],
                    'principios_activos' => $actives,
                ],
            ]),
            'api.example.test/api/farma/productos/producto/familias*' => fn (Request $request) => $this->familyPageResponse($request, $families),
            'api.example.test/api/farma/productos/producto/principios_activos*' => fn (Request $request) => $this->familyPageResponse($request, $actives),
        ]);

        $this->fakeSelectorChoice('product-units', ['id' => 204, 'abreviatura_c' => 'CAJ 4', 'cantidad_c' => 40, 'abreviatura_v' => 'UND 4', 'cantidad_v' => 4, 'label' => 'CAJ 4 × 40 → UND 4 × 4']);
        $component = Livewire::test(Productos::class)
            ->call('openCreate')
            ->call('openFamilyPicker')
            ->assertSet('showFamilyPicker', true)
            ->assertSee('Cargando familias')
            ->call('retryFamilyPage')
            ->assertSee('Presentación 10')
            ->assertDontSee('Presentación 11')
            ->call('nextFamilyPage')
            ->assertSet('familyPage', 2)
            ->assertSee('Presentación 11')
            ->set('familySearch', 'Presentación 12')
            ->assertSet('familyPage', 1)
            ->call('selectFamily', 112)
            ->assertSet('familia', '112')
            ->assertSet('showFamilyPicker', false)
            ->call('rememberSelectorChoice', 'product-units', '204')->set('unidad', '204')
            ->call('openActivePicker')
            ->call('retryActivePage')
            ->assertSee('>1 mg</td>', false)
            ->assertDontSee('>101</td>', false);

        foreach (range(1, 10) as $number) {
            $component->call('addActive', 300 + $number);
        }

        $component
            ->call('nextActivePage')
            ->call('addActive', 311)
            ->assertSet('selectedActivePage', 2)
            ->assertSee('Activo 11')
            ->call('removeActive', 311)
            ->assertSet('selectedActivePage', 1)
            ->assertCount('principiosActivos', 10)
            ->set('imagenArchivo', UploadedFile::fake()->image('producto.png', 40, 40))
            ->assertHasNoErrors('imagenArchivo')
            ->assertSee('Nueva imagen optimizada')
            ->assertSee('Vista previa del producto');
    }

    public function test_catalog_pickers_fetch_new_records_and_keep_the_product_draft(): void
    {
        $this->fakeList();
        foreach (['familias', 'unidades', 'principios_activos'] as $catalog) {
            $sequence = Http::sequence();
            if ($catalog === 'unidades') {
                $sequence->push(['code' => 200, 'data' => []]);
            }
            Http::fake([
                'api.example.test/api/farma/productos/producto/'.$catalog.'*' => $sequence
                    ->push(['code' => 200, 'data' => [[
                        'id' => 101, 'nombre' => 'Registro nuevo', 'presentacion' => 'Tabletas',
                        'abreviatura_c' => 'CAJ', 'abreviatura_v' => 'UND',
                    ]]]),
            ]);
        }
        $this->fakeOptions();
        $this->fakeSelectorChoice('product-units', ['id' => 101, 'abreviatura_c' => 'CAJ', 'abreviatura_v' => 'UND', 'cantidad_c' => 1, 'cantidad_v' => 1]);

        Livewire::test(Productos::class)
            ->call('openCreate')
            ->assertSet('options.familias', [])
            ->set('codigo', 'BORRADOR-01')
            ->set('descripcion', 'Producto sin guardar')
            ->set('categoria', '2')
            ->call('openFamilyPicker')
            ->call('retryFamilyPage')
            ->assertSee('Registro nuevo')
            ->call('selectFamily', 101)
            ->assertSet('familia', '101')
            ->call('rememberSelectorChoice', 'product-units', '101')
            ->assertSet('options.unidades.0.id', 101)
            ->set('unidad', '101')
            ->call('openActivePicker')
            ->call('retryActivePage')
            ->assertSet('options.principios_activos.0.id', 101)
            ->call('addActive', 101)
            ->assertSet('codigo', 'BORRADOR-01')
            ->assertSet('descripcion', 'Producto sin guardar')
            ->assertSet('categoria', '2')
            ->assertSet('familia', '101')
            ->assertSet('unidad', '101')
            ->assertSet('principiosActivos', [101]);
    }

    public function test_large_active_catalog_stays_paged_and_refresh_preserves_selections_and_the_draft(): void
    {
        $this->fakeList();
        $fresh = false;
        Http::fake(['api.example.test/api/farma/productos/producto/principios_activos*' => function (Request $request) use (&$fresh) {
            $page = max(1, (int) $request['page']);
            $items = collect(range(1, 10))->map(fn ($number): array => [
                'id' => 4000 - ($page - 1) * 10 - $number,
                'nombre' => 'Principio '.(4000 - ($page - 1) * 10 - $number),
                'concentracion_detalle' => ['valor' => '500 mg'],
            ])->all();
            if ($fresh && $page === 1) {
                $items[0] = ['id' => 4000, 'nombre' => 'Principio creado en otra pestaña'];
            }

            return Http::response(['code' => 200, 'data' => $items,
                'meta' => ['current_page' => $page, 'last_page' => 392, 'total' => 3914 + (int) $fresh]]);
        }]);
        $this->fakeOptions();

        $component = Livewire::test(Productos::class)->call('openCreate')
            ->set('codigo', 'BORRADOR-PAGINADO')->set('descripcion', 'Producto sin guardar')
            ->assertSet('options.principios_activos', [])->call('openActivePicker')
            ->assertSet('activeLoaded', false)->assertSee('Cargando principios activos…');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/principios_activos'));

        $component->call('retryActivePage')->assertCount('activeOptions', 10)
            ->assertSet('activeTotal', 3914)->call('addActive', 3999)->call('nextActivePage')
            ->assertSet('activePage', 2)->assertCount('activeOptions', 10)
            ->assertCount('options.principios_activos', 11)->assertSee('Principio 3999')
            ->call('addActive', 3999)->assertSet('principiosActivos', [3999])
            ->call('closeActivePicker')->assertCount('options.principios_activos', 1);

        $fresh = true;
        $component->call('openActivePicker')->call('retryActivePage')
            ->assertSee('Principio creado en otra pestaña')->call('addActive', 4000)
            ->assertSet('principiosActivos', [3999, 4000])->call('closeActivePicker')
            ->assertCount('options.principios_activos', 2)
            ->assertSet('codigo', 'BORRADOR-PAGINADO')->assertSet('descripcion', 'Producto sin guardar');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/principios_activos') && isset($request['all']));
    }

    public function test_failed_active_picker_can_retry_without_losing_the_selected_principles(): void
    {
        $this->fakeList();
        Http::fake([
            'api.example.test/api/farma/productos/producto/buscar/9' => Http::response(['code' => 200, 'data' => $this->product()]),
            'api.example.test/api/farma/productos/producto/principios_activos*' => Http::sequence()
                ->push(['code' => 503, 'message' => 'El catálogo no está disponible.'], 503)
                ->push(['code' => 200, 'data' => [['id' => 7, 'nombre' => 'Principio nuevo']]]),
        ]);
        $this->fakeOptions();

        Livewire::test(Productos::class)->call('openEdit', 9)->call('openActivePicker')
            ->call('retryActivePage')->assertSet('showActivePicker', true)
            ->assertSee('El catálogo no está disponible.')->assertSee('Reintentar')
            ->assertSet('principiosActivos', [6])->assertSee('Paracetamol')
            ->call('addActive', 7)->assertSet('principiosActivos', [6])
            ->call('retryActivePage')->assertSet('activeLoadError', null)
            ->assertSee('Principio nuevo')->call('addActive', 7)
            ->assertSet('principiosActivos', [6, 7])->assertSet('codigo', 'PRD-009');
    }

    public function test_manual_refresh_updates_only_the_open_picker_without_resetting_the_draft(): void
    {
        $this->fakeList();
        Http::fake([
            'api.example.test/api/farma/productos/producto/familias*' => Http::sequence()
                ->push(['code' => 200, 'data' => [['id' => 5, 'nombre' => 'Familia inicial']]])
                ->push(['code' => 200, 'data' => [
                    ['id' => 5, 'nombre' => 'Familia inicial'],
                    ['id' => 6, 'nombre' => 'Familia creada en otra pestaña'],
                ]]),
            'api.example.test/api/farma/productos/producto/categorias*' => Http::sequence()
                ->push(['code' => 200, 'data' => [['id' => 2, 'nombre' => 'Categoría inicial']]])
                ->push(['code' => 200, 'data' => [
                    ['id' => 2, 'nombre' => 'Categoría inicial'],
                    ['id' => 3, 'nombre' => 'Categoría nueva'],
                ]]),
        ]);
        $this->fakeOptions();

        $component = Livewire::test(Productos::class)
            ->call('openCreate')
            ->assertDontSee('visibilitychange', false)
            ->set('codigo', 'BORRADOR-02')
            ->set('familia', '5')
            ->set('categoria', '2')
            ->set('principiosActivos', [6])
            ->set('imagenArchivo', UploadedFile::fake()->image('borrador.png', 20, 20))
            ->call('openFamilyPicker')
            ->set('familySearch', 'Familia');

        $requestCount = count(Http::recorded());

        // A component render must not reload catalogs; returning to a tab has no refresh listener.
        $component->call('$refresh')
            ->assertDontSee('visibilitychange', false)
            ->assertDontSee('Familia creada en otra pestaña');
        Http::assertSentCount($requestCount);

        $component->call('refreshOptions', 'familias')
            ->assertSet('showFamilyPicker', true)
            ->assertSet('familySearch', 'Familia')
            ->assertSee('Familia creada en otra pestaña')
            ->assertDontSee('Categoría nueva')
            ->assertSet('codigo', 'BORRADOR-02')
            ->assertSet('familia', '5')
            ->assertSet('categoria', '2')
            ->assertSet('principiosActivos', [6])
            ->assertSee('Nueva imagen optimizada');

        Http::assertSentCount($requestCount + 1);
        foreach (['categorias', 'estados', 'unidades'] as $catalog) {
            $this->assertCount(0, Http::recorded(fn (Request $request): bool => str_ends_with(
                (string) parse_url($request->url(), PHP_URL_PATH), '/'.$catalog,
            )));
        }

        $component->call('refreshOptions', 'unidades')
            ->call('refreshOptions', 'no-existe')
            ->call('closeFamilyPicker')
            ->call('refreshOptions', 'familias')
            ->call('closeForm')
            ->call('refreshOptions', 'familias');
        Http::assertSentCount($requestCount + 1);
    }

    public function test_family_catalog_is_lazy_and_reopening_its_picker_loads_current_records(): void
    {
        $this->fakeList();
        Http::fake([
            'api.example.test/api/farma/productos/producto/familias*' => Http::sequence()
                ->push(['code' => 200, 'data' => []])
                ->push(['code' => 200, 'data' => [['id' => 7, 'nombre' => 'Familia reciente']]]),
        ]);
        $this->fakeOptions();

        Livewire::test(Productos::class)
            ->call('openCreate')
            ->assertSet('options.familias', [])
            ->call('openFamilyPicker')
            ->assertSet('familyLoaded', false)
            ->assertSee('wire:init="retryFamilyPage"', false)
            ->assertSee('Cargando familias');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/familias'));

        Livewire::test(Productos::class)->call('openCreate')
            ->call('openFamilyPicker')->call('retryFamilyPage')
            ->assertSet('familyOptions', [])
            ->call('closeFamilyPicker')
            ->call('openFamilyPicker')->call('retryFamilyPage')
            ->assertSet('familyOptions.0.id', 7);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/familias') && isset($request['all']));
    }

    public function test_picker_refresh_requires_an_open_form_and_reports_failures_without_losing_the_draft(): void
    {
        $this->fakeList();
        Http::fake([
            'api.example.test/api/farma/productos/producto/familias*' => Http::sequence()
                ->push(['code' => 503, 'message' => 'No se pudo actualizar familias'], 503)
                ->push(['code' => 200, 'data' => [['id' => 8, 'nombre' => 'Familia recuperada']]]),
        ]);
        $this->fakeOptions();

        $component = Livewire::test(Productos::class)
            ->call('refreshOptions', 'familias')
            ->call('openFamilyPicker')
            ->assertSet('showFamilyPicker', false);
        Http::assertSentCount(1);

        $component->call('openCreate')
            ->set('codigo', 'BORRADOR-03')
            ->call('openFamilyPicker')
            ->assertSet('showFamilyPicker', true)
            ->call('retryFamilyPage')
            ->assertSet('familyLoadError', 'No se pudo actualizar familias')
            ->assertSee('Reintentar')
            ->call('selectFamily', 8)
            ->assertSet('familia', '')
            ->assertSet('codigo', 'BORRADOR-03')
            ->call('retryFamilyPage')
            ->assertSet('familyLoadError', null)
            ->assertSet('showFamilyPicker', true)
            ->assertSee('Familia recuperada');
    }

    public function test_family_relations_render_and_filter_as_text_when_editing_products(): void
    {
        $this->fakeList();
        $families = [
            ['id' => 5, 'descripcion' => 'Analgésicos', 'presentacion' => ['id' => 8, 'descripcion' => 'Tabletas'], 'administracion' => ['id' => 9, 'nombre' => 'Oral']],
            ['id' => 6, 'descripcion' => 'Otra familia', 'presentacion' => ['id' => 10, 'label' => 'Cápsulas'], 'administracion' => null],
            ['id' => 7, 'descripcion' => 'Sin detalles', 'presentacion' => ['id' => 11], 'administracion' => []],
        ];
        Http::fake([
            'api.example.test/api/farma/productos/producto/familias*' => fn (Request $request) => $this->familyPageResponse($request, $families),
            'api.example.test/api/farma/productos/producto/buscar/9' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => $this->product(),
            ]),
        ]);
        $this->fakeOptions();

        Livewire::test(Productos::class)
            ->call('openEdit', 9)
            ->assertSet('showFormModal', true)
            ->assertSet('options.familias.0.presentacion', 'Tabletas')
            ->assertSet('options.familias.0.administracion', 'Oral')
            ->assertSee('Oral')
            ->call('openFamilyPicker')->call('retryFamilyPage')
            ->assertSee('Cápsulas')
            ->set('familySearch', 'Tabletas')
            ->assertSee('Analgésicos')
            ->assertDontSee('Otra familia')
            ->set('familySearch', '')
            ->call('selectFamily', 6)
            ->assertSet('familia', '6')
            ->assertSee('Cápsulas')
            ->call('openFamilyPicker')->call('retryFamilyPage')
            ->call('selectFamily', 7)
            ->assertSet('familia', '7')
            ->assertSee('Sin detalles');
    }

    public function test_family_picker_modal_is_wide_and_uses_a_fixed_table_without_horizontal_scroll(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/productos.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString(
            'class="products-content-modal products-family-picker-modal',
            $view,
        );
        $this->assertStringContainsString(
            'max-h-104 overflow-x-hidden overflow-y-auto',
            $view,
        );
        $this->assertStringContainsString(
            'class="w-full table-fixed divide-y divide-slate-200 text-sm dark:divide-slate-700" data-test="family-picker-table"',
            $view,
        );
        $this->assertStringContainsString('<col class="w-28" />', $view);
    }

    public function test_long_laboratory_names_wrap_inside_their_product_table_column(): void
    {
        $view = file_get_contents(resource_path('views/livewire/farma/productos.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString(
            '<flux:table.column class="w-[13%] py-3.5">Laboratorio</flux:table.column>',
            $view,
        );
        $this->assertStringContainsString(
            'class="block whitespace-normal break-words leading-snug [overflow-wrap:anywhere]"',
            $view,
        );
        $this->assertStringContainsString('data-test="product-laboratory"', $view);
    }

    public function test_parent_selector_sends_explicit_relation_and_preserves_it_when_editing(): void
    {
        $this->fakeList();
        $this->fakeOptions();
        Http::fake([
            'api.example.test/api/farma/productos/producto/padres*' => Http::response([
                'code' => 200, 'message' => 'ok',
                'data' => [['id' => '9223372036854775808', 'codigo' => 'P-TEST', 'descripcion' => 'Parent test']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/productos/producto/buscar/9' => Http::response([
                'code' => 200, 'message' => 'ok', 'data' => [...$this->product(),
                    'producto_padre_id' => '9223372036854775808',
                    'producto_padre' => ['codigo' => 'P-TEST', 'descripcion' => 'Parent test']],
            ]),
            'api.example.test/api/farma/productos/producto/actualizar/9' => Http::response(['code' => 200, 'message' => 'ok', 'data' => true]),
        ]);
        $component = Livewire::test(Productos::class)->call('openEdit', 9)
            ->assertSet('productoPadreId', '9223372036854775808')
            ->assertDontSee('Seleccionar producto padre')
            ->assertSee('P-TEST')->call('openParentPicker')
            ->assertSet('showParentPicker', true)->assertSee('Seleccionar producto padre')->assertSee('Parent test')
            ->assertDontSee('Eliminar producto')
            ->call('selectParent', 'NOT-IN-RESULTS')->assertSet('showParentPicker', true)
            ->call('selectParent', '9223372036854775808')->assertSet('showParentPicker', false)
            ->assertDontSee('Seleccionar producto padre')->assertSet('showFormModal', true)
            ->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/actualizar/9')
            && $request['producto_padre_id'] === '9223372036854775808');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/padres')
            && $request->hasHeader('Action-Btn', 'prdbtn07'));
        $component->call('openEdit', 9)->call('clearParent')->assertSet('productoPadreId', '')
            ->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/actualizar/9')
            && array_key_exists('producto_padre_id', $request->data()) && $request['producto_padre_id'] === null);
    }

    private function fakeList(bool $hasImage = false): void
    {
        Http::fake([
            'api.example.test/api/farma/productos/producto/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [$this->product($hasImage)],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
        ]);
    }

    /** @param array<int, array<string, mixed>> $families */
    private function familyPageResponse(Request $request, array $families): PromiseInterface
    {
        $search = mb_strtolower(trim((string) $request['search']));
        $matches = array_values(array_filter($families, fn (array $family): bool => $search === ''
            || str_contains(mb_strtolower(json_encode($family, JSON_UNESCAPED_UNICODE)), $search)));
        $page = max(1, (int) $request['page']);

        return Http::response([
            'code' => 200, 'data' => array_slice($matches, ($page - 1) * 10, 10),
            'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil(count($matches) / 10)), 'total' => count($matches)],
        ]);
    }

    private function fakeOptions(): void
    {
        Http::fake([
            ...$this->productOptionResponses([
                'message' => 'ok', 'code' => 200, 'data' => [
                    'categorias' => [['id' => 2, 'label' => 'Medicamentos']],
                    'laboratorios' => [['id' => 3, 'label' => 'Laboratorio Central']],
                    'unidades' => [[
                        'id' => 4,
                        'abreviatura_c' => 'CAJ',
                        'cantidad_c' => 1,
                        'abreviatura_v' => 'UND',
                        'cantidad_v' => 20,
                    ]],
                    'familias' => [[
                        'id' => 5,
                        'label' => 'Tabletas orales',
                        'nombre' => 'Analgésicos',
                        'presentacion' => 'Tabletas',
                        'administracion' => 'Oral',
                    ]],
                    'estados' => [['id' => 1, 'label' => 'Activo']],
                    'principios_activos' => [[
                        'id' => 6,
                        'label' => 'Paracetamol 500 mg',
                        'nombre' => 'Paracetamol',
                        'concentracion' => 7,
                        'concentracion_detalle' => ['id' => 7, 'valor' => '500 mg'],
                    ]],
                ],
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function product(bool $hasImage = false): array
    {
        return [
            'id' => 9,
            'codigo' => 'PRD-009',
            'codigobar' => '750000000009',
            'descripcion' => 'Acetaminofén 500 mg',
            'tiene_imagen' => $hasImage,
            'categoria_id' => 2,
            'laboratorio_id' => 3,
            'unidad_id' => 4,
            'familia_id' => 5,
            'estado_id' => 1,
            'categoria' => ['id' => 2, 'nombre' => 'Medicamentos'],
            'laboratorio' => ['id' => 3, 'nombre' => 'Laboratorio Central'],
            'unidad' => [
                'id' => 4,
                'abreviatura_c' => 'CAJ',
                'cantidad_c' => 1,
                'abreviatura_v' => 'UND',
                'cantidad_v' => 20,
            ],
            'familia' => [
                'id' => 5,
                'descripcion' => 'Tabletas orales',
                'presentacion' => ['id' => 8, 'descripcion' => 'Tabletas'],
                'administracion' => ['id' => 9, 'descripcion' => 'Oral'],
            ],
            'estado' => ['id' => 1, 'descripcion' => 'Activo'],
            'principios_activos' => [[
                'id' => 6,
                'nombre' => 'Paracetamol',
                'concentracion' => 7,
                'concentracion_detalle' => ['id' => 7, 'valor' => '500 mg'],
            ]],
        ];
    }

    private function pngBase64(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image, null, 9, PNG_ALL_FILTERS);
        $png = ob_get_clean();
        unset($image);
        $this->assertIsString($png);

        return base64_encode($png);
    }

    /** @return array<int, array<string, mixed>> */
    private function permissions(): array
    {
        return [[
            'modulo_l' => ['codigo' => 'frm'],
            'vistas_l' => [[
                'vistai' => ['codigo' => 'prd'],
                'accionesi' => collect([
                    'prdbtn01', 'prdbtn02', 'prdbtn03', 'prdbtn04',
                    'prdbtn05', 'prdbtn06', 'prdbtn07', 'prdbtn08',
                    'prdbtn09', 'prdbtn10', 'prdbtn11', 'prdbtn12',
                    'prdbtn19',
                ])->map(fn (string $code): array => ['codigo' => $code])->all(),
            ]],
        ]];
    }
}
