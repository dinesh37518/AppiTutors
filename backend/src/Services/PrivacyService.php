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
use PDO;
use Throwable;

/**
 * PrivacyService — Technical Privacy Engineering Controls.
 *
 * Engineering mechanisms are provided to support data access, rectification and erasure workflows.
 * Record-specific retention, anonymization and deletion rules remain subject to client/legal approval
 * and are not hard-coded as legal requirements.
 *
 * Architectural capabilities:
 * - Subject Access Request export data compilation hook
 * - Technical data minimization and account anonymization hook
 * - Consent tracking and privacy-related audit logging
 */
class PrivacyService
{
    private PDO $pdo;
    private Logger $logger;
    private AuditService $audit;

    public function __construct(
        ?PDO $pdo = null,
        ?Logger $logger = null,
        ?AuditService $audit = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
        $this->audit = $audit ?? new AuditService($this->pdo, $this->logger);
    }

    /**
     * Compile an export package of all personal data held for a user (DSAR preparation).
     *
     * @param int $userId Target user ID
     * @param UserContext $requester Authenticated requesting user
     * @return array Comprehensive, minimized user data package
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function exportUserData(int $userId, UserContext $requester): array
    {
        Authorization::requireAuthenticatedUser($requester);
        Authorization::requireActiveStatus($requester);

        if (!$requester->isManager()) {
            Authorization::assertOwnership($userId, $requester->id, 'You may only export your own personal data.');
        }

        // 1. Fetch User Record
        $stmtUser = $this->pdo->prepare('
            SELECT id, email, display_name, role, status, email_verified_at, created_at, updated_at
            FROM `users`
            WHERE id = ?
            LIMIT 1
        ');
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new ValidationException('User not found.', 'USER_NOT_FOUND', 404);
        }

        // 2. Fetch Role Profile
        $profile = null;
        if ($user['role'] === 'TUTOR') {
            $stmtProf = $this->pdo->prepare('
                SELECT headline, bio, subjects_json, curriculum_json, qualifications, dbs_status, approval_status, created_at, updated_at
                FROM `tutor_profiles`
                WHERE user_id = ?
                LIMIT 1
            ');
            $stmtProf->execute([$userId]);
            $profRow = $stmtProf->fetch(PDO::FETCH_ASSOC);
            if ($profRow) {
                $profRow['subjects'] = !empty($profRow['subjects_json']) ? json_decode($profRow['subjects_json'], true) : [];
                $profRow['curriculum'] = !empty($profRow['curriculum_json']) ? json_decode($profRow['curriculum_json'], true) : [];
                unset($profRow['subjects_json'], $profRow['curriculum_json']);
                $profile = $profRow;
            }
        } elseif ($user['role'] === 'STUDENT_PARENT') {
            $stmtProf = $this->pdo->prepare('
                SELECT phone, postcode, created_at, updated_at
                FROM `student_profiles`
                WHERE user_id = ?
                LIMIT 1
            ');
            $stmtProf->execute([$userId]);
            $profile = $stmtProf->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // 3. Fetch Children (if parent)
        $children = [];
        if ($user['role'] === 'STUDENT_PARENT') {
            $stmtChild = $this->pdo->prepare('
                SELECT id, first_name, last_name, date_of_birth, school_year, curriculum, active, created_at
                FROM `children`
                WHERE parent_user_id = ? AND active = 1
                ORDER BY id ASC
            ');
            $stmtChild->execute([$userId]);
            $children = $stmtChild->fetchAll(PDO::FETCH_ASSOC);
        }

        // 4. Fetch Bookings (as student/parent or tutor)
        $stmtBookings = $this->pdo->prepare('
            SELECT id, tutor_user_id, student_user_id, child_id, status, proposed_starts_at_utc, proposed_ends_at_utc, created_at
            FROM `bookings`
            WHERE tutor_user_id = ? OR student_user_id = ?
            ORDER BY id DESC
        ');
        $stmtBookings->execute([$userId, $userId]);
        $bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

        // 5. Fetch Newsletter Subscription Status
        $stmtNews = $this->pdo->prepare('
            SELECT email, consent_at, confirmed_at, unsubscribed_at, status, created_at
            FROM `newsletter_subscribers`
            WHERE email = ?
            LIMIT 1
        ');
        $stmtNews->execute([$user['email']]);
        $newsletter = $stmtNews->fetch(PDO::FETCH_ASSOC) ?: null;

        // 6. Audit Logging
        $this->audit->log(
            action: 'PRIVACY_DATA_EXPORT_REQUESTED',
            entityType: 'user',
            entityId: $userId,
            actorUserId: $requester->id,
            metadata: ['target_user_id' => $userId, 'role' => $user['role']]
        );

        return [
            'export_timestamp_utc' => Timezone::nowUtc(),
            'user' => $user,
            'profile' => $profile,
            'children' => $children,
            'bookings' => $bookings,
            'newsletter' => $newsletter,
            'policy_note' => 'Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements.',
        ];
    }

    /**
     * Perform technical data minimization / account anonymization.
     *
     * Active/upcoming bookings remain deferred pending operational/cancellation policy confirmation.
     *
     * @param int $userId Target user ID
     * @param UserContext $requester Authenticated requesting user
     * @return array Result of erasure request
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function prepareAccountErasure(int $userId, UserContext $requester): array
    {
        Authorization::requireAuthenticatedUser($requester);
        Authorization::requireActiveStatus($requester);

        if (!$requester->isManager()) {
            Authorization::assertOwnership($userId, $requester->id, 'You may only request erasure of your own account.');
        }

        // 1. Verify user exists
        $stmtUser = $this->pdo->prepare('SELECT id, email, role, status FROM `users` WHERE id = ? LIMIT 1');
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new ValidationException('User not found.', 'USER_NOT_FOUND', 404);
        }

        // 2. Operational Boundary Check:
        // Technical erasure execution for accounts with active or upcoming bookings is deferred pending policy review.
        // Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements.
        $stmtActiveBookings = $this->pdo->prepare('
            SELECT COUNT(*) FROM `bookings`
            WHERE (tutor_user_id = ? OR student_user_id = ?)
              AND status IN ("PENDING", "CONFIRMED", "RESCHEDULE_PROPOSED")
        ');
        $stmtActiveBookings->execute([$userId, $userId]);
        $activeCount = (int) $stmtActiveBookings->fetchColumn();

        if ($activeCount > 0) {
            return [
                'eligible' => false,
                'status' => 'DEFERRED_PENDING_POLICY_REVIEW',
                'reason' => "Account has {$activeCount} active or confirmed lesson booking(s). Outstanding bookings must be completed or cancelled before erasure can proceed.",
                'active_bookings_count' => $activeCount,
                'policy_note' => 'Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements.',
            ];
        }

        // 3. Technical Anonymization / Soft-Erasure Execution
        // Application technical dependency: Personal identifying fields are anonymized while operational database records
        // remain structurally intact for relational integrity pending client/legal retention policy confirmation.
        $this->pdo->beginTransaction();
        try {
            $anonymizedEmail = 'erased_' . $userId . '_' . bin2hex(random_bytes(4)) . '@anonymized.invalid';

            // Anonymize user record
            $stmtUpdateUser = $this->pdo->prepare('
                UPDATE `users`
                SET `display_name` = "Erased User",
                    `email` = ?,
                    `status` = "DELETED",
                    `updated_at` = UTC_TIMESTAMP()
                WHERE id = ?
            ');
            $stmtUpdateUser->execute([$anonymizedEmail, $userId]);

            // Clear student profile contact details
            $stmtClearStudent = $this->pdo->prepare('
                UPDATE `student_profiles`
                SET `phone` = NULL, `postcode` = NULL, `updated_at` = UTC_TIMESTAMP()
                WHERE user_id = ?
            ');
            $stmtClearStudent->execute([$userId]);

            // Clear tutor profile bio and headlines
            $stmtClearTutor = $this->pdo->prepare('
                UPDATE `tutor_profiles`
                SET `headline` = NULL, `bio` = NULL, `qualifications` = NULL, `approval_status` = "SUSPENDED", `updated_at` = UTC_TIMESTAMP()
                WHERE user_id = ?
            ');
            $stmtClearTutor->execute([$userId]);

            // Deactivate children
            $stmtDeleteChildren = $this->pdo->prepare('
                UPDATE `children`
                SET `active` = 0
                WHERE parent_user_id = ? AND active = 1
            ');
            $stmtDeleteChildren->execute([$userId]);

            // Suppress newsletter if present
            $stmtSuppressNews = $this->pdo->prepare('
                UPDATE `newsletter_subscribers`
                SET `status` = "SUPPRESSED", `unsubscribed_at` = UTC_TIMESTAMP(), `updated_at` = UTC_TIMESTAMP()
                WHERE email = ?
            ');
            $stmtSuppressNews->execute([$user['email']]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Account erasure transaction failed: ' . $e->getMessage());
            throw new ValidationException('Account erasure failed during database anonymization.', 'ERASURE_FAILED', 500, [], $e);
        }

        // 4. Audit Log
        $this->audit->log(
            action: 'PRIVACY_DATA_ERASURE_REQUESTED',
            entityType: 'user',
            entityId: $userId,
            actorUserId: $requester->id,
            metadata: ['target_user_id' => $userId, 'role' => $user['role']]
        );

        return [
            'eligible' => true,
            'erased_at' => Timezone::nowUtc(),
            'status' => 'DELETED',
            'message' => 'Personal data technical minimization and account anonymization completed successfully.',
            'policy_note' => 'Engineering mechanisms are provided to support data access, rectification and erasure workflows. Record-specific retention, anonymization and deletion rules remain subject to client/legal approval and are not hard-coded as legal requirements.',
        ];
    }

    /**
     * Record explicit privacy consent event.
     */
    public function recordConsent(int $userId, string $consentType, bool $granted, UserContext $user): void
    {
        Authorization::requireAuthenticatedUser($user);

        $this->audit->log(
            action: 'PRIVACY_CONSENT_RECORDED',
            entityType: 'user',
            entityId: $userId,
            actorUserId: $user->id,
            metadata: [
                'consent_type' => $consentType,
                'granted' => $granted,
                'recorded_at' => Timezone::nowUtc(),
            ]
        );
    }
}
