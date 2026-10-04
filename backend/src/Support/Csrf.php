<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Robust Anti-CSRF Token Generation and Timing-Safe Verification.
 */
class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Generate or retrieve an active session CSRF token.
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    /**
     * Timing-safe verification of submitted CSRF token.
     */
    public static function validateToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::SESSION_KEY], (string) $token);
    }
}
