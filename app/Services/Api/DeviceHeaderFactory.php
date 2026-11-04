<?php

namespace App\Services\Api;

class DeviceHeaderFactory
{
    public function __construct(
        private readonly DeviceIdentity $identity,
        private readonly ApiValueHasher $hasher,
    ) {}

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'App-Version' => $this->hasher->hash($this->identity->appVersion()),
            'X-Device-Ip' => $this->hasher->hash($this->identity->ipAddress()),
            'X-Device-Name' => $this->hasher->hash($this->identity->deviceName()),
        ];
    }
}
