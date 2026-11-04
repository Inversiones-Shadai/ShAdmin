<?php

namespace App\Data\Auth;

use Illuminate\Http\Request;

class AuthLoginData
{
    public function __construct(
        public readonly string $name,
        public readonly string $password,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: (string) $request->input('name'),
            password: (string) $request->input('password'),
        );
    }

    /**
     * @return array{name: string, password: string}
     */
    public function toPayload(): array
    {
        return [
            'name' => $this->name,
            'password' => $this->password,
        ];
    }
}
