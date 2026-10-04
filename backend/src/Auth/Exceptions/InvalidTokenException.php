<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

use Throwable;

class InvalidTokenException extends AuthenticationException
{
    public function __construct(string $message = 'Invalid or expired authentication token.', ?Throwable $previous = null)
    {
        parent::__construct($message, 'INVALID_TOKEN', 401, $previous);
    }
}
