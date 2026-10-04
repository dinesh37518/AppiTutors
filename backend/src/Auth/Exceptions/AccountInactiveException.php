<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

use Throwable;

class AccountInactiveException extends AuthenticationException
{
    private string $accountStatus;

    public function __construct(string $accountStatus, string $message = 'User account is not active.', ?Throwable $previous = null)
    {
        $this->accountStatus = $accountStatus;
        parent::__construct($message, 'ACCOUNT_INACTIVE', 403, $previous);
    }

    public function getAccountStatus(): string
    {
        return $this->accountStatus;
    }
}
