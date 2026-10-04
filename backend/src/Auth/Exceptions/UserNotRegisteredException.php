<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

use Throwable;

class UserNotRegisteredException extends AuthenticationException
{
    private string $firebaseUid;
    private ?string $email;

    public function __construct(string $firebaseUid, ?string $email = null, string $message = 'User has authenticated with Firebase but has no platform account.', ?Throwable $previous = null)
    {
        $this->firebaseUid = $firebaseUid;
        $this->email = $email;
        parent::__construct($message, 'USER_NOT_REGISTERED', 404, $previous);
    }

    public function getFirebaseUid(): string
    {
        return $this->firebaseUid;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
