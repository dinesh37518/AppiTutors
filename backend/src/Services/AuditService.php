<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Logging\Logger;
use PDO;
use Throwable;

class AuditService
{
    private PDO $pdo;
    private Logger $logger;

    public function __construct(?PDO $pdo = null, ?Logger $logger = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
    }

    /**
     * Record an audit event into audit_logs table.
     *
     * @param string $action e.g. 'USER_REGISTERED', 'ROLE_CHECK_FAILED'
     * @param string $entityType e.g. 'user', 'booking', 'tutor_profile'
     * @param int|null $entityId
     * @param int|null $actorUserId
     * @param array $metadata Context details (automatically redacted)
     * @param string|null $clientIp
     * @param string|null $userAgent
     * @return int|null Audit log inserted ID or null on failure
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?int $actorUserId = null,
        array $metadata = [],
        ?string $clientIp = null,
        ?string $userAgent = null
    ): ?int {
        try {
            $ip = $clientIp ?? $_SERVER['REMOTE_ADDR'] ?? null;
            $ipHash = $ip ? hash('sha256', $ip) : null;
            $ua = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($ua !== null && strlen($ua) > 500) {
                $ua = substr($ua, 0, 500);
            }

            $cleanMeta = $this->logger->redactSensitiveData($metadata);
            $metaJson = !empty($cleanMeta) ? json_encode($cleanMeta, JSON_UNESCAPED_SLASHES) : null;

            $sql = 'INSERT INTO `audit_logs` 
                    (`actor_user_id`, `action`, `entity_type`, `entity_id`, `ip_hash`, `user_agent`, `metadata_json`, `created_at`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $actorUserId,
                $action,
                $entityType,
                $entityId,
                $ipHash,
                $ua,
                $metaJson,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (Throwable $e) {
            // Audit failures should not crash user requests, but must be logged
            $this->logger->error('Audit logging failed: ' . $e->getMessage(), [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
            return null;
        }
    }
}
