<?php

namespace App\Exceptions;

class ApiSessionExpiredException extends ApiOperationException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Tu sesión expiró. Inicia sesión nuevamente.',
            status: 401,
        );
    }
}
