<?php

namespace Tests\Feature\Farma;

use App\Exceptions\ApiOperationException;
use App\Services\Api\ProductApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductApiServiceTest extends TestCase
{
    public function test_active_picker_fetches_one_page_with_search_without_requesting_all_records(): void
    {
        Http::fake(['*/principios_activos*' => Http::response([
            'code' => 200, 'data' => [['id' => 3914, 'nombre' => 'Amoxicilina']],
            'meta' => ['current_page' => 2, 'last_page' => 392, 'total' => 3914],
        ])]);

        $page = app(ProductApiService::class)->activePickerPage('  amoxi  ', 2);

        $this->assertSame(3914, $page->total);
        $this->assertSame(2, $page->currentPage);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Action-Btn', 'prdbtn07')
            && $request->data() === ['page' => 2, 'per_page' => 10, 'search' => 'amoxi']);
    }

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
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_uses_the_product_routes_and_action_codes(): void
    {
        Http::fake([
            'api.example.test/api/farma/productos/producto/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 4, 'codigo' => 'PRD-004']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            ...$this->productOptionResponses([
                'message' => 'ok', 'code' => 200,
                'data' => [
                    'categorias' => [['id' => 2, 'label' => 'Medicamentos']],
                    'familias' => [[
                        'id' => 5,
                        'label' => 'Tabletas orales',
                        'nombre' => 'Analgésicos',
                        'presentacion' => 'Tabletas',
                        'administracion' => 'Oral',
                    ]],
                ],
            ]),
            'api.example.test/api/farma/productos/producto/crear' => Http::response([
                'message' => 'Producto creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/producto/buscar/4' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => ['id' => 4, 'codigo' => 'PRD-004'],
            ]),
            'api.example.test/api/farma/productos/producto/actualizar/4' => Http::response([
                'message' => 'Producto actualizado correctamente.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/producto/eliminar/4' => Http::response([
                'message' => 'Producto eliminado correctamente.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/producto/imagen/4' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['image' => 'aW1hZ2U='],
            ]),
        ]);

        $service = app(ProductApiService::class);
        $payload = $this->productPayload();

        $this->assertSame(4, $service->list()->items[0]['id']);
        $this->assertSame(4, $service->advancedSearch(2, 'PRD-004', 3, 8)->items[0]['id']);
        $options = $service->options();
        $this->assertSame('Medicamentos', $options['categorias'][0]['label']);
        $this->assertSame('Tabletas', $options['familias'][0]['presentacion']);
        $this->assertSame('Oral', $options['familias'][0]['administracion']);
        $this->assertSame('Producto creado correctamente.', $service->create($payload));
        $this->assertSame('PRD-004', $service->find(4)['codigo']);
        $this->assertSame('Producto actualizado correctamente.', $service->update(4, $payload));
        $this->assertSame('Producto eliminado correctamente.', $service->delete(4));
        $this->assertSame('aW1hZ2U=', $service->image(4));

        foreach ([
            '/listar' => 'prdbtn01',
            '/crear' => 'prdbtn02',
            '/buscar/4' => 'prdbtn03',
            '/actualizar/4' => 'prdbtn04',
            '/eliminar/4' => 'prdbtn05',
            '/imagen/4' => 'prdbtn06',
            '/categorias' => 'prdbtn07',
        ] as $suffix => $code) {
            Http::assertSent(fn (Request $request): bool => str_ends_with(
                (string) parse_url($request->url(), PHP_URL_PATH),
                $suffix,
            ) && $request->hasHeader('Action-Btn', $code));
        }

        Http::assertSent(fn (Request $request): bool => str_ends_with(
            (string) parse_url($request->url(), PHP_URL_PATH),
            '/listar',
        )
            && $request->hasHeader('Action-Btn', 'prdbtn03')
            && $request->data() === [
                'page' => 2,
                'per_page' => 10,
                'search' => 'PRD-004',
                'laboratorio' => 3,
                'proveedor' => 8,
            ]);
    }

    public function test_it_uses_the_active_principle_routes_and_codes(): void
    {
        Http::fake([
            'api.example.test/api/farma/productos/productos/activos/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 7, 'producto_id' => 4]],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/productos/productos/activos/crear' => Http::response([
                'message' => 'Asociación creada.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/productos/activos/buscar/7' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['id' => 7],
            ]),
            'api.example.test/api/farma/productos/productos/activos/update/7' => Http::response([
                'message' => 'Asociación actualizada.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/productos/productos/activos/eliminar/7' => Http::response([
                'message' => 'Asociación eliminada.', 'code' => 200, 'data' => true,
            ]),
        ]);

        $service = app(ProductApiService::class);
        $payload = ['producto' => 4, 'pactivo' => 3];

        $this->assertSame(7, $service->listActives()->items[0]['id']);
        $this->assertSame('Asociación creada.', $service->createActive($payload));
        $this->assertSame(7, $service->findActive(7)['id']);
        $this->assertSame('Asociación actualizada.', $service->updateActive(7, $payload));
        $this->assertSame('Asociación eliminada.', $service->deleteActive(7));

        foreach ([
            '/activos/listar' => 'prdbtn08',
            '/activos/crear' => 'prdbtn09',
            '/activos/buscar/7' => 'prdbtn10',
            '/activos/update/7' => 'prdbtn11',
            '/activos/eliminar/7' => 'prdbtn12',
        ] as $suffix => $code) {
            Http::assertSent(fn (Request $request): bool => str_ends_with(
                (string) parse_url($request->url(), PHP_URL_PATH),
                $suffix,
            ) && $request->hasHeader('Action-Btn', $code));
        }
    }

    public function test_product_writes_send_nested_relations_and_preserve_active_principles(): void
    {
        Http::fake(['*/crear' => Http::response(['message' => 'ok', 'code' => 201, 'data' => true], 201)]);
        app(ProductApiService::class)->create($this->productPayload());
        Http::assertSent(fn (Request $request): bool => $request['categoria'] === ['id' => 2]
            && $request['laboratorio'] === ['id' => 3]
            && $request['unidad'] === ['id' => 4]
            && $request['familia'] === ['id' => 5]
            && $request['estado'] === ['id' => 1]
            && $request['principios_activos'] === [3]);
    }

    public function test_options_request_complete_catalogs_once_each(): void
    {
        Http::fake(function (Request $request) {
            $all = (int) $request['all'] === 1;

            return Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => $all ? [['id' => 1, 'nombre' => 'Primera'], ['id' => 2, 'nombre' => 'Última']] : [['id' => 1]],
                'meta' => ['current_page' => 1, 'last_page' => $all ? 1 : 2, 'total' => 2],
            ]);
        });

        $options = app(ProductApiService::class)->options();

        foreach (['categorias', 'laboratorios', 'unidades', 'familias', 'estados', 'principios_activos'] as $catalog) {
            $this->assertSame([1, 2], array_column($options[$catalog], 'id'));
            Http::assertSent(fn (Request $request): bool => str_ends_with(
                (string) parse_url($request->url(), PHP_URL_PATH), '/'.$catalog,
            ) && (int) $request['all'] === 1);
        }
        Http::assertSentCount(6);
    }

    public function test_options_collect_every_page_when_the_api_does_not_support_all(): void
    {
        Http::fake(function (Request $request) {
            $page = (int) ($request['page'] ?? 1);

            return Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => $page, 'nombre' => 'Opción '.$page]],
                'meta' => ['current_page' => $page, 'last_page' => 2, 'total' => 2],
            ]);
        });
        $options = app(ProductApiService::class)->options();
        $this->assertSame([1, 2], array_column($options['categorias'], 'id'));
        $this->assertSame([1, 2], array_column($options['principios_activos'], 'id'));
        Http::assertSentCount(12);
    }

    public function test_images_are_reused_across_service_instances_and_expire(): void
    {
        Http::fake(['*/imagen/4' => Http::response([
            'message' => 'ok', 'code' => 200, 'data' => ['image' => 'aW1hZ2U='],
        ])]);

        $this->assertSame('aW1hZ2U=', app(ProductApiService::class)->image(4));
        $this->assertSame('aW1hZ2U=', app(ProductApiService::class)->image(4));
        Http::assertSentCount(1);

        $this->travel(6)->minutes();
        $this->assertSame('aW1hZ2U=', app(ProductApiService::class)->image(4));
        Http::assertSentCount(2);
    }

    public function test_images_are_invalidated_after_update_delete_and_token_changes(): void
    {
        Http::fake([
            '*/imagen/4' => Http::response(['message' => 'ok', 'code' => 200, 'data' => ['image' => 'aW1hZ2U=']]),
            '*/actualizar/4' => Http::response(['message' => 'ok', 'code' => 200, 'data' => true]),
            '*/eliminar/4' => Http::response(['message' => 'ok', 'code' => 200, 'data' => true]),
        ]);
        $service = app(ProductApiService::class);
        $service->image(4);
        $service->update(4, $this->productPayload());
        $this->assertNull(session('products.last_image'));
        $service->image(4);
        Http::assertSentCount(3);

        session()->put('auth.api.token', 'another-token');
        $service->image(4);
        Http::assertSentCount(4);

        $service->delete(4);
        $this->assertNull(session('products.last_image'));
    }

    public function test_nested_validation_errors_are_preserved_for_the_form(): void
    {
        $errors = ['categoria.id' => ['La categoría no existe.']];
        Http::fake(['*/crear' => Http::response([
            'message' => 'Datos inválidos.', 'code' => 422, 'data' => ['errors' => $errors], 'meta' => null,
        ], 422)]);
        try {
            app(ProductApiService::class)->create($this->productPayload());
            $this->fail('Se esperaba un error de validación.');
        } catch (ApiOperationException $exception) {
            $this->assertSame($errors, $exception->errors);
        }
    }

    /** @return array<string, mixed> */
    private function productPayload(): array
    {
        return [
            'categoria' => 2,
            'laboratorio' => 3,
            'unidad' => 4,
            'familia' => 5,
            'codigo' => 'PRD-004',
            'codigobar' => '750000000004',
            'descripcion' => 'Producto de prueba',
            'estado' => 1,
            'principios_activos' => [3],
        ];
    }
}
