<?php

namespace Tests\Feature\Farma;

use App\Services\Api\ProviderApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.provider_path' => '/farma/proveedores',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_uses_all_provider_routes_and_payloads(): void
    {
        Http::fake([
            'api.example.test/api/farma/proveedores/listar*' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [[
                    'id' => 8,
                    'nombre' => 'Farmacéutica Uno',
                    'telefono' => '2233-4455',
                    'direccion' => 'Tegucigalpa',
                    'tiene_imagen' => true,
                ]],
                'meta' => ['total' => 31, 'current_page' => 2, 'last_page' => 2],
            ]),
            'api.example.test/api/farma/proveedores/crear' => Http::response([
                'message' => 'Proveedor creado correctamente',
                'code' => 201,
                'data' => true,
            ]),
            'api.example.test/api/farma/proveedores/buscar/8' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    'id' => 8,
                    'nombre' => 'Farmacéutica Uno',
                    'telefono' => '2233-4455',
                    'direccion' => 'Tegucigalpa',
                    'tiene_imagen' => true,
                ],
            ]),
            'api.example.test/api/farma/proveedores/actualizar/8' => Http::response([
                'message' => 'Proveedor actualizado correctamente',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/farma/proveedores/eliminar/8' => Http::response([
                'message' => 'Proveedor eliminado correctamente',
                'code' => 200,
                'data' => true,
            ]),
            'api.example.test/api/farma/proveedores/imagen/8' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => ['image' => 'c2FuaXRpemVk'],
            ]),
        ]);

        $service = app(ProviderApiService::class);
        $payload = [
            'nombre' => 'Farmacéutica Uno',
            'telefono' => '2233-4455',
            'direccion' => 'Tegucigalpa',
            'imagen' => 'c2FuaXRpemVk',
        ];

        $page = $service->list(2);

        $this->assertSame(8, $page->items[0]['id']);
        $this->assertSame(31, $page->total);
        $this->assertSame('Proveedor creado correctamente', $service->create($payload));
        $this->assertSame('Farmacéutica Uno', $service->find(8)['nombre']);
        $this->assertSame('Proveedor actualizado correctamente', $service->update(8, $payload));
        $this->assertSame('Proveedor eliminado correctamente', $service->delete(8));
        $this->assertSame('c2FuaXRpemVk', $service->image(8));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/farma/proveedores/crear'
            && $request->hasHeader('Action-Btn', 'prvbtn02')
            && $request->data() === $payload);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/farma/proveedores/actualizar/8'
            && $request->hasHeader('Action-Btn', 'prvbtn04')
            && $request->data() === [...$payload, 'id' => 8]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/farma/proveedores/imagen/8'
            && $request->hasHeader('Action-Btn', 'prvbtn06'));
    }
}
