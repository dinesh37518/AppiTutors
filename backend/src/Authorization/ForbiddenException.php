<?php

declare(strict_types=1);

namespace App\Authorization;

use RuntimeException;
use Throwable;

class ForbiddenException extends RuntimeException
{
    private string $errorCode;

    public function __construct(string $message = 'Access forbidden.', string $errorCode = 'FORBIDDEN', ?Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, 403, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
