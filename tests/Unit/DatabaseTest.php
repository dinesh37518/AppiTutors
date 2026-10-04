<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Database;
use App\Database\Migration;
use PDO;
use RuntimeException;

class DatabaseTest
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function run(): array
    {
        $results = [];

        // Test 1: PDO Connection & MySQL 8+ version
        $results[] = $this->testConnection();

        // Test 2: Database Timezone is UTC
        $results[] = $this->testDatabaseTimezone();

        // Test 3: Charset and Collation is utf8mb4
        $results[] = $this->testCharsetAndCollation();

        // Test 4: All 12 application tables exist
        $results[] = $this->testRequiredTablesExist();

        // Test 5: InnoDB engine on all tables
        $results[] = $this->testInnoDBEngine();

        // Test 6: Migrations table has all 6 applied batches
        $results[] = $this->testMigrationsApplied();

        return $results;
    }

    private function testConnection(): array
    {
        try {
            $ver = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $isMySQL8 = version_compare($ver, '8.0.0', '>=');
            return [
                'name' => 'Database: MySQL 8+ PDO Connection',
                'passed' => $isMySQL8,
                'detail' => "Connected to MySQL {$ver}",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Database: MySQL 8+ PDO Connection',
                'passed' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function testDatabaseTimezone(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT @@session.time_zone AS tz');
            $row = $stmt->fetch();
            $tz = $row['tz'] ?? '';
            $isUtc = in_array($tz, ['+00:00', 'UTC'], true);
            return [
                'name' => 'Database: Session Timezone UTC (+00:00)',
                'passed' => $isUtc,
                'detail' => "Session timezone: {$tz}",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Database: Session Timezone UTC (+00:00)',
                'passed' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function testCharsetAndCollation(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT @@character_set_connection AS cs, @@collation_connection AS coll');
            $row = $stmt->fetch();
            $cs = $row['cs'] ?? '';
            $isUtf8mb4 = str_starts_with($cs, 'utf8mb4');
            return [
                'name' => 'Database: Connection Charset utf8mb4',
                'passed' => $isUtf8mb4,
                'detail' => "Charset: {$cs}, Collation: {$row['coll']}",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Database: Connection Charset utf8mb4',
                'passed' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function testRequiredTablesExist(): array
    {
        $expected = [
            'users', 'tutor_profiles', 'student_profiles', 'children',
            'availability_slots', 'bookings', 'booking_status_history',
            'lesson_notes', 'audit_logs', 'newsletter_subscribers',
            'blog_posts', 'migrations'
        ];

        try {
            $stmt = $this->pdo->query('SHOW TABLES');
            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $missing = array_diff($expected, $existing);
            $passed = empty($missing);

            return [
                'name' => 'Database: All 11 Domain Entities + Migrations Table Exist',
                'passed' => $passed,
                'detail' => $passed ? 'All 12 tables present' : 'Missing: ' . implode(', ', $missing),
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Database: All 11 Domain Entities + Migrations Table Exist',
                'passed' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function testInnoDBEngine(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
            $rows = $stmt->fetchAll();

            $nonInnoDB = [];
            foreach ($rows as $r) {
                $tableName = $r['TABLE_NAME'] ?? $r['table_name'] ?? 'unknown';
                $engine = strtoupper((string) ($r['ENGINE'] ?? $r['engine'] ?? ''));
                if ($engine !== 'INNODB') {
                    $nonInnoDB[] = "{$tableName} ({$engine})";
                }
            }

            return [
                'name' => 'Database: All Tables Use InnoDB Storage Engine',
                'passed' => empty($nonInnoDB),
                'detail' => empty($nonInnoDB) ? '100% InnoDB confirmed' : 'Non-InnoDB: ' . implode(', ', $nonInnoDB),
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Database: All Tables Use InnoDB Storage Engine',
                'passed' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function testMigrationsApplied(): array
    {
        try {
            $runner = new Migration($this->pdo);
            $statuses = $runner->status();

            $pending = array_filter($statuses, fn($s) => $s['status'] !== 'APPLIED');
            $passed = empty($pending) && count($statuses) === 6;

            return [
                'name' => 'Database: All 6 Migration Batches Applied',
                'passed' => $passed,
                'detail' => count($statuses) . ' migrations tracked, ' . count($pending) . ' pending',
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Database: All 6 Migration Batches Applied',
                'passed' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }
}
