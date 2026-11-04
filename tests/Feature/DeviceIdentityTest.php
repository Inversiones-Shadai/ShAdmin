<?php

namespace Tests\Feature;

use App\Services\Api\DeviceIdentity;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceIdentityTest extends TestCase
{
    public function test_the_web_instance_uses_its_registered_identity_without_external_requests(): void
    {
        config(['services.shadmin_api.device_ip' => '203.0.113.25', 'services.shadmin_api.device_name' => 'SUCURSAL-WEB']);
        $identity = app(DeviceIdentity::class);
        $this->assertSame('203.0.113.25', $identity->ipAddress());
        $this->assertSame('SUCURSAL-WEB', $identity->deviceName());
        Http::assertNothingSent();
    }

    public function test_missing_configuration_uses_request_ip_and_a_stable_web_name(): void
    {
        config(['services.shadmin_api.device_ip' => null, 'services.shadmin_api.device_name' => null]);
        request()->server->set('REMOTE_ADDR', '198.51.100.42');

        $identity = app(DeviceIdentity::class);
        $this->assertSame('198.51.100.42', $identity->ipAddress());
        $this->assertSame('SHADMIN-WEB', $identity->deviceName());
        Http::assertNothingSent();
    }
}
