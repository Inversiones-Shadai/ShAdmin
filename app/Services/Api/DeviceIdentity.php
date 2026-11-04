<?php

namespace App\Services\Api;

use App\Exceptions\ApiConfigurationException;

use function is_string;

class DeviceIdentity
{
    public function appVersion(): string
    {
        return (string) config('services.shadmin_api.app_version', '1.0.0');
    }

    public function ipAddress(): string
    {
        $configuredIp = config('services.shadmin_api.device_ip');
        $ip = is_string($configuredIp) && trim($configuredIp) !== ''
            ? trim($configuredIp)
            : request()->ip();

        if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new ApiConfigurationException('No se pudo determinar una IP válida para el dispositivo.');
        }

        return $ip;
    }

    public function deviceName(): string
    {
        $configuredName = config('services.shadmin_api.device_name');

        if (is_string($configuredName) && trim($configuredName) !== '') {
            return trim($configuredName);
        }

        return 'SHADMIN-WEB';
    }
}
