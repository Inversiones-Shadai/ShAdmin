<?php

namespace App\Exceptions;

use RuntimeException;

class ApiOperationException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly int $status = 0,
    ) {
        parent::__construct($message, $status);
    }
}
