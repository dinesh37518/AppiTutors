<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use Exception;

class ValidationException extends Exception
{
    private string $errorCode;
    private array $details;

    public function __construct(string $message = 'Validation error occurred.', string $errorCode = 'VALIDATION_ERROR', int $statusCode = 422, array $details = [], ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        $this->details = $details;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
