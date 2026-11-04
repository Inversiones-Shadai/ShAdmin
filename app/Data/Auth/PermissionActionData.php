<?php

namespace App\Data\Auth;

class PermissionActionData
{
    public function __construct(public readonly string $code) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(code: (string) ($payload['codigo'] ?? ''));
    }

    /**
     * @return array{codigo: string}
     */
    public function toArray(): array
    {
        return [
            'codigo' => $this->code,
        ];
    }
}
