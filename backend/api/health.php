<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/bootstrap.php';

use App\Database\Database;
use App\Support\Env;
use App\Support\Response;
use App\Support\Timezone;

try {
    $checks = [
        'php' => [
            'status' => 'OK',
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
        ],
        'environment' => [
            'status' => Env::isLoaded() ? 'OK' : 'DEFAULT',
            'app_env' => Env::get('APP_ENV', 'local'),
            'timezone' => Env::get('APP_TIMEZONE', 'Europe/London'),
            'current_time_london' => date('Y-m-d H:i:s'),
            'current_time_utc' => Timezone::nowUtc(),
        ],
        'database' => [
            'status' => 'UNKNOWN',
            'engine' => 'MySQL',
        ],
        'firebase' => [
            'status' => 'UNKNOWN',
            'project_id_configured' => !empty(Env::get('FIREBASE_PROJECT_ID')),
            'credentials_file_configured' => !empty(Env::get('FIREBASE_CREDENTIALS_PATH')),
            'credentials_file_exists' => false,
        ],
        'storage' => [
            'status' => 'OK',
            'writable' => (function() {
                $appRoot = defined('APP_ROOT') ? APP_ROOT : (file_exists(dirname(__DIR__, 2) . '/storage') ? dirname(__DIR__, 2) : dirname(__DIR__));
                $testFile = $appRoot . '/storage/logs/.write_test_' . uniqid();
                $ok = (@file_put_contents($testFile, '1') !== false);
                if ($ok) { @unlink($testFile); }
                return $ok;
            })(),
        ],
    ];

    // Verify Database Connection
    try {
        $pdo = Database::getConnection();
        $dbVer = $pdo->query('SELECT VERSION() AS ver, @@global.time_zone AS tz')->fetch();
        $tableCount = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();

        $checks['database']['status'] = 'OK';
        $checks['database']['version'] = $dbVer['ver'] ?? 'Unknown';
        $checks['database']['global_timezone'] = $dbVer['tz'] ?? 'Unknown';
        $checks['database']['tables_installed'] = $tableCount;
    } catch (Throwable $dbErr) {
        $checks['database']['status'] = 'FAIL';
        $checks['database']['error'] = 'Database connection failed';
    }

    // Verify Firebase Configuration Presence (NEVER EXPOSE SECRETS)
    $credsPath = (string) Env::get('FIREBASE_CREDENTIALS_PATH', '');
    $appRoot = defined('APP_ROOT') ? APP_ROOT : (file_exists(dirname(__DIR__, 2) . '/storage') ? dirname(__DIR__, 2) : dirname(__DIR__));
    $fullCredsPath = str_starts_with($credsPath, '/') || preg_match('/^[a-zA-Z]:/', $credsPath)
        ? $credsPath
        : $appRoot . '/' . ltrim($credsPath, '/\\');

    $credsExist = !empty($credsPath) && file_exists($fullCredsPath);
    $checks['firebase']['credentials_file_exists'] = $credsExist;
    $checks['firebase']['status'] = $checks['firebase']['project_id_configured'] ? 'CONFIGURED' : 'UNCONFIGURED';

    if ($credsExist) {
        try {
            $verifier = new \App\Auth\FirebaseTokenVerifier();
            $auth = $verifier->getFirebaseAuth();
            $checks['firebase']['admin_sdk_initialized'] = ($auth instanceof \Kreait\Firebase\Contract\Auth);
        } catch (\Throwable $fbErr) {
            $checks['firebase']['admin_sdk_initialized'] = false;
        }
    }

    $isHealthy = $checks['database']['status'] === 'OK';
    $statusCode = $isHealthy ? 200 : 503;

    Response::success($checks, $statusCode, ['timestamp' => Timezone::nowUtc()]);

} catch (Throwable $e) {
    Response::error('Health check failed: ' . $e->getMessage(), 'HEALTH_CHECK_FAILED', 503);
}
