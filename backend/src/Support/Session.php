<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Session — Centralized Secure Session and Cookie Lifecycle Manager.
 *
 * Implements PHP official security recommendations:
 * - Strict session mode (prevents uninitialized session ID adoption)
 * - Cookie HttpOnly (neutralizes script access)
 * - Cookie SameSite=Lax (mitigates CSRF leakage)
 * - Cookie Secure (when running over HTTPS)
 * - Inactivity timeout enforcement (defaults to 1800s / 30m)
 * - Session fixation prevention through ID regeneration
 */
class Session
{
    private static bool $configured = false;
    public const DEFAULT_TIMEOUT_SECONDS = 1800; // 30 minutes

    /**
     * Configure PHP session ini and cookie parameters defensively.
     */
    public static function configure(): void
    {
        if (self::$configured || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = SecurityHeaders::isHttps();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');

        session_set_cookie_params([
            'lifetime' => 0, // Session-scoped cookie
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        self::$configured = true;
    }

    /**
     * Inspect intended session cookie configuration parameters.
     *
     * In local plain HTTP development, cookie_secure is false ('0') so that local test sessions
     * function without requiring local TLS certificates.
     * In HTTPS / production environments, cookie_secure is true ('1'), ensuring browser cookie transmission
     * is strictly restricted to encrypted TLS connections.
     *
     * @param bool|null $simulateHttps Optional override for testing environmental behavior
     * @return array
     */
    public static function getCookieConfig(?bool $simulateHttps = null): array
    {
        $isHttps = ($simulateHttps !== null) ? $simulateHttps : SecurityHeaders::isHttps();
        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
            'strict_mode' => true,
            'use_only_cookies' => true,
            'is_https_enforced' => $isHttps,
            'environment_note' => $isHttps
                ? 'Production/HTTPS active: Secure cookie attribute is strictly enforced.'
                : 'Local plain HTTP development: Secure cookie attribute is deliberately omitted to prevent session breakage on unencrypted localhost.',
        ];
    }

    /**
     * Start or resume session with inactivity timeout enforcement.
     *
     * @param int $timeoutSeconds Maximum allowed inactivity duration before expiration
     */
    public static function start(int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS): void
    {
        self::configure();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $now = time();
        if (isset($_SESSION['_last_activity'])) {
            if ($now - (int) $_SESSION['_last_activity'] > $timeoutSeconds) {
                // Session expired due to inactivity: clear data and assign fresh session ID
                $_SESSION = [];
                self::regenerate(true);
            }
        }
        $_SESSION['_last_activity'] = $now;
    }

    /**
     * Regenerate session ID to prevent session fixation attacks (e.g. after login or privilege elevation).
     *
     * @param bool $deleteOldSession
     * @return bool
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return @session_regenerate_id($deleteOldSession);
        }
        return false;
    }

    /**
     * Terminate and purge session data and remove the session cookie.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
        }
    }
}
