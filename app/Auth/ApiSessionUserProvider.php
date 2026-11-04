<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;

class ApiSessionUserProvider implements UserProvider
{
    private readonly EloquentUserProvider $local;

    public function __construct(Hasher $hasher)
    {
        $this->local = new EloquentUserProvider($hasher, User::class);
    }

    /** @return class-string<User> */
    public function getModel(): string
    {
        return User::class;
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        if (! $this->usesApi()) {
            return $this->local->retrieveById($identifier);
        }

        $identity = session('auth.api');
        if (! is_array($identity) || ! is_string($identity['token'] ?? null)
            || $identity['token'] === '' || (string) ($identity['web_user_id'] ?? '') !== (string) $identifier) {
            return null;
        }

        return (new User)->forceFill([
            'id' => $identity['web_user_id'],
            'name' => (string) ($identity['uname'] ?? ''),
            'email' => '',
        ]);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return $this->usesApi() ? null : $this->local->retrieveByToken($identifier, $token);
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        if (! $this->usesApi() && $user instanceof Model) {
            $this->local->updateRememberToken($user, $token);
        }
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        return $this->usesApi() ? null : $this->local->retrieveByCredentials($credentials);
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        return ! $this->usesApi() && $this->local->validateCredentials($user, $credentials);
    }

    /** @param array<string, mixed> $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        if (! $this->usesApi() && $user instanceof Model) {
            $this->local->rehashPasswordIfRequired($user, $credentials, $force);
        }
    }

    private function usesApi(): bool
    {
        return filled(config('services.shadmin_api.base_url'));
    }
}
