<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\UserContext;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Logging\Logger;
use App\Services\Exceptions\ValidationException;
use App\Support\Timezone;
use App\Validation\Validator;
use PDO;
use Throwable;

class NewsletterService
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_UNSUBSCRIBED = 'UNSUBSCRIBED';
    public const STATUS_SUPPRESSED = 'SUPPRESSED';

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_UNSUBSCRIBED,
        self::STATUS_SUPPRESSED,
    ];

    private PDO $pdo;
    private Logger $logger;
    private AuditService $audit;

    public function __construct(?PDO $pdo = null, ?Logger $logger = null, ?AuditService $audit = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
        $this->audit = $audit ?? new AuditService($this->pdo, $this->logger);
    }

    /**
     * Enforce strict manager role and active account status.
     *
     * @param UserContext|null $manager
     * @throws ForbiddenException
     */
    private function requireManager(?UserContext $manager): void
    {
        Authorization::requireAuthenticatedUser($manager);
        Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
        Authorization::requireActiveStatus($manager);
    }

    /**
     * Validate pagination and sort parameters against an allowlist.
     */
    private function validatePaginationAndSort(
        int $page,
        int $perPage,
        string $sortBy,
        string $sortDir,
        array $allowedSortFields
    ): void {
        if ($page < 1 || $perPage < 1) {
            throw new ValidationException(
                'Page and per_page parameters must be positive integers.',
                'INVALID_PAGINATION',
                422
            );
        }

        if ($perPage > 100) {
            throw new ValidationException(
                'per_page cannot exceed 100 records.',
                'INVALID_PAGINATION',
                422
            );
        }

        if (!in_array($sortBy, $allowedSortFields, true)) {
            throw new ValidationException(
                "Invalid sort field '{$sortBy}'. Allowed fields: " . implode(', ', $allowedSortFields),
                'INVALID_SORT_FIELD',
                422
            );
        }

        $upperDir = strtoupper($sortDir);
        if ($upperDir !== 'ASC' && $upperDir !== 'DESC') {
            throw new ValidationException(
                "Invalid sort direction '{$sortDir}'. Must be ASC or DESC.",
                'INVALID_SORT_DIRECTION',
                422
            );
        }
    }

    /**
     * Subscribe an email address to the newsletter.
     * In accordance with open commercial/legal scope, new subscribers are created in neutral PENDING state
     * with confirmed_at = NULL, preserving the unresolved double opt-in client decision.
     *
     * @param string $email
     * @param bool $consentGiven
     * @return array
     * @throws ValidationException
     */
    public function subscribe(string $email, bool $consentGiven = true): array
    {
        $cleanEmail = trim($email);

        if ($cleanEmail === '' || !Validator::validateEmail($cleanEmail)) {
            throw new ValidationException('Please provide a valid email address.', 'INVALID_EMAIL', 422);
        }

        if (strlen($cleanEmail) > 255) {
            throw new ValidationException('Email address cannot exceed 255 characters.', 'EMAIL_TOO_LONG', 422);
        }

        if (!$consentGiven) {
            throw new ValidationException('You must confirm your consent to receive educational updates.', 'CONSENT_REQUIRED', 422);
        }

        $emailNormalized = strtolower($cleanEmail);
        $now = Timezone::nowUtc();

        // 1. Check existing record
        $stmt = $this->pdo->prepare('SELECT id, status, confirmed_at FROM `newsletter_subscribers` WHERE email = ? LIMIT 1');
        $stmt->execute([$emailNormalized]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // If previously unsubscribed, reactivate in neutral PENDING state preserving open double-opt-in decision
            $rawUnsubToken = bin2hex(random_bytes(32));
            $unsubTokenHash = hash('sha256', $rawUnsubToken);
            if ($existing['status'] === self::STATUS_UNSUBSCRIBED) {
                $updateStmt = $this->pdo->prepare('
                    UPDATE `newsletter_subscribers`
                    SET status = "PENDING", consent_at = ?, unsubscribed_at = NULL, unsubscribe_token_hash = ?, updated_at = ?
                    WHERE id = ?
                ');
                $updateStmt->execute([$now, $unsubTokenHash, $now, $existing['id']]);

                $this->logger->info('Newsletter subscriber re-opted in (neutral pending state)', [
                    'email_hash' => hash('sha256', $emailNormalized),
                ]);
            }

            return [
                'id' => (int) $existing['id'],
                'email' => $emailNormalized,
                'status' => $existing['status'] === self::STATUS_UNSUBSCRIBED ? self::STATUS_PENDING : $existing['status'],
                'unsubscribe_token' => $rawUnsubToken,
                'message' => 'Thank you for your interest! Your subscription request has been received.',
                'open_decision_note' => 'Newsletter double opt-in remains an open client decision; record maintained in neutral state.',
            ];
        }

        // 2. Insert new subscriber in neutral PENDING state
        $rawConfirmToken = bin2hex(random_bytes(32));
        $confirmTokenHash = hash('sha256', $rawConfirmToken);
        $rawUnsubToken = bin2hex(random_bytes(32));
        $unsubTokenHash = hash('sha256', $rawUnsubToken);

        $insertStmt = $this->pdo->prepare('
            INSERT INTO `newsletter_subscribers` (
                email, consent_at, confirmed_at, unsubscribed_at, status,
                confirmation_token_hash, unsubscribe_token_hash, created_at, updated_at
            ) VALUES (?, ?, NULL, NULL, "PENDING", ?, ?, ?, ?)
        ');

        $insertStmt->execute([
            $emailNormalized,
            $now,
            $confirmTokenHash,
            $unsubTokenHash,
            $now,
            $now,
        ]);

        $subscriberId = (int) $this->pdo->lastInsertId();

        // Log action with privacy-preserving email hash
        $this->logger->info('Newsletter subscriber opted in (neutral pending state)', [
            'subscriber_id' => $subscriberId,
            'email_hash' => hash('sha256', $emailNormalized),
        ]);

        return [
            'id' => $subscriberId,
            'email' => $emailNormalized,
            'status' => self::STATUS_PENDING,
            'unsubscribe_token' => $rawUnsubToken,
            'message' => 'Thank you for your interest! Your subscription request has been received.',
            'open_decision_note' => 'Newsletter double opt-in remains an open client decision; record maintained in neutral state.',
        ];
    }

    /**
     * List newsletter subscribers with pagination, status filtering, and safe search (manager-only).
     *
     * @param UserContext|null $manager
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortDir
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function listSubscribers(
        ?UserContext $manager,
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $this->requireManager($manager);

        $page = isset($filters['page']) ? (int) $filters['page'] : $page;
        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : $perPage;
        $sortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : $sortBy;
        $sortDir = isset($filters['sort_dir']) ? (string) $filters['sort_dir'] : $sortDir;

        $allowedSortFields = ['id', 'email', 'status', 'created_at', 'consent_at', 'confirmed_at'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $status = strtoupper((string) $filters['status']);
            if (in_array($status, self::ALLOWED_STATUSES, true)) {
                $where[] = 'status = ?';
                $params[] = $status;
            }
        }

        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = 'email LIKE ?';
            $params[] = $term;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 1. Total count query
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `newsletter_subscribers` {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumn = match ($sortBy) {
            'email' => 'email',
            'status' => 'status',
            'consent_at' => 'consent_at',
            'confirmed_at' => 'confirmed_at',
            'id' => 'id',
            default => 'created_at',
        };

        // 2. Data slice query
        $sql = "
            SELECT 
                id, email, consent_at, confirmed_at, unsubscribed_at, status, created_at, updated_at
            FROM `newsletter_subscribers`
            {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $execParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'status' => $row['status'],
                'consent_at' => $row['consent_at'],
                'consent_date_london' => !empty($row['consent_at']) 
                    ? Timezone::utcToLondon($row['consent_at'], 'j F Y, H:i') 
                    : null,
                'confirmed_at' => $row['confirmed_at'],
                'confirmed_date_london' => !empty($row['confirmed_at']) 
                    ? Timezone::utcToLondon($row['confirmed_at'], 'j F Y, H:i') 
                    : null,
                'unsubscribed_at' => $row['unsubscribed_at'],
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Retrieve aggregate subscriber counts for managerial desk.
     *
     * @param UserContext|null $manager
     * @return array
     * @throws ForbiddenException
     */
    public function getSubscriberStats(?UserContext $manager): array
    {
        $this->requireManager($manager);

        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'UNSUBSCRIBED' THEN 1 ELSE 0 END) AS unsubscribed,
                SUM(CASE WHEN status = 'SUPPRESSED' THEN 1 ELSE 0 END) AS suppressed
            FROM `newsletter_subscribers`
        ");

        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'pending' => (int) ($stats['pending'] ?? 0),
            'active' => (int) ($stats['active'] ?? 0),
            'unsubscribed' => (int) ($stats['unsubscribed'] ?? 0),
            'suppressed' => (int) ($stats['suppressed'] ?? 0),
        ];
    }

    /**
     * Unsubscribe a subscriber using a secure token.
     * Public operation: Transitions subscriber to UNSUBSCRIBED and records unsubscribed_at timestamp.
     * Does NOT log the raw token or subscriber secrets.
     *
     * @param string $rawToken
     * @return array
     * @throws ValidationException
     */
    public function unsubscribe(string $rawToken): array
    {
        $cleanToken = trim($rawToken);
        if ($cleanToken === '') {
            throw new ValidationException('Unsubscribe token is required.', 'TOKEN_REQUIRED', 422);
        }

        $tokenHash = hash('sha256', $cleanToken);

        $stmt = $this->pdo->prepare('
            SELECT id, email, status, unsubscribed_at
            FROM `newsletter_subscribers`
            WHERE unsubscribe_token_hash = ? OR unsubscribe_token_hash = ?
            LIMIT 1
        ');
        $stmt->execute([$tokenHash, $cleanToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidationException('Invalid or expired unsubscribe token.', 'INVALID_TOKEN', 404);
        }

        $now = Timezone::nowUtc();

        if ($row['status'] === self::STATUS_UNSUBSCRIBED) {
            return [
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'status' => self::STATUS_UNSUBSCRIBED,
                'unsubscribed_at' => $row['unsubscribed_at'],
                'message' => 'You are already unsubscribed from our newsletter.',
            ];
        }

        $updateStmt = $this->pdo->prepare('
            UPDATE `newsletter_subscribers`
            SET status = "UNSUBSCRIBED", unsubscribed_at = ?, updated_at = ?
            WHERE id = ?
        ');
        $updateStmt->execute([$now, $now, $row['id']]);

        // Audit log action: DO NOT log the raw token or subscriber secrets
        $this->audit->log(
            action: 'NEWSLETTER_UNSUBSCRIBED',
            entityType: 'newsletter_subscriber',
            entityId: (int) $row['id'],
            actorUserId: null,
            metadata: [
                'previous_status' => $row['status'],
                'email_hash' => hash('sha256', $row['email']),
            ]
        );

        $this->logger->info("Subscriber ID {$row['id']} unsubscribed via token", [
            'email_hash' => hash('sha256', $row['email']),
        ]);

        return [
            'id' => (int) $row['id'],
            'email' => $row['email'],
            'status' => self::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => $now,
            'message' => 'You have been successfully unsubscribed from the newsletter.',
        ];
    }

    /**
     * Update subscriber status (manager administrative operational control).
     * Strictly protects the open double opt-in client decision:
     * Managers CANNOT arbitrarily transition PENDING to ACTIVE.
     *
     * @param mixed $first
     * @param mixed $second
     * @param mixed $third
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function updateSubscriberStatus(mixed $first, mixed $second, mixed $third = null): array
    {
        if ($first instanceof UserContext || ($first === null && is_int($second))) {
            $manager = $first;
            $subscriberId = (int) $second;
            $newStatus = (string) $third;
        } else {
            $subscriberId = (int) $first;
            $newStatus = (string) $second;
            $manager = $third instanceof UserContext ? $third : null;
        }

        $this->requireManager($manager);

        if ($subscriberId <= 0) {
            throw new ValidationException('Valid subscriber ID is required.', 'INVALID_ID', 422);
        }

        $upperStatus = strtoupper(trim($newStatus));
        if (!in_array($upperStatus, self::ALLOWED_STATUSES, true)) {
            throw new ValidationException("Invalid subscriber status '{$newStatus}'.", 'INVALID_STATUS', 422);
        }

        $stmtFetch = $this->pdo->prepare('SELECT id, email, status, confirmed_at, unsubscribed_at FROM `newsletter_subscribers` WHERE id = ? LIMIT 1');
        $stmtFetch->execute([$subscriberId]);
        $existing = $stmtFetch->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new ValidationException('Subscriber not found.', 'SUBSCRIBER_NOT_FOUND', 404);
        }

        // Strict boundary: Managers CANNOT arbitrarily transition PENDING to ACTIVE
        if ($existing['status'] === self::STATUS_PENDING && $upperStatus === self::STATUS_ACTIVE) {
            throw new ValidationException(
                'Cannot transition subscriber from PENDING to ACTIVE: Double opt-in confirmation workflow remains an OPEN client decision and cannot be bypassed administratively.',
                'DOUBLE_OPT_IN_OPEN_DECISION',
                422
            );
        }

        $now = Timezone::nowUtc();
        $confirmedAtUpdate = ($upperStatus === self::STATUS_ACTIVE) ? $existing['confirmed_at'] : null;
        $unsubscribedAtUpdate = ($upperStatus === self::STATUS_UNSUBSCRIBED) ? $now : $existing['unsubscribed_at'];

        $stmt = $this->pdo->prepare("
            UPDATE `newsletter_subscribers`
            SET status = ?, 
                confirmed_at = ?,
                unsubscribed_at = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$upperStatus, $confirmedAtUpdate, $unsubscribedAtUpdate, $now, $subscriberId]);

        $this->audit->log(
            action: 'NEWSLETTER_SUBSCRIBER_STATUS_UPDATED',
            entityType: 'newsletter_subscriber',
            entityId: $subscriberId,
            actorUserId: $manager->id,
            metadata: [
                'previous_status' => $existing['status'],
                'new_status' => $upperStatus,
                'email_hash' => hash('sha256', $existing['email']),
            ]
        );

        $this->logger->info("Manager ID {$manager->id} updated newsletter subscriber ID {$subscriberId} to {$upperStatus}");

        $stmtUpdated = $this->pdo->prepare('SELECT id, email, status, consent_at, confirmed_at, unsubscribed_at, updated_at FROM `newsletter_subscribers` WHERE id = ? LIMIT 1');
        $stmtUpdated->execute([$subscriberId]);
        $updatedRow = $stmtUpdated->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'id' => $subscriberId,
            'email' => $updatedRow['email'] ?? $existing['email'],
            'status' => $updatedRow['status'] ?? $upperStatus,
            'new_status' => $upperStatus,
            'previous_status' => $existing['status'],
            'confirmed_at' => $updatedRow['confirmed_at'] ?? null,
            'consent_at' => $updatedRow['consent_at'] ?? null,
            'unsubscribed_at' => $updatedRow['unsubscribed_at'] ?? null,
            'updated_at' => $updatedRow['updated_at'] ?? $now,
        ];
    }

    /**
     * Broadcast published blog post notification to active newsletter subscribers.
     *
     * @param int $postId
     * @param UserContext $manager
     * @param \App\Services\Email\DefaultEmailService|null $emailService
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function broadcastPublishedBlogPost(int $postId, UserContext $manager, ?\App\Services\Email\DefaultEmailService $emailService = null): array
    {
        $this->requireManager($manager);

        if ($postId <= 0) {
            throw new ValidationException('Valid post ID is required.', 'INVALID_ID', 422);
        }

        $stmtPost = $this->pdo->prepare('SELECT id, title, slug, excerpt, status FROM `blog_posts` WHERE id = ? LIMIT 1');
        $stmtPost->execute([$postId]);
        $post = $stmtPost->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            throw new ValidationException('Blog post not found.', 'POST_NOT_FOUND', 404);
        }

        if ($post['status'] !== 'PUBLISHED') {
            throw new ValidationException("Only PUBLISHED blog posts can be broadcast to newsletter subscribers. Current status is '{$post['status']}'.", 'POST_NOT_PUBLISHED', 422);
        }

        $emailService = $emailService ?? new \App\Services\Email\DefaultEmailService(null, $this->logger, $this->audit);

        // Fetch eligible subscribers
        $stmtSubs = $this->pdo->prepare("
            SELECT id, email, unsubscribe_token_hash 
            FROM `newsletter_subscribers` 
            WHERE `status` IN ('ACTIVE', 'PENDING')
        ");
        $stmtSubs->execute();
        $subscribers = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

        $sentCount = 0;
        $appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost'), '/');
        $postUrl = "{$appUrl}/blog-post.php?slug=" . urlencode($post['slug']);

        foreach ($subscribers as $sub) {
            $toEmail = $sub['email'];
            if (empty($toEmail) || !Validator::validateEmail($toEmail)) {
                continue;
            }

            $unsubUrl = "{$appUrl}/newsletter-unsubscribe.php?token=" . urlencode((string) ($sub['unsubscribe_token_hash'] ?? ''));

            try {
                $emailService->send(
                    toEmail: $toEmail,
                    toName: 'Valued Subscriber',
                    subject: 'New on AppTutors: ' . $post['title'],
                    templateName: 'newsletter_blog',
                    templateData: [
                        'post_title' => $post['title'],
                        'post_excerpt' => $post['excerpt'] ?? '',
                        'post_url' => $postUrl,
                        'unsubscribe_url' => $unsubUrl,
                        'app_url' => $appUrl,
                    ]
                );
                $sentCount++;
            } catch (Throwable $e) {
                $this->logger->error("Failed to send newsletter email to subscriber ID {$sub['id']}: " . $e->getMessage());
            }
        }

        $this->audit->log(
            action: 'NEWSLETTER_BROADCAST_BLOG_POST',
            entityType: 'blog_post',
            entityId: $postId,
            actorUserId: $manager->id,
            metadata: [
                'post_title' => $post['title'],
                'slug' => $post['slug'],
                'recipients_count' => $sentCount,
            ]
        );

        $this->logger->info("Manager ID {$manager->id} broadcasted blog post ID {$postId} to {$sentCount} newsletter subscribers");

        return [
            'post_id' => $postId,
            'title' => $post['title'],
            'sent_count' => $sentCount,
            'subscribers_total' => count($subscribers),
        ];
    }
}
