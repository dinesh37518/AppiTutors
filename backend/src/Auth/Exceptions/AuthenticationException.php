<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

use RuntimeException;
use Throwable;

class AuthenticationException extends RuntimeException
{
    protected string $errorCode;

    public function __construct(string $message = 'Authentication failed', string $errorCode = 'AUTHENTICATION_FAILED', int $statusCode = 401, ?Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
