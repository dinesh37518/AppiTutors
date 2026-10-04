<?php

declare(strict_types=1);

namespace App\Logging;

use RuntimeException;

class Logger
{
    private string $logPath;

    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'id_token', 'access_token',
        'refresh_token', 'secret', 'private_key', 'authorization', 'api_key',
        'credit_card', 'cvv', 'card_number', 'unsubscribe_token', 'confirmation_token',
        'unsubscribe_token_hash', 'confirmation_token_hash', 'certificate_number',
        'dbs_certificate', 'dbs_certificate_number', 'dbs_document', 'service_account',
        'private_key_id', 'auth_token', 'bearer', 'session_id', 'client_secret', 'secret_key'
    ];

    private static ?self $defaultInstance = null;

    private static function getDefaultInstance(): self
    {
        if (self::$defaultInstance === null) {
            self::$defaultInstance = new self();
        }
        return self::$defaultInstance;
    }

    public function __construct(?string $logPath = null)
    {
        $rootDir = dirname(__DIR__, 3);
        $defaultLogPath = is_dir($rootDir . '/storage/logs')
            ? $rootDir . '/storage/logs'
            : dirname(__DIR__, 2) . '/storage/logs';
        $this->logPath = $logPath ?? $defaultLogPath;
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Log an informational message.
     */
    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::getDefaultInstance()->write('INFO', $message, $context, $channel);
    }

    /**
     * Log a warning message.
     */
    public static function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        self::getDefaultInstance()->write('WARNING', $message, $context, $channel);
    }

    /**
     * Log an error message.
     */
    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::getDefaultInstance()->write('ERROR', $message, $context, $channel);
    }

    /**
     * Log a security or authentication event.
     */
    public static function security(string $message, array $context = []): void
    {
        self::getDefaultInstance()->write('SECURITY', $message, $context, 'security');
    }

    /**
     * Write formatted log entry with secret redaction.
     */
    public function write(string $level, string $message, array $context, string $channel): void
    {
        $date = date('Y-m-d');
        $timestamp = gmdate('Y-m-d H:i:s \U\T\C');
        $fileName = "{$this->logPath}/{$channel}-{$date}.log";

        $cleanContext = self::redactSensitiveData($context);
        $contextString = !empty($cleanContext) ? ' ' . json_encode($cleanContext, JSON_UNESCAPED_SLASHES) : '';

        $line = "[{$timestamp}] [{$level}] {$message}{$contextString}" . PHP_EOL;

        @file_put_contents($fileName, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Redact known sensitive keys and credentials recursively.
     */
    public static function redactSensitiveData(array $data): array
    {
        $redacted = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, self::SENSITIVE_KEYS, true)) {
                $redacted[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $redacted[$key] = self::redactSensitiveData($value);
            } else {
                $redacted[$key] = $value;
            }
        }
        return $redacted;
    }
}
