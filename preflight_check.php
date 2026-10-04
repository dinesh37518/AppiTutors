<?php
/**
 * Local Development Environment Preflight Verification Script
 * UK Tutoring Platform — Phase 00 Preflight Check
 */

echo "=======================================================\n";
echo " PHASE 00: LOCAL WINDOWS PREFLIGHT VERIFICATION\n";
echo "=======================================================\n\n";

$allPassed = true;

function reportCheck(string $label, bool $status, string $detail = ''): void {
    global $allPassed;
    if ($status) {
        echo "[ PASS ] $label" . ($detail ? " ($detail)" : "") . "\n";
    } else {
        echo "[ FAIL ] $label" . ($detail ? " ($detail)" : "") . "\n";
        $allPassed = false;
    }
}

// 1. PHP Version
$phpVersion = PHP_VERSION;
$phpMajor = (int) PHP_MAJOR_VERSION;
reportCheck(
    "PHP 8.x Version",
    $phpMajor >= 8,
    "Detected PHP $phpVersion"
);

// 2. Required PHP Extensions
$requiredExtensions = [
    'pdo'        => 'PDO',
    'pdo_mysql'  => 'PDO MySQL Driver',
    'openssl'    => 'OpenSSL',
    'mbstring'   => 'Multibyte String',
    'curl'       => 'cURL',
    'json'       => 'JSON',
    'fileinfo'   => 'Fileinfo'
];

foreach ($requiredExtensions as $ext => $name) {
    reportCheck("PHP Extension: $name ($ext)", extension_loaded($ext));
}

// 3. MySQL 8+ PDO Connectivity
$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';

try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3
    ]);

    $stmt = $pdo->query("SELECT VERSION() AS ver, @@global.time_zone AS tz");
    $dbInfo = $stmt->fetch();
    $versionStr = $dbInfo['ver'] ?? 'Unknown';
    $isMySQL8 = version_compare($versionStr, '8.0.0', '>=');
    $isMariaDB = stripos($versionStr, 'MariaDB') !== false;

    if ($isMariaDB) {
        reportCheck("MySQL 8+ Database Engine", false, "Detected MariaDB ($versionStr), MySQL 8+ required");
    } else {
        reportCheck("MySQL 8+ Database Engine", $isMySQL8, "Detected MySQL $versionStr");
    }

    reportCheck(
        "MySQL UTC Timestamp Configuration",
        in_array($dbInfo['tz'], ['+00:00', 'UTC', 'SYSTEM']),
        "Global timezone: " . ($dbInfo['tz'] ?? 'unset')
    );

} catch (PDOException $e) {
    reportCheck("MySQL 8+ PDO Connectivity", false, "Connection error: " . $e->getMessage());
}

// 4. CLI Tools in PATH
$cliTools = [
    'git'      => '--version',
    'node'     => '--version',
    'npm'      => '--version',
    'composer' => '--version'
];

foreach ($cliTools as $tool => $arg) {
    $output = [];
    $returnVar = 0;
    @exec("$tool $arg 2>&1", $output, $returnVar);
    $firstLine = !empty($output) ? trim($output[0]) : '';
    reportCheck("CLI Tool: $tool", $returnVar === 0, $firstLine ?: 'Command not found in PATH');
}

echo "\n=======================================================\n";
if ($allPassed) {
    echo " ALL PREFLIGHT CHECKS PASSED SUCCESSFULLY!\n";
    echo " Environment is ready for Phase 01.\n";
    echo "=======================================================\n";
    exit(0);
} else {
    echo " PREFLIGHT CHECKS FAILED. PLEASE RESOLVE ISSUES ABOVE.\n";
    echo "=======================================================\n";
    exit(1);
}
