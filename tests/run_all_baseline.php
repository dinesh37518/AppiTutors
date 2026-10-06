<?php

declare(strict_types=1);

$files = [
    'Phase 3'  => 'tests/existing-phase-tests/run_tests.php',
    'Phase 4'  => 'tests/existing-phase-tests/Phase4PublicWebsiteTest.php',
    'Phase 5'  => 'tests/existing-phase-tests/Phase5TutorWorkflowTest.php',
    'Phase 6'  => 'tests/existing-phase-tests/Phase6StudentParentTest.php',
    'Phase 7'  => 'tests/existing-phase-tests/Phase7BookingEngineTest.php',
    'Phase 8'  => 'tests/existing-phase-tests/Phase8EmailTest.php',
    'Phase 9'  => 'tests/existing-phase-tests/Phase9ManagerAdminTest.php',
    'Phase 10' => 'tests/existing-phase-tests/Phase10BlogNewsletterTest.php',
    'Phase 11' => 'tests/existing-phase-tests/Phase11SecurityPrivacyTest.php',
    'Phase 12' => 'tests/existing-phase-tests/Phase12QaUatTest.php',
];

$totalPassed = 0;
$totalExpected = 0;
$allSuccess = true;

foreach ($files as $name => $file) {
    $fullPath = __DIR__ . '/../' . $file;
    $output = shell_exec("php " . escapeshellarg($fullPath));
    if (preg_match('/TEST SUMMARY:\s*(\d+)\/(\d+)\s*PASSED/i', (string)$output, $m)) {
        $p = (int)$m[1];
        $t = (int)$m[2];
        $totalPassed += $p;
        $totalExpected += $t;
        echo sprintf("%-10s: %3d / %3d passed\n", $name, $p, $t);
        if ($p !== $t) {
            $allSuccess = false;
        }
    } elseif (preg_match('/PHASE 10 TEST RESULTS:\s*(\d+)\s*PASSED,\s*(\d+)\s*FAILED/i', (string)$output, $m)) {
        $p = (int)$m[1];
        $f = (int)$m[2];
        $t = $p + $f;
        $totalPassed += $p;
        $totalExpected += $t;
        echo sprintf("%-10s: %3d / %3d passed\n", $name, $p, $t);
        if ($f > 0) {
            $allSuccess = false;
        }
    } else {
        echo sprintf("%-10s: ERROR/UNEXPECTED OUTPUT\n", $name);
        echo substr((string)$output, -300) . "\n";
        $allSuccess = false;
    }
}

echo "===============================================\n";
echo "CUMULATIVE BASELINE: {$totalPassed} / {$totalExpected} PASSED\n";
echo "STATUS: " . ($allSuccess && $totalPassed === 500 ? "100% REGRESSION PASS (500/500)" : "FAILURES DETECTED") . "\n";
echo "===============================================\n";
exit($allSuccess && $totalPassed === 500 ? 0 : 1);
