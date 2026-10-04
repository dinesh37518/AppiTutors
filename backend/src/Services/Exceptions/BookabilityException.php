<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use Exception;

class BookabilityException extends Exception
{
    private string $errorCode;

    public function __construct(string $message = 'Tutor is not bookable.', string $errorCode = 'TUTOR_NOT_BOOKABLE', int $statusCode = 403, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
