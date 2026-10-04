<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Database;
use App\Database\Migration;
use App\Support\Env;

// 1. Load Environment Configuration
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
} else {
    echo "[WARN] .env not found, using default configuration values.\n";
}

$dbConfig = require dirname(__DIR__) . '/config/database.php';

echo "=======================================================\n";
echo " UK TUTORING PLATFORM — DATABASE MIGRATION RUNNER\n";
echo "=======================================================\n";
echo "Target Host: {$dbConfig['host']}:{$dbConfig['port']}\n";
echo "Target DB:   {$dbConfig['database']}\n";
echo "User:        {$dbConfig['username']}\n";
echo "Timezone:    {$dbConfig['timezone']}\n\n";

try {
    // 2. Ensure database exists
    echo "1. Checking database existence...\n";
    Migration::createDatabaseIfNotExists($dbConfig);
    echo "   [OK] Database `{$dbConfig['database']}` verified.\n\n";

    // 3. Connect to database
    echo "2. Connecting via PDO...\n";
    $pdo = Database::getConnection($dbConfig);
    echo "   [OK] Connected successfully to MySQL " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n\n";

    $runner = new Migration($pdo);

    // 4. Handle CLI arguments
    $isStatusOnly = in_array('--status', $argv, true);

    if ($isStatusOnly) {
        echo "Migration Status:\n";
        echo str_repeat('-', 75) . "\n";
        printf("%-50s %-10s %-6s\n", "Migration", "Status", "Batch");
        echo str_repeat('-', 75) . "\n";
        foreach ($runner->status() as $row) {
            printf("%-50s %-10s %-6s\n", $row['migration'], $row['status'], $row['batch'] ?? '-');
        }
        echo str_repeat('-', 75) . "\n";
        exit(0);
    }

    echo "3. Executing pending migrations...\n";
    $migrated = $runner->migrate();

    if (empty($migrated)) {
        echo "   [INFO] Nothing to migrate. All migrations are already up to date.\n";
    } else {
        foreach ($migrated as $file) {
            echo "   [MIGRATED] {$file}\n";
        }
        echo "\n   [OK] Successfully executed " . count($migrated) . " migration(s).\n";
    }

    echo "\n=======================================================\n";
    echo " MIGRATIONS COMPLETE\n";
    echo "=======================================================\n";

} catch (Throwable $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
