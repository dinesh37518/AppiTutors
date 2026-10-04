<?php

declare(strict_types=1);

namespace App\Support;

/**
 * RateLimiter — Local, File-Backed Rate Limiting Mechanism.
 *
 * Implements token/counter window rate limiting without external Redis dependencies.
 * Bucket state is persisted securely in private storage outside public web root.
 */
class RateLimiter
{
    private static ?bool $enabledOverride = null;
    private static ?string $storageDirOverride = null;

    /**
     * Get the directory where rate limit counters are saved.
     */
    public static function getStorageDir(): string
    {
        if (self::$storageDirOverride !== null) {
            return self::$storageDirOverride;
        }

        $rootDir = dirname(__DIR__, 3);
        $dir = is_dir($rootDir . '/storage/private')
            ? $rootDir . '/storage/private/rate_limits'
            : dirname(__DIR__, 2) . '/storage/private/rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }

    public static function setStorageDir(?string $dir): void
    {
        self::$storageDirOverride = $dir;
    }

    public static function setEnabled(?bool $enabled): void
    {
        self::$enabledOverride = $enabled;
    }

    public static function isEnabled(): bool
    {
        if (self::$enabledOverride !== null) {
            return self::$enabledOverride;
        }
        return Env::get('RATE_LIMIT_ENABLED', 'true') !== 'false';
    }

    /**
     * Resolve the client identifier (IP address).
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_TEST_IP'])) {
            return trim((string) $_SERVER['HTTP_X_TEST_IP']);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return trim((string) $ip);
    }

    /**
     * Check rate limit status for a given action and client identifier.
     *
     * @param string $action Name of action (e.g. 'auth', 'contact', 'newsletter')
     * @param string $identifier Unique client identifier (e.g. IP address or user ID)
     * @param int $maxAttempts Maximum allowed attempts in window
     * @param int $decaySeconds Window duration in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int, 'reset_at' => int]
     */
    public static function check(string $action, string $identifier, int $maxAttempts, int $decaySeconds): array
    {
        if (!self::isEnabled()) {
            return [
                'allowed' => true,
                'remaining' => $maxAttempts,
                'retry_after' => 0,
                'reset_at' => time() + $decaySeconds,
            ];
        }

        $key = hash('sha256', "{$action}:{$identifier}");
        $file = self::getStorageDir() . "/rl_{$key}.json";
        $now = time();

        $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $parsed = $raw ? json_decode($raw, true) : null;
            if (is_array($parsed) && isset($parsed['reset_at']) && $parsed['reset_at'] > $now) {
                $data = $parsed;
            }
        }

        $data['count'] = (int) ($data['count'] ?? 0) + 1;
        $allowed = $data['count'] <= $maxAttempts;
        $remaining = max(0, $maxAttempts - $data['count']);
        $retryAfter = max(1, $data['reset_at'] - $now);

        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'retry_after' => $allowed ? 0 : $retryAfter,
            'reset_at' => $data['reset_at'],
        ];
    }

    /**
     * Enforce rate limit. Throws RateLimitExceededException (HTTP 429) if exceeded.
     *
     * @param string $action
     * @param string|null $identifier Defaults to client IP
     * @param int $maxAttempts
     * @param int $decaySeconds
     * @throws RateLimitExceededException
     */
    public static function enforce(string $action, ?string $identifier = null, int $maxAttempts = 10, int $decaySeconds = 60): void
    {
        $id = $identifier ?? self::getClientIp();
        $result = self::check($action, $id, $maxAttempts, $decaySeconds);

        if (!$result['allowed']) {
            if (!headers_sent()) {
                header('Retry-After: ' . $result['retry_after']);
            }
            throw new RateLimitExceededException(
                "Too many requests for '{$action}'. Please retry in {$result['retry_after']} seconds.",
                $result['retry_after'],
                'RATE_LIMIT_EXCEEDED'
            );
        }
    }

    /**
     * Reset rate limit bucket for a specific action and client.
     */
    public static function reset(string $action, string $identifier): void
    {
        $key = hash('sha256', "{$action}:{$identifier}");
        $file = self::getStorageDir() . "/rl_{$key}.json";
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Purge all rate limit files (e.g. for testing cleanups).
     */
    public static function clearAll(): void
    {
        $dir = self::getStorageDir();
        if (is_dir($dir)) {
            $files = glob($dir . '/rl_*.json');
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
        }
    }
}
