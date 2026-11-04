<?php

namespace Tests\Feature;

use App\Services\Api\ApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_client_sends_hashed_device_headers(): void
    {
        config([
            'services.shadmin_api.app_version' => '2.5.0',
            'services.shadmin_api.base_url' => 'https://api.example.test',
            'services.shadmin_api.device_header_secret' => 'test-secret',
            'services.shadmin_api.device_name' => 'DESKTOP-TEST',
        ]);

        Http::fake([
            'api.example.test/*' => Http::response(['ok' => true]),
        ]);

        app(ApiClient::class)->request()->get('/health');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.example.test/health'
                && $request->hasHeader('App-Version', hash_hmac('sha256', '2.5.0', 'test-secret'))
                && $request->hasHeader('X-Device-Ip', hash_hmac('sha256', '10.10.0.25', 'test-secret'))
                && $request->hasHeader('X-Device-Name', hash_hmac('sha256', 'DESKTOP-TEST', 'test-secret'))
                && ! $request->hasHeader('App-Version', '2.5.0')
                && ! $request->hasHeader('X-Device-Ip', '10.10.0.25')
                && ! $request->hasHeader('X-Device-Name', 'DESKTOP-TEST');
        });
    }
}
