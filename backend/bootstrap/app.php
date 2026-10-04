<?php

declare(strict_types=1);

// UK Tutoring Platform — Central Application Bootstrap

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

require_once APP_ROOT . '/vendor/autoload.php';

use App\Logging\Logger;
use App\Support\Env;
use App\Support\Response;

// 1. Load Environment Configuration
$envFile = dirname(__DIR__, 2) . '/.env';
if (file_exists($envFile)) {
    Env::load($envFile);
}

// 2. Set Application Timezone
$appTimezone = Env::get('APP_TIMEZONE', 'Europe/London');
date_default_timezone_set($appTimezone);

// 3. Configure Error Reporting & Exception Safety
$isDebug = (bool) Env::get('APP_DEBUG', false);
$logger = new Logger();

if ($isDebug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 4. Harden Session Cookie Configuration & Security Headers
\App\Support\Session::configure();
\App\Support\SecurityHeaders::apply();

// Global Uncaught Exception Handler
set_exception_handler(function (Throwable $e) use ($logger, $isDebug): void {
    $logger->error('Uncaught Exception: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'code' => $e->getCode(),
    ]);

    $statusCode = 500;
    $errorCode = 'INTERNAL_SERVER_ERROR';

    if ($e instanceof \App\Support\RateLimitExceededException) {
        $statusCode = 429;
        $errorCode = $e->getErrorCode();
        if (!headers_sent()) {
            header('Retry-After: ' . $e->getRetryAfter());
        }
    } elseif (method_exists($e, 'getStatusCode')) {
        $statusCode = (int) $e->getStatusCode();
    } elseif ($e->getCode() >= 400 && $e->getCode() < 600) {
        $statusCode = (int) $e->getCode();
    }

    if (method_exists($e, 'getErrorCode')) {
        $errorCode = $e->getErrorCode();
    }

    $message = $isDebug ? $e->getMessage() : ($statusCode === 429 ? $e->getMessage() : 'An unexpected server error occurred. Please try again later.');

    // Send controlled JSON response if API or web client
    Response::error($message, $errorCode, $statusCode);
});

// Global Error Handler (converts PHP warnings/notices to logged events)
set_error_handler(function (int $severity, string $message, string $file, int $line) use ($logger): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $logger->warning("PHP Error [{$severity}]: {$message}", ['file' => $file, 'line' => $line]);
    return true;
});

// Global XSS escaping helper
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
