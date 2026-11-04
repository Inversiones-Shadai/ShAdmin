<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config([
            'services.shadmin_api.base_url' => null,
            'services.shadmin_api.auth_login_path' => '/auth/login',
            'services.shadmin_api.device_header_secret' => null,
            'services.shadmin_api.device_name' => null,
        ]);

        parent::tearDown();
    }

    public function test_users_can_authenticate_against_the_api(): void
    {
        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test',
            'services.shadmin_api.auth_login_path' => '/login',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.device_name' => 'DESKTOP-TEST',
        ]);

        Http::fake([
            'api.example.test/login' => Http::response([
                'message' => 'Operacion exitosa',
                'code' => 200,
                'data' => [
                    'token' => 'encrypted-token',
                    'uname' => 'Juan Perez',
                    'urol' => 'Administrador',
                    'stockname' => 'Principal',
                    'stockid' => 7,
                    'deviceid' => 33,
                    'spermisos' => [
                        [
                            'modulo_l' => [
                                'codigo' => 'VENTAS',
                            ],
                            'vistas_l' => [
                                [
                                    'vistai' => [
                                        'codigo' => 'PEDIDOS',
                                    ],
                                    'accionesi' => [
                                        [
                                            'codigo' => 'CREAR',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->post(route('login.store'), [
            'name' => 'jperez',
            'password' => 'secret',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 0, 'sqlite');
        $this->get(route('dashboard'))->assertOk();
        $this->assertSame('Juan Perez', auth()->user()->name);

        $this->assertSame('encrypted-token', session('auth.api.token'));
        $this->assertSame('Administrador', session('auth.api.urol'));
        $this->assertSame(7, session('auth.api.stockid'));
        $this->assertSame('VENTAS', session('auth.api.spermisos.0.modulo_l.codigo'));
        $this->assertSame('PEDIDOS', session('auth.api.spermisos.0.vistas_l.0.vistai.codigo'));
        $this->assertSame('CREAR', session('auth.api.spermisos.0.vistas_l.0.accionesi.0.codigo'));
        $this->assertTrue(session('auth.api.abilities.ventas'));
        $this->assertTrue(session('auth.api.abilities.ventas|pedidos'));
        $this->assertTrue(session('auth.api.abilities.ventas|pedidos|crear'));
        Http::assertSentCount(1);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.example.test/login'
                && $request->data() === [
                    'name' => hash_hmac('sha256', 'jperez', 'test-secret'),
                    'password' => hash_hmac('sha256', 'secret', 'test-secret'),
                ]
                && $request->hasHeader('App-Version', hash_hmac('sha256', '2.5.0', 'test-secret'))
                && $request->hasHeader('X-Device-Ip', hash_hmac('sha256', '10.10.0.25', 'test-secret'))
                && $request->hasHeader('X-Device-Name', hash_hmac('sha256', 'DESKTOP-TEST', 'test-secret'));
        });
    }

    public function test_login_shows_message_when_api_server_does_not_respond(): void
    {
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test',
            'services.shadmin_api.auth_login_path' => '/login',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        Http::fake([
            'api.example.test/login' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $response = $this->post(route('login.store'), [
            'name' => 'jperez',
            'password' => 'secret',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'No se pudo conectar con el servidor.',
        ]);

        $this->assertGuest();
    }

    public function test_login_sends_unknown_device_to_the_api_for_registration(): void
    {
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test',
            'services.shadmin_api.auth_login_path' => '/login',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        config(['services.shadmin_api.device_ip' => null, 'services.shadmin_api.device_name' => null]);
        Http::fake([
            'api.example.test/login' => Http::response([
                'message' => 'Dispositivo no registrado, consultar con el administrador:',
                'code' => 401,
                'data' => null,
            ], 401),
        ]);

        $response = $this->post(route('login.store'), [
            'name' => 'jperez',
            'password' => 'secret',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'Dispositivo no registrado, consultar con el administrador:',
        ]);

        $this->assertGuest();
        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'X-Device-Ip', hash_hmac('sha256', '127.0.0.1', 'test-secret'),
        ) && $request->hasHeader(
            'X-Device-Name', hash_hmac('sha256', 'SHADMIN-WEB', 'test-secret'),
        ));
    }

    public function test_login_shows_api_error_message_when_server_responds_with_error(): void
    {
        config([
            'services.shadmin_api.base_url' => 'https://api.example.test',
            'services.shadmin_api.auth_login_path' => '/login',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        Http::fake([
            'api.example.test/login' => Http::response([
                'message' => 'Usuario o clave incorrectos',
                'code' => 401,
                'data' => null,
            ], 401),
        ]);

        $response = $this->post(route('login.store'), [
            'name' => 'jperez',
            'password' => 'bad-secret',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'Usuario o clave incorrectos',
        ]);

        $this->assertGuest();
    }
}
