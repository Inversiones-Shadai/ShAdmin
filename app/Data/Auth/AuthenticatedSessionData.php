<?php

namespace App\Data\Auth;

use App\Support\Auth\ApiPermissionIndex;
use Illuminate\Support\Collection;

use function is_array;

class AuthenticatedSessionData
{
    /**
     * @param  Collection<int, PermissionModuleData>  $permissions
     */
    public function __construct(
        public readonly string $token,
        public readonly string $userName,
        public readonly string $role,
        public readonly string $stockName,
        public readonly int $stockId,
        public readonly int $deviceId,
        public readonly Collection $permissions,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $permissions = is_array($payload['spermisos'] ?? null) ? $payload['spermisos'] : [];

        return new self(
            token: (string) ($payload['token'] ?? ''),
            userName: (string) ($payload['uname'] ?? ''),
            role: (string) ($payload['urol'] ?? ''),
            stockName: (string) ($payload['stockname'] ?? ''),
            stockId: (int) ($payload['stockid'] ?? 0),
            deviceId: (int) ($payload['deviceid'] ?? 0),
            permissions: collect($permissions)
                ->filter(fn ($permission) => is_array($permission))
                ->map(fn (array $permission) => PermissionModuleData::fromApi($permission))
                ->values(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toSession(): array
    {
        $permissions = $this->permissions
            ->map(fn (PermissionModuleData $permission) => $permission->toArray())
            ->values()
            ->all();

        return [
            'token' => $this->token,
            'uname' => $this->userName,
            'urol' => $this->role,
            'stockname' => $this->stockName,
            'stockid' => $this->stockId,
            'deviceid' => $this->deviceId,
            'spermisos' => $permissions,
            'abilities' => ApiPermissionIndex::build($permissions),
        ];
    }
}
