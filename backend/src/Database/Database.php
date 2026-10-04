<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get the PDO connection singleton.
     *
     * @param array|null $config Optional custom config array (for tests/migrations)
     * @return PDO
     * @throws RuntimeException
     */
    public static function getConnection(?array $config = null): PDO
    {
        if (self::$instance !== null && $config === null) {
            return self::$instance;
        }

        $cfgFile = file_exists(dirname(__DIR__, 2) . '/config/database.php')
            ? dirname(__DIR__, 2) . '/config/database.php'
            : dirname(__DIR__, 3) . '/config/database.php';
        $cfg = $config ?? require $cfgFile;

        $host = $cfg['host'] ?? '127.0.0.1';
        $port = $cfg['port'] ?? 3306;
        $dbname = $cfg['database'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $user = $cfg['username'] ?? 'root';
        $pass = $cfg['password'] ?? '';
        $timezone = $cfg['timezone'] ?? '+00:00';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = $cfg['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            // Enforce explicit UTC timezone per connection
            $pdo->exec("SET time_zone = '{$timezone}'");

            if ($config === null) {
                self::$instance = $pdo;
            }

            return $pdo;
        } catch (PDOException $e) {
            // Mask raw credentials or internal database strings from leaking
            throw new RuntimeException("Database connection failure: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Reset the active connection (useful in test teardown or process restarts).
     */
    public static function resetConnection(): void
    {
        self::$instance = null;
    }

    /**
     * Set a custom PDO instance (e.g. for testing / mocks).
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
