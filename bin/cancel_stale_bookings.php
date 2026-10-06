<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run via CLI.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/backend/bootstrap/bootstrap.php';

use App\Database\Database;
use App\Logging\Logger;
use App\Services\AuditService;
use App\Services\BookingService;

$options = getopt('', ['hours::']);
$hours = isset($options['hours']) ? (int) $options['hours'] : 24;
if ($hours <= 0) {
    $hours = 24;
}

echo "=======================================================\n";
echo " UK TUTORING PLATFORM — 24-HOUR AUTO-CANCELLATION TOOL\n";
echo " Threshold: Bookings awaiting response > {$hours} hours\n";
echo "=======================================================\n\n";

try {
    $pdo = Database::getConnection();
    $logger = new Logger();
    $audit = new AuditService($pdo, $logger);
    $bookingService = new BookingService($pdo, $logger, $audit);

    $cancelled = $bookingService->autoCancelStaleBookings($hours);

    echo "[COMPLETED] Processed auto-cancellations.\n";
    echo "Total stale bookings cancelled to SYSTEM_CANCELLED: {$cancelled}\n";
    exit(0);
} catch (Throwable $e) {
    echo "\n[ERROR] Stale booking cancellation failure: " . $e->getMessage() . "\n";
    exit(1);
}
