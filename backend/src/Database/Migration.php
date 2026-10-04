<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class Migration
{
    private PDO $pdo;
    private string $migrationsPath;

    public function __construct(PDO $pdo, ?string $migrationsPath = null)
    {
        $this->pdo = $pdo;
        $defaultPath = is_dir(dirname(__DIR__, 3) . '/database/migrations')
            ? dirname(__DIR__, 3) . '/database/migrations'
            : dirname(__DIR__, 2) . '/database/migrations';
        $this->migrationsPath = $migrationsPath ?? $defaultPath;
    }

    /**
     * Create the database if it doesn't already exist.
     * Connects without selecting a database first.
     */
    public static function createDatabaseIfNotExists(array $config): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $dbname = $config['database'] ?? 'tutoring_platform_dev';
        $user = $config['username'] ?? 'root';
        $pass = $config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    }

    /**
     * Ensure the migrations tracking table exists.
     */
    public function ensureMigrationTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL,
            `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->pdo->exec($sql);
    }

    /**
     * Run all pending migrations.
     *
     * @return array List of migrated filenames
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();

        $applied = $this->getAppliedMigrations();
        $files = $this->getMigrationFiles();

        $stmtBatch = $this->pdo->query("SELECT IFNULL(MAX(batch), 0) + 1 AS next_batch FROM `migrations`");
        $nextBatch = (int) $stmtBatch->fetch()['next_batch'];

        $migrated = [];

        foreach ($files as $file) {
            $migrationName = basename($file);

            if (in_array($migrationName, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Failed to read migration file: {$file}");
            }

            try {
                $this->pdo->exec($sql);

                $stmt = $this->pdo->prepare("INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, ?)");
                $stmt->execute([$migrationName, $nextBatch]);

                $migrated[] = $migrationName;
            } catch (PDOException $e) {
                throw new RuntimeException("Migration failed in [{$migrationName}]: " . $e->getMessage(), 0, $e);
            }
        }

        return $migrated;
    }

    /**
     * Get list of all migration status (applied vs pending).
     *
     * @return array
     */
    public function status(): array
    {
        $this->ensureMigrationTable();

        $appliedMap = [];
        $stmt = $this->pdo->query("SELECT migration, batch, executed_at FROM `migrations` ORDER BY id ASC");
        while ($row = $stmt->fetch()) {
            $appliedMap[$row['migration']] = $row;
        }

        $files = $this->getMigrationFiles();
        $statusList = [];

        foreach ($files as $file) {
            $name = basename($file);
            $isApplied = isset($appliedMap[$name]);

            $statusList[] = [
                'migration' => $name,
                'status' => $isApplied ? 'APPLIED' : 'PENDING',
                'batch' => $isApplied ? (int) $appliedMap[$name]['batch'] : null,
                'executed_at' => $isApplied ? $appliedMap[$name]['executed_at'] : null,
            ];
        }

        return $statusList;
    }

    /**
     * Get all migration files sorted alphabetically.
     *
     * @return array
     */
    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob("{$this->migrationsPath}/*.sql");
        if ($files === false) {
            return [];
        }

        sort($files);
        return $files;
    }

    /**
     * Get list of already applied migration filenames.
     *
     * @return array
     */
    private function getAppliedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM `migrations`");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
