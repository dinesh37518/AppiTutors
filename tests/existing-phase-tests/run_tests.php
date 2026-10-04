<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Database\Database;
use Tests\AuthTest;
use Tests\AuthorizationTest;
use Tests\ConfigTest;
use Tests\DatabaseTest;

echo "=======================================================\n";
echo " UK TUTORING PLATFORM — PHASE 3 FOUNDATION TEST SUITE\n";
echo "=======================================================\n\n";

$pdo = Database::getConnection();

$suites = [
    'Database Foundation' => new DatabaseTest($pdo),
    'Authentication & Identity Foundation' => new AuthTest($pdo),
    'Authorization & RBAC Security Foundation' => new AuthorizationTest(),
    'Configuration, Timezone & Privacy Foundation' => new ConfigTest(),
];

$totalTests = 0;
$totalPassed = 0;
$totalFailed = 0;

foreach ($suites as $suiteName => $suite) {
    echo "--- {$suiteName} ---\n";
    $results = $suite->run();

    foreach ($results as $res) {
        $totalTests++;
        $status = $res['passed'] ? '[ PASS ]' : '[ FAIL ]';
        if ($res['passed']) {
            $totalPassed++;
        } else {
            $totalFailed++;
        }

        printf("%-8s %-60s\n", $status, $res['name']);
        if (!empty($res['detail'])) {
            echo "         Detail: {$res['detail']}\n";
        }
    }
    echo "\n";
}

echo "=======================================================\n";
echo " TEST SUMMARY: {$totalPassed}/{$totalTests} PASSED (" . round(($totalPassed / max(1, $totalTests)) * 100) . "%)\n";
if ($totalFailed > 0) {
    echo " STATUS: {$totalFailed} TEST(S) FAILED.\n";
    echo "=======================================================\n";
    exit(1);
} else {
    echo " STATUS: ALL FOUNDATIONAL TESTS PASSED CLEANLY!\n";
    echo "=======================================================\n";
    exit(0);
}
