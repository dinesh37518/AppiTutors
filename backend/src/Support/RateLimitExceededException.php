<?php

declare(strict_types=1);

namespace App\Support;

use Exception;

class RateLimitExceededException extends Exception
{
    private int $retryAfter;
    private string $errorCode;

    public function __construct(string $message = 'Too many requests. Please slow down.', int $retryAfter = 60, string $errorCode = 'RATE_LIMIT_EXCEEDED')
    {
        parent::__construct($message, 429);
        $this->retryAfter = $retryAfter;
        $this->errorCode = $errorCode;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return 429;
    }
}
