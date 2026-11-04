<?php

namespace Tests\Feature\Sorteos;

use App\Services\Api\DrawApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DrawApiServiceTest extends TestCase
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

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_lists_filters_and_loads_draw_states(): void
    {
        Http::fake([
            'api.example.test/api/sorteos/sorteo/listar*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [$this->drawData()],
                'meta' => [
                    'total' => 22,
                    'current_page' => 2,
                    'last_page' => 3,
                ],
            ]),
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
            'api.example.test/api/sorteos/sorteo/estados' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [['id' => 1, 'descripcion' => 'ACTIVO']],
            ]),
        ]);

        $service = app(DrawApiService::class);
        $page = $service->list(2);
        $filtered = $service->filter('Navidad');

        $this->assertSame(11, $page->items[0]['id']);
        $this->assertSame(22, $page->total);
        $this->assertSame(2, $page->currentPage);
        $this->assertSame(1, $filtered->total);
        $this->assertSame('ACTIVO', $service->states()[0]['descripcion']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/sorteos/sorteo/listar?page=2');
        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://api.example.test/api/sorteos/sorteo/filter?',
        )
            && $request->data() === [
                'name' => 'lista-sorteos',
                'filterItems' => [
                    [
                        'key' => 'nombre',
                        'value' => '%Navidad%',
                        'operator' => 'LIKE',
                        'logicalOperator' => 'OR',
                    ],
                    [
                        'key' => 'descripcion',
                        'value' => '%Navidad%',
                        'operator' => 'LIKE',
                        'logicalOperator' => 'OR',
                    ],
                ],
                'logicalOperator' => 'OR',
                'page' => 1,
            ]);
    }

    public function test_it_uses_all_draw_mutation_routes_and_payloads(): void
    {
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
            'api.example.test/api/sorteos/sorteo/eliminar/11' => Http::response([
                'message' => 'Sorteo eliminado exitosamente.',
                'code' => 200,
                'data' => true,
            ]),
        ]);

        $service = app(DrawApiService::class);
        $createPayload = [
            'nombre' => 'Sorteo Navidad',
            'descripcion' => 'Promoción de fin de año',
            'fecha_inicio' => '2026-12-01',
            'fecha_fin' => '2026-12-31',
            'impresiones' => 5,
            'valor' => 100.50,
            'estado' => 1,
        ];
        $updatePayload = [
            'id' => 11,
            'nombre' => 'Sorteo Navidad',
            'descripcion' => 'Promoción actualizada',
            'impresiones' => 10,
            'valor' => 125.50,
            'estado' => 2,
        ];

        $this->assertSame('Sorteo creado exitosamente.', $service->create($createPayload));
        $this->assertSame('Sorteo Navidad', $service->find(11)['nombre']);
        $this->assertSame('Sorteo actualizado exitosamente.', $service->update($updatePayload));
        $this->assertSame('Sorteo eliminado exitosamente.', $service->delete(11));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/sorteos/sorteo/crear'
            && $request->data() === [
                ...$createPayload,
                'estado' => ['id' => 1],
            ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/sorteos/sorteo/actualizar'
            && $request->data() === [
                ...$updatePayload,
                'estado' => ['id' => 2],
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
}
