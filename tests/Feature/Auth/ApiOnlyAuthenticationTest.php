<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiOnlyAuthenticationTest extends TestCase
{
    public function test_login_and_next_page_work_with_no_database_connection(): void
    {
        config([
            'database.default' => 'none',
            'services.shadmin_api.base_url' => 'https://api.example.test/api',
            'services.shadmin_api.device_header_secret' => 'test-secret',
        ]);

        Http::fake([
            'api.example.test/api/auth/session/login' => Http::response([
                'message' => 'ok',
                'code' => 200,
                'data' => [
                    'token' => 'encrypted-token',
                    'uname' => 'Usuario API',
                    'urol' => 'Administrador',
                    'stockname' => 'Principal',
                    'stockid' => 7,
                    'deviceid' => 33,
                    'spermisos' => [],
                ],
            ]),
        ]);

        $this->get(route('login'))->assertOk();
        $this->post(route('login.store'), ['name' => 'usuario', 'password' => 'secreto'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertOk();
        $this->assertAuthenticated();
        $this->assertSame('Usuario API', auth()->user()->name);
        Http::assertSentCount(1);
    }
}
