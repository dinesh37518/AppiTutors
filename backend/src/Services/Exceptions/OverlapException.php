<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use Exception;

class OverlapException extends Exception
{
    private string $errorCode;

    public function __construct(string $message = 'Availability slot overlaps with an existing slot.', string $errorCode = 'OVERLAPPING_SLOT', int $statusCode = 409, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
