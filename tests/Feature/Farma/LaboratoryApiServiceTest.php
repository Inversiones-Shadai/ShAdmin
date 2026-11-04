<?php

namespace Tests\Feature\Farma;

use App\Services\Api\LaboratoryApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaboratoryApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.laboratory_path' => '/farma/laboratorios',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);
        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_uses_all_laboratory_routes_and_action_codes(): void
    {
        Http::fake([
            'api.example.test/api/farma/laboratorios/listar*' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => [['id' => 4, 'nombre' => 'Laboratorio Central']],
                'meta' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
            ]),
            'api.example.test/api/farma/laboratorios/crear' => Http::response([
                'message' => 'Laboratorio creado correctamente.', 'code' => 201, 'data' => true,
            ]),
            'api.example.test/api/farma/laboratorios/buscar/4' => Http::response([
                'message' => 'ok', 'code' => 200,
                'data' => ['id' => 4, 'nombre' => 'Laboratorio Central'],
            ]),
            'api.example.test/api/farma/laboratorios/actualizar/4' => Http::response([
                'message' => 'Laboratorio actualizado correctamente.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/laboratorios/eliminar/4' => Http::response([
                'message' => 'Laboratorio eliminado correctamente.', 'code' => 200, 'data' => true,
            ]),
            'api.example.test/api/farma/laboratorios/imagen/4' => Http::response([
                'message' => 'ok', 'code' => 200, 'data' => ['image' => 'aW1hZ2U='],
            ]),
        ]);

        $service = app(LaboratoryApiService::class);
        $payload = ['nombre' => 'Laboratorio Central', 'telefono' => '', 'direccion' => ''];

        $this->assertSame(4, $service->list()->items[0]['id']);
        $this->assertSame('Laboratorio creado correctamente.', $service->create($payload));
        $this->assertSame('Laboratorio Central', $service->find(4)['nombre']);
        $this->assertSame('Laboratorio actualizado correctamente.', $service->update(4, $payload));
        $this->assertSame('Laboratorio eliminado correctamente.', $service->delete(4));
        $this->assertSame('aW1hZ2U=', $service->image(4));

        $expected = [
            '/listar' => 'lbtbtn01',
            '/crear' => 'lbtbtn02',
            '/buscar/4' => 'lbtbtn03',
            '/actualizar/4' => 'lbtbtn04',
            '/eliminar/4' => 'lbtbtn05',
            '/imagen/4' => 'lbtbtn06',
        ];

        foreach ($expected as $suffix => $code) {
            Http::assertSent(function (Request $request) use ($suffix, $code): bool {
                $path = parse_url($request->url(), PHP_URL_PATH);

                return is_string($path)
                    && str_ends_with($path, $suffix)
                    && $request->hasHeader('Action-Btn', $code);
            });
        }
    }
}
