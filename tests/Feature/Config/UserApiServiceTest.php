<?php

namespace Tests\Feature\Config;

use App\Exceptions\ApiSessionExpiredException;
use App\Models\User;
use App\Services\Api\UserApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.user_path' => '/config/user',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.device_name' => 'DESKTOP-TEST',
        ]);

        session()->put('auth.api.token', 'api-token');
        Http::preventStrayRequests();
    }

    public function test_it_loads_users_roles_and_states(): void
    {
        Http::fake([
            'api.example.test/api/config/user/listar' => Http::response([
                'message' => 'Usuarios encontrados',
                'code' => 200,
                'data' => [
                    ['id' => 7, 'nombre' => 'Ana López', 'name' => 'alopez'],
                ],
            ]),
            'api.example.test/api/config/user/roles' => Http::response([
                'message' => 'Roles encontrados',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ADMINISTRADOR'],
                ],
            ]),
            'api.example.test/api/config/user/estados' => Http::response([
                'message' => 'Estados encontrados',
                'code' => 200,
                'data' => [
                    ['id' => 1, 'descripcion' => 'ACTIVO'],
                ],
            ]),
        ]);

        $service = app(UserApiService::class);

        $this->assertSame(7, $service->list()[0]['id']);
        $this->assertSame('ADMINISTRADOR', $service->roles()[0]['descripcion']);
        $this->assertSame('ACTIVO', $service->states()[0]['descripcion']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/api/config/user/listar'
            && $request->hasHeader('Authorization', 'Bearer api-token'));
    }

    public function test_it_uses_all_user_mutation_routes(): void
    {
        Http::fake([
            'api.example.test/api/config/user/crear' => Http::response([
                'message' => 'Usuario creado',
                'code' => 201,
                'data' => ['id' => 8],
            ], 201),
            'api.example.test/api/config/user/actualizar/8' => Http::response([
                'message' => 'Usuario actualizado',
                'code' => 200,
                'data' => null,
            ]),
            'api.example.test/api/config/user/buscar/8' => Http::response([
                'message' => 'Usuario encontrado',
                'code' => 200,
                'data' => [
                    'id' => 8,
                    'nombre' => 'Carlos Ruiz',
                    'name' => 'cruiz',
                    'email' => 'carlos@example.test',
                    'rol' => 1,
                    'estado' => 1,
                ],
            ]),
            'api.example.test/api/config/user/eliminar/8' => Http::response([
                'message' => 'Usuario eliminado',
                'code' => 200,
                'data' => null,
            ]),
        ]);

        $service = app(UserApiService::class);
        $payload = [
            'nombre' => 'Carlos Ruiz',
            'name' => 'cruiz',
            'email' => 'carlos@example.test',
            'rol' => 1,
            'estado' => 1,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $this->assertSame('Usuario creado', $service->create($payload));
        $this->assertSame('Usuario actualizado', $service->update(['id' => 8, ...$payload]));
        $this->assertSame('Carlos Ruiz', $service->find(8)['nombre']);
        $this->assertSame('Usuario eliminado', $service->delete(8));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.example.test/api/config/user/crear'
            && $request['name'] === hash_hmac('sha256', 'cruiz', 'test-secret')
            && $request['password'] === hash_hmac('sha256', 'secret123', 'test-secret')
            && $request['password_confirmation'] === hash_hmac('sha256', 'secret123', 'test-secret')
            && $request['rol'] === ['id' => 1]
            && $request['estado'] === ['id' => 1]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api.example.test/api/config/user/actualizar/8'
            && $request['id'] === 8
            && $request['name'] === 'cruiz'
            && $request['password'] === 'secret123'
            && $request['rol'] === ['id' => 1]
            && $request['estado'] === ['id' => 1]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.example.test/api/config/user/buscar/8');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.example.test/api/config/user/eliminar/8');
    }

    public function test_it_logs_out_when_the_api_returns_an_unauthorized_status(): void
    {
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'api-token');

        Http::fake([
            'api.example.test/api/config/user/listar' => Http::response([
                'message' => 'La solicitud no está autorizada.',
            ], 401),
        ]);

        try {
            app(UserApiService::class)->list();
            $this->fail('Se esperaba detectar la sesión API expirada.');
        } catch (ApiSessionExpiredException $exception) {
            $this->assertSame('Tu sesión expiró. Inicia sesión nuevamente.', $exception->getMessage());
            $this->assertGuest();
            $this->assertNull(session('auth.api.token'));
        }
    }
}
