<?php

namespace App\Data\Api;

use function is_array;

class ApiResponseData
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public readonly string $message,
        public readonly int $code,
        public readonly mixed $data,
        public readonly ?array $meta = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            message: (string) ($payload['message'] ?? ''),
            code: (int) ($payload['code'] ?? 0),
            data: $payload['data'] ?? null,
            meta: isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : null,
        );
    }
}
