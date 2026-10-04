<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run via CLI.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Database;
use App\Logging\Logger;
use App\Services\AuditService;
use App\Support\Env;

$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

$dbConfig = require dirname(__DIR__) . '/config/database.php';
$pdo = Database::getConnection($dbConfig);
$logger = new Logger();
$audit = new AuditService($pdo, $logger);

echo "=======================================================\n";
echo " UK TUTORING PLATFORM — MANAGER PROVISIONING TOOL\n";
echo "=======================================================\n\n";

$options = getopt('', ['email:', 'uid:', 'name:']);

$email = $options['email'] ?? null;
$uid = $options['uid'] ?? null;
$name = $options['name'] ?? null;

if (!$email || !$uid || !$name) {
    echo "Usage: php bin/create_manager.php --email <email> --uid <firebase_uid> --name <display_name>\n\n";
    echo "Example: php bin/create_manager.php --email admin@tutoringplatform.co.uk --uid ADMIN_FB_UID_123 --name \"Platform Administrator\"\n";
    exit(1);
}

try {
    $stmt = $pdo->prepare('SELECT id, role, status FROM `users` WHERE `firebase_uid` = ? OR `email` = ?');
    $stmt->execute([$uid, $email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $userId = (int) $existing['id'];
        $stmtUpdate = $pdo->prepare(
            'UPDATE `users` SET `role` = "MANAGER", `status` = "ACTIVE", `display_name` = ?, `updated_at` = UTC_TIMESTAMP() WHERE `id` = ?'
        );
        $stmtUpdate->execute([$name, $userId]);
        echo "[UPDATED] Existing user ID {$userId} promoted to MANAGER role with ACTIVE status.\n";
    } else {
        $stmtInsert = $pdo->prepare(
            'INSERT INTO `users` (`firebase_uid`, `email`, `display_name`, `role`, `status`, `email_verified_at`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, "MANAGER", "ACTIVE", UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmtInsert->execute([$uid, $email, $name]);
        $userId = (int) $pdo->lastInsertId();
        echo "[CREATED] New MANAGER user created with ID {$userId}.\n";
    }

    $audit->log(
        action: 'MANAGER_PROVISIONED_CLI',
        entityType: 'user',
        entityId: $userId,
        actorUserId: null,
        metadata: ['email' => $email, 'uid' => $uid, 'assigned_role' => 'MANAGER']
    );

    echo "\nManager account successfully provisioned.\n";
    exit(0);

} catch (Throwable $e) {
    echo "\n[ERROR] Failed to provision manager: " . $e->getMessage() . "\n";
    exit(1);
}
