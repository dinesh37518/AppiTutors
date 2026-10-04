<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\UserContext;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Logging\Logger;
use App\Services\EmailService;
use App\Services\Email\DefaultEmailService;
use App\Services\Exceptions\BookabilityException;
use App\Services\Exceptions\ValidationException;
use App\Support\Timezone;
use App\Validation\Validator;
use PDO;
use Throwable;

class TutorService
{
    public const APPROVAL_PENDING = 'PENDING';
    public const APPROVAL_APPROVED = 'APPROVED';
    public const APPROVAL_REJECTED = 'REJECTED';
    public const APPROVAL_SUSPENDED = 'SUSPENDED';

    public const DBS_NOT_SUBMITTED = 'NOT_SUBMITTED';
    public const DBS_SUBMITTED = 'SUBMITTED';
    public const DBS_VERIFIED = 'VERIFIED';
    public const DBS_REJECTED = 'REJECTED';
    public const DBS_EXPIRED = 'EXPIRED';

    private PDO $pdo;
    private Logger $logger;
    private AuditService $audit;
    private EmailService $emailService;

    public function __construct(
        ?PDO $pdo = null,
        ?Logger $logger = null,
        ?AuditService $audit = null,
        ?EmailService $emailService = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
        $this->audit = $audit ?? new AuditService($this->pdo, $this->logger);
        $this->emailService = $emailService ?? new DefaultEmailService(logger: $this->logger, audit: $this->audit);
    }

    public function getEmailService(): EmailService
    {
        return $this->emailService;
    }

    public function setEmailService(EmailService $emailService): void
    {
        $this->emailService = $emailService;
    }

    /**
     * Register a new Tutor using verified Firebase credentials.
     * Enforces that tutor starts strictly in PENDING role/status.
     *
     * @param array $data
     * @param UserContext|null $creator
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function registerTutor(array $data, ?UserContext $creator = null): array
    {
        // 1. Strictly discard any client-supplied role or status authority
        unset(
            $data['role'],
            $data['status'],
            $data['approval_status'],
            $data['dbs_status'],
            $data['is_manager'],
            $data['is_admin'],
            $data['approved_at'],
            $data['approved_by']
        );

        $firebaseUid = trim((string) ($data['firebase_uid'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $displayName = Validator::sanitizeString((string) ($data['display_name'] ?? ''));

        if (empty($firebaseUid)) {
            throw new ValidationException('Firebase UID is required.', 'VALIDATION_ERROR', 422, ['firebase_uid' => 'Required']);
        }

        if (empty($email) || !Validator::validateEmail($email)) {
            throw new ValidationException('Valid email address is required.', 'VALIDATION_ERROR', 422, ['email' => 'Invalid email']);
        }

        if (empty($displayName)) {
            throw new ValidationException('Display name is required.', 'VALIDATION_ERROR', 422, ['display_name' => 'Required']);
        }

        // 2. Prevent duplicate registrations
        $stmtCheck = $this->pdo->prepare('SELECT id, role, firebase_uid, email FROM `users` WHERE `firebase_uid` = ? OR `email` = ? LIMIT 1');
        $stmtCheck->execute([$firebaseUid, $email]);
        if ($stmtCheck->fetch()) {
            throw new ValidationException('User account already exists with this email or Firebase UID.', 'USER_ALREADY_EXISTS', 409);
        }

        // 3. Sanitize profile fields
        $headline = !empty($data['headline']) ? Validator::sanitizeString((string) $data['headline']) : null;
        if ($headline !== null && mb_strlen($headline) > 255) {
            throw new ValidationException('Headline cannot exceed 255 characters.', 'VALIDATION_ERROR', 422, ['headline' => 'Too long']);
        }

        $bio = !empty($data['bio']) ? Validator::sanitizeString((string) $data['bio']) : null;
        $qualifications = !empty($data['qualifications']) ? Validator::sanitizeString((string) $data['qualifications']) : null;

        $subjectsJson = null;
        if (!empty($data['subjects']) && is_array($data['subjects'])) {
            $sanitizedSubjects = array_values(array_filter(array_map('strval', $data['subjects'])));
            $subjectsJson = !empty($sanitizedSubjects) ? json_encode($sanitizedSubjects, JSON_UNESCAPED_SLASHES) : null;
        }

        $curriculumJson = null;
        if (!empty($data['curriculum']) && is_array($data['curriculum'])) {
            $sanitizedCurriculum = array_values(array_filter(array_map('strval', $data['curriculum'])));
            $curriculumJson = !empty($sanitizedCurriculum) ? json_encode($sanitizedCurriculum, JSON_UNESCAPED_SLASHES) : null;
        }

        // 4. Atomic Transaction: Insert into users and tutor_profiles
        try {
            $this->pdo->beginTransaction();

            $stmtUser = $this->pdo->prepare('
                INSERT INTO `users` 
                (`firebase_uid`, `email`, `display_name`, `role`, `status`, `email_verified_at`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, "TUTOR", "PENDING", ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ');
            $verifiedAt = !empty($data['email_verified']) ? Timezone::nowUtc() : null;
            $stmtUser->execute([$firebaseUid, $email, $displayName, $verifiedAt]);
            $userId = (int) $this->pdo->lastInsertId();

            $stmtProfile = $this->pdo->prepare('
                INSERT INTO `tutor_profiles` 
                (`user_id`, `headline`, `bio`, `subjects_json`, `curriculum_json`, `qualifications`, `dbs_status`, `approval_status`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, ?, ?, "NOT_SUBMITTED", "PENDING", UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ');
            $stmtProfile->execute([$userId, $headline, $bio, $subjectsJson, $curriculumJson, $qualifications]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Tutor registration failed: ' . $e->getMessage());
            throw new ValidationException('Registration could not be completed: ' . $e->getMessage(), 'REGISTRATION_FAILED', 500, [], $e);
        }

        // 5. Audit log
        $this->audit->log(
            action: 'USER_REGISTERED_TUTOR',
            entityType: 'user',
            entityId: $userId,
            actorUserId: $userId,
            metadata: [
                'role' => 'TUTOR',
                'status' => 'PENDING',
                'approval_status' => self::APPROVAL_PENDING,
                'dbs_status' => self::DBS_NOT_SUBMITTED,
            ]
        );

        $this->logger->info("New tutor registered in PENDING status: {$email} (User ID: {$userId})");

        // Transactional Email Notification (Dispatched strictly after commit)
        try {
            $this->emailService->send(
                toEmail: $email,
                toName: $displayName,
                subject: 'Welcome to UK Tutoring Platform — Application Received',
                templateName: 'tutor_registered',
                templateData: ['recipient_name' => $displayName]
            );
        } catch (Throwable $mailEx) {
            $this->logger->error('Failed to dispatch tutor registration email: ' . $mailEx->getMessage());
        }

        return [
            'id' => $userId,
            'firebase_uid' => $firebaseUid,
            'email' => $email,
            'display_name' => $displayName,
            'role' => 'TUTOR',
            'status' => 'PENDING',
            'headline' => $headline,
            'bio' => $bio,
            'subjects' => !empty($subjectsJson) ? json_decode($subjectsJson, true) : [],
            'curriculum' => !empty($curriculumJson) ? json_decode($curriculumJson, true) : [],
            'qualifications' => $qualifications,
            'approval_status' => self::APPROVAL_PENDING,
            'dbs_status' => self::DBS_NOT_SUBMITTED,
            'is_bookable' => false,
        ];
    }

    /**
     * Retrieve a tutor's profile with authorization and bookability gating.
     *
     * @param int $tutorUserId
     * @param UserContext|null $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws BookabilityException
     * @throws ValidationException
     */
    public function getProfile(int $tutorUserId, ?UserContext $currentUser = null): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                u.id, u.firebase_uid, u.email, u.display_name, u.role, u.status, u.email_verified_at,
                tp.headline, tp.bio, tp.subjects_json, tp.curriculum_json, tp.qualifications,
                tp.dbs_status, tp.approval_status, tp.approved_at, tp.approved_by,
                tp.created_at as profile_created_at, tp.updated_at as profile_updated_at
            FROM `users` u
            JOIN `tutor_profiles` tp ON u.id = tp.user_id
            WHERE u.id = ? AND u.role = "TUTOR"
            LIMIT 1
        ');
        $stmt->execute([$tutorUserId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new ValidationException('Tutor profile not found.', 'TUTOR_NOT_FOUND', 404);
        }

        $isBookable = ($row['status'] === 'ACTIVE' && $row['approval_status'] === self::APPROVAL_APPROVED && $row['dbs_status'] === self::DBS_VERIFIED);

        // Authorization Gate:
        // - Owner can view own profile regardless of approval state
        // - Manager can view any tutor profile
        // - Third parties / public can ONLY view if tutor is APPROVED, VERIFIED, and ACTIVE
        $isOwner = ($currentUser !== null && $currentUser->id === $tutorUserId);
        $isManager = ($currentUser !== null && $currentUser->isManager());

        if (!$isOwner && !$isManager && !$isBookable) {
            throw new BookabilityException(
                'Tutor profile is currently undergoing verification and is not publicly visible.',
                'TUTOR_NOT_BOOKABLE',
                403
            );
        }

        $profileData = [
            'id' => (int) $row['id'],
            'display_name' => $row['display_name'],
            'role' => $row['role'],
            'status' => $row['status'],
            'headline' => $row['headline'],
            'bio' => $row['bio'],
            'subjects' => !empty($row['subjects_json']) ? json_decode($row['subjects_json'], true) : [],
            'curriculum' => !empty($row['curriculum_json']) ? json_decode($row['curriculum_json'], true) : [],
            'qualifications' => $row['qualifications'],
            'dbs_status' => $row['dbs_status'],
            'approval_status' => $row['approval_status'],
            'is_bookable' => $isBookable,
            'created_at' => $row['profile_created_at'],
            'updated_at' => $row['profile_updated_at'],
        ];

        // Privacy & Data Minimization: Expose private identifier and internal approval metadata strictly to owner or manager
        if ($isOwner || $isManager) {
            $profileData['firebase_uid'] = $row['firebase_uid'];
            $profileData['email'] = $row['email'];
            $profileData['approved_at'] = $row['approved_at'];
            $profileData['approved_by'] = $row['approved_by'] ? (int) $row['approved_by'] : null;
        }

        return $profileData;
    }

    /**
     * Update an authenticated tutor's own profile.
     * Enforces ownership, validation, and disallows unauthorized status changes.
     *
     * @param int $tutorUserId
     * @param array $data
     * @param UserContext $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function updateProfile(int $tutorUserId, array $data, UserContext $currentUser): array
    {
        // 1. Ownership Check: Tutor can only modify own profile (unless Manager)
        if (!$currentUser->isManager()) {
            Authorization::assertOwnership($tutorUserId, $currentUser->id, 'You cannot modify another tutor\'s profile.');
        }

        // 2. Inactive account enforcement
        Authorization::requireActiveStatus($currentUser);

        // 3. Verify tutor profile exists
        $stmt = $this->pdo->prepare('SELECT user_id, approval_status, dbs_status FROM `tutor_profiles` WHERE `user_id` = ? LIMIT 1');
        $stmt->execute([$tutorUserId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            throw new ValidationException('Tutor profile not found.', 'TUTOR_NOT_FOUND', 404);
        }

        // 4. Discard any client-supplied role or status authority
        unset(
            $data['role'],
            $data['status'],
            $data['approval_status'],
            $data['dbs_status'],
            $data['approved_at'],
            $data['approved_by']
        );

        // 5. Sanitize and validate update fields
        $updates = [];
        $params = [];

        if (array_key_exists('headline', $data)) {
            $headline = !empty($data['headline']) ? Validator::sanitizeString((string) $data['headline']) : null;
            if ($headline !== null && mb_strlen($headline) > 255) {
                throw new ValidationException('Headline cannot exceed 255 characters.', 'VALIDATION_ERROR', 422, ['headline' => 'Too long']);
            }
            $updates[] = '`headline` = ?';
            $params[] = $headline;
        }

        if (array_key_exists('bio', $data)) {
            $bio = !empty($data['bio']) ? Validator::sanitizeString((string) $data['bio']) : null;
            $updates[] = '`bio` = ?';
            $params[] = $bio;
        }

        if (array_key_exists('qualifications', $data)) {
            $qualifications = !empty($data['qualifications']) ? Validator::sanitizeString((string) $data['qualifications']) : null;
            $updates[] = '`qualifications` = ?';
            $params[] = $qualifications;
        }

        if (array_key_exists('subjects', $data)) {
            $subjectsJson = null;
            if (is_array($data['subjects'])) {
                $sanitized = array_values(array_filter(array_map('strval', $data['subjects'])));
                $subjectsJson = !empty($sanitized) ? json_encode($sanitized, JSON_UNESCAPED_SLASHES) : null;
            }
            $updates[] = '`subjects_json` = ?';
            $params[] = $subjectsJson;
        }

        if (array_key_exists('curriculum', $data)) {
            $curriculumJson = null;
            if (is_array($data['curriculum'])) {
                $sanitized = array_values(array_filter(array_map('strval', $data['curriculum'])));
                $curriculumJson = !empty($sanitized) ? json_encode($sanitized, JSON_UNESCAPED_SLASHES) : null;
            }
            $updates[] = '`curriculum_json` = ?';
            $params[] = $curriculumJson;
        }

        // If display name is provided, update users table as well
        if (!empty($data['display_name'])) {
            $displayName = Validator::sanitizeString((string) $data['display_name']);
            $stmtUser = $this->pdo->prepare('UPDATE `users` SET `display_name` = ?, `updated_at` = UTC_TIMESTAMP() WHERE `id` = ?');
            $stmtUser->execute([$displayName, $tutorUserId]);
        }

        if (!empty($updates)) {
            $updates[] = '`updated_at` = UTC_TIMESTAMP()';
            $params[] = $tutorUserId;
            $sql = 'UPDATE `tutor_profiles` SET ' . implode(', ', $updates) . ' WHERE `user_id` = ?';
            $stmtUpdate = $this->pdo->prepare($sql);
            $stmtUpdate->execute($params);
        }

        // 6. Audit logging
        $this->audit->log(
            action: 'TUTOR_PROFILE_UPDATED',
            entityType: 'tutor_profile',
            entityId: $tutorUserId,
            actorUserId: $currentUser->id,
            metadata: ['updated_fields' => array_keys($data)]
        );

        return $this->getProfile($tutorUserId, $currentUser);
    }

    /**
     * Manager Approval Action: Promotes tutor from PENDING to APPROVED and activates user account.
     *
     * @param int $tutorUserId
     * @param UserContext $manager
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function managerApproveTutor(int $tutorUserId, UserContext $manager): array
    {
        // 1. Role verification
        Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
        Authorization::requireActiveStatus($manager);

        // 2. Prevent self-approval
        if ($manager->id === $tutorUserId) {
            throw new ForbiddenException('Managers cannot approve their own tutor profile.', 'SELF_APPROVAL_FORBIDDEN');
        }

        // 3. Verify tutor exists
        $stmt = $this->pdo->prepare('SELECT user_id, approval_status, dbs_status FROM `tutor_profiles` WHERE `user_id` = ? LIMIT 1');
        $stmt->execute([$tutorUserId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            throw new ValidationException('Tutor profile not found.', 'TUTOR_NOT_FOUND', 404);
        }

        $now = Timezone::nowUtc();

        try {
            $this->pdo->beginTransaction();

            // Update tutor profile
            $stmtProfile = $this->pdo->prepare('
                UPDATE `tutor_profiles` 
                SET `approval_status` = "APPROVED",
                    `dbs_status` = "VERIFIED",
                    `approved_at` = ?,
                    `approved_by` = ?,
                    `updated_at` = UTC_TIMESTAMP()
                WHERE `user_id` = ?
            ');
            $stmtProfile->execute([$now, $manager->id, $tutorUserId]);

            // Activate user account in users table
            $stmtUser = $this->pdo->prepare('
                UPDATE `users` 
                SET `status` = "ACTIVE", `updated_at` = UTC_TIMESTAMP() 
                WHERE `id` = ?
            ');
            $stmtUser->execute([$tutorUserId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new ValidationException('Failed to approve tutor: ' . $e->getMessage(), 'APPROVAL_FAILED', 500, [], $e);
        }

        // Audit log
        $this->audit->log(
            action: 'MANAGER_APPROVE_TUTOR',
            entityType: 'tutor_profile',
            entityId: $tutorUserId,
            actorUserId: $manager->id,
            metadata: [
                'previous_approval_status' => $profile['approval_status'],
                'new_approval_status' => self::APPROVAL_APPROVED,
                'previous_dbs_status' => $profile['dbs_status'],
                'new_dbs_status' => self::DBS_VERIFIED,
                'approved_at' => $now,
            ]
        );

        $this->logger->info("Manager ID {$manager->id} approved Tutor ID {$tutorUserId} (Active & Bookable)");

        // Transactional Email Notification (Dispatched strictly after commit)
        try {
            $stmtUser = $this->pdo->prepare('SELECT email, display_name FROM `users` WHERE `id` = ? LIMIT 1');
            $stmtUser->execute([$tutorUserId]);
            $tutorUser = $stmtUser->fetch();
            if ($tutorUser && !empty($tutorUser['email'])) {
                $this->emailService->send(
                    toEmail: $tutorUser['email'],
                    toName: $tutorUser['display_name'] ?? 'Tutor',
                    subject: 'Your Tutor Application has been Approved!',
                    templateName: 'tutor_approved',
                    templateData: [
                        'recipient_name' => $tutorUser['display_name'] ?? 'Tutor',
                    ]
                );
            }
        } catch (Throwable $mailEx) {
            $this->logger->error('Failed to dispatch tutor approved email: ' . $mailEx->getMessage());
        }

        return $this->getProfile($tutorUserId, $manager);
    }

    /**
     * Manager Rejection Action: Rejects tutor application.
     *
     * @param int $tutorUserId
     * @param string $reason
     * @param UserContext $manager
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function managerRejectTutor(int $tutorUserId, string $reason, UserContext $manager): array
    {
        Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
        Authorization::requireActiveStatus($manager);

        if ($manager->id === $tutorUserId) {
            throw new ForbiddenException('Managers cannot reject their own profile.', 'SELF_REJECTION_FORBIDDEN');
        }

        $stmt = $this->pdo->prepare('SELECT user_id, approval_status, dbs_status FROM `tutor_profiles` WHERE `user_id` = ? LIMIT 1');
        $stmt->execute([$tutorUserId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            throw new ValidationException('Tutor profile not found.', 'TUTOR_NOT_FOUND', 404);
        }

        try {
            $this->pdo->beginTransaction();

            $stmtProfile = $this->pdo->prepare('
                UPDATE `tutor_profiles` 
                SET `approval_status` = "REJECTED",
                    `dbs_status` = "REJECTED",
                    `updated_at` = UTC_TIMESTAMP()
                WHERE `user_id` = ?
            ');
            $stmtProfile->execute([$tutorUserId]);

            $stmtUser = $this->pdo->prepare('
                UPDATE `users` 
                SET `status` = "SUSPENDED", `updated_at` = UTC_TIMESTAMP() 
                WHERE `id` = ?
            ');
            $stmtUser->execute([$tutorUserId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new ValidationException('Failed to reject tutor: ' . $e->getMessage(), 'REJECTION_FAILED', 500, [], $e);
        }

        $this->audit->log(
            action: 'MANAGER_REJECT_TUTOR',
            entityType: 'tutor_profile',
            entityId: $tutorUserId,
            actorUserId: $manager->id,
            metadata: [
                'previous_approval_status' => $profile['approval_status'],
                'new_approval_status' => self::APPROVAL_REJECTED,
                'reason' => Validator::sanitizeString($reason),
            ]
        );

        $this->logger->info("Manager ID {$manager->id} rejected Tutor ID {$tutorUserId}");

        // Transactional Email Notification (Dispatched strictly after commit)
        try {
            $stmtUser = $this->pdo->prepare('SELECT email, display_name FROM `users` WHERE `id` = ? LIMIT 1');
            $stmtUser->execute([$tutorUserId]);
            $tutorUser = $stmtUser->fetch();
            if ($tutorUser && !empty($tutorUser['email'])) {
                $this->emailService->send(
                    toEmail: $tutorUser['email'],
                    toName: $tutorUser['display_name'] ?? 'Tutor',
                    subject: 'Update on Your Tutor Application',
                    templateName: 'tutor_rejected',
                    templateData: [
                        'recipient_name' => $tutorUser['display_name'] ?? 'Tutor',
                        'reason' => $reason,
                    ]
                );
            }
        } catch (Throwable $mailEx) {
            $this->logger->error('Failed to dispatch tutor rejected email: ' . $mailEx->getMessage());
        }

        return $this->getProfile($tutorUserId, $manager);
    }

    /**
     * Manager Suspension Action: Immediately suspends an approved tutor.
     *
     * @param int $tutorUserId
     * @param string $reason
     * @param UserContext $manager
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function managerSuspendTutor(int $tutorUserId, string $reason, UserContext $manager): array
    {
        Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
        Authorization::requireActiveStatus($manager);

        if ($manager->id === $tutorUserId) {
            throw new ForbiddenException('Managers cannot suspend their own account.', 'SELF_SUSPENSION_FORBIDDEN');
        }

        $stmt = $this->pdo->prepare('SELECT user_id, approval_status FROM `tutor_profiles` WHERE `user_id` = ? LIMIT 1');
        $stmt->execute([$tutorUserId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            throw new ValidationException('Tutor profile not found.', 'TUTOR_NOT_FOUND', 404);
        }

        try {
            $this->pdo->beginTransaction();

            $stmtProfile = $this->pdo->prepare('
                UPDATE `tutor_profiles` 
                SET `approval_status` = "SUSPENDED", `updated_at` = UTC_TIMESTAMP() 
                WHERE `user_id` = ?
            ');
            $stmtProfile->execute([$tutorUserId]);

            $stmtUser = $this->pdo->prepare('
                UPDATE `users` 
                SET `status` = "SUSPENDED", `updated_at` = UTC_TIMESTAMP() 
                WHERE `id` = ?
            ');
            $stmtUser->execute([$tutorUserId]);

            // Block unpublished availability slots
            $stmtSlots = $this->pdo->prepare('
                UPDATE `availability_slots` 
                SET `status` = "BLOCKED", `updated_at` = UTC_TIMESTAMP() 
                WHERE `tutor_user_id` = ? AND `status` = "PUBLISHED"
            ');
            $stmtSlots->execute([$tutorUserId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new ValidationException('Failed to suspend tutor: ' . $e->getMessage(), 'SUSPENSION_FAILED', 500, [], $e);
        }

        $this->audit->log(
            action: 'MANAGER_SUSPEND_TUTOR',
            entityType: 'tutor_profile',
            entityId: $tutorUserId,
            actorUserId: $manager->id,
            metadata: [
                'previous_approval_status' => $profile['approval_status'],
                'new_approval_status' => self::APPROVAL_SUSPENDED,
                'reason' => Validator::sanitizeString($reason),
            ]
        );

        $this->logger->info("Manager ID {$manager->id} suspended Tutor ID {$tutorUserId}");

        return $this->getProfile($tutorUserId, $manager);
    }

    /**
     * Manager Reinstatement Action: Restores a previously suspended tutor to APPROVED.
     *
     * @param int $tutorUserId
     * @param UserContext $manager
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function managerReinstateTutor(int $tutorUserId, UserContext $manager): array
    {
        Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
        Authorization::requireActiveStatus($manager);

        $stmt = $this->pdo->prepare('SELECT user_id, approval_status, dbs_status FROM `tutor_profiles` WHERE `user_id` = ? LIMIT 1');
        $stmt->execute([$tutorUserId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            throw new ValidationException('Tutor profile not found.', 'TUTOR_NOT_FOUND', 404);
        }

        if ($profile['approval_status'] !== self::APPROVAL_SUSPENDED) {
            throw new ValidationException("Only suspended tutors can be reinstated. Current status: {$profile['approval_status']}.", 'INVALID_LIFECYCLE_STATE', 409);
        }

        try {
            $this->pdo->beginTransaction();

            $stmtProfile = $this->pdo->prepare('
                UPDATE `tutor_profiles` 
                SET `approval_status` = "APPROVED", `updated_at` = UTC_TIMESTAMP() 
                WHERE `user_id` = ?
            ');
            $stmtProfile->execute([$tutorUserId]);

            $stmtUser = $this->pdo->prepare('
                UPDATE `users` 
                SET `status` = "ACTIVE", `updated_at` = UTC_TIMESTAMP() 
                WHERE `id` = ?
            ');
            $stmtUser->execute([$tutorUserId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new ValidationException('Failed to reinstate tutor: ' . $e->getMessage(), 'REINSTATEMENT_FAILED', 500, [], $e);
        }

        $this->audit->log(
            action: 'MANAGER_REINSTATE_TUTOR',
            entityType: 'tutor_profile',
            entityId: $tutorUserId,
            actorUserId: $manager->id,
            metadata: [
                'previous_approval_status' => self::APPROVAL_SUSPENDED,
                'new_approval_status' => self::APPROVAL_APPROVED,
            ]
        );

        $this->logger->info("Manager ID {$manager->id} reinstated Tutor ID {$tutorUserId} to APPROVED");

        return $this->getProfile($tutorUserId, $manager);
    }

    /**
     * Check whether a tutor is currently bookable according to the mandatory safeguarding gate.
     *
     * @param int $tutorUserId
     * @return bool True if active, approved, and DBS verified
     */
    public function isBookable(int $tutorUserId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT u.status, tp.approval_status, tp.dbs_status 
            FROM `users` u 
            JOIN `tutor_profiles` tp ON u.id = tp.user_id 
            WHERE u.id = ? 
            LIMIT 1
        ');
        $stmt->execute([$tutorUserId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        return (
            $row['status'] === 'ACTIVE' &&
            $row['approval_status'] === self::APPROVAL_APPROVED &&
            $row['dbs_status'] === self::DBS_VERIFIED
        );
    }

    /**
     * List all tutors for managerial review queue.
     *
     * @param UserContext $manager
     * @param string|null $filterApproval
     * @return array
     * @throws ForbiddenException
     */
    public function listTutorsForManager(UserContext $manager, ?string $filterApproval = null): array
    {
        Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
        Authorization::requireActiveStatus($manager);

        $sql = '
            SELECT 
                u.id, u.email, u.display_name, u.status, u.created_at as registered_at,
                tp.headline, tp.dbs_status, tp.approval_status, tp.approved_at, tp.approved_by,
                tp.subjects_json, tp.curriculum_json
            FROM `users` u
            JOIN `tutor_profiles` tp ON u.id = tp.user_id
            WHERE u.role = "TUTOR"
        ';
        $params = [];

        if (!empty($filterApproval)) {
            $sql .= ' AND tp.approval_status = ?';
            $params[] = strtoupper($filterApproval);
        }

        $sql .= ' ORDER BY u.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'display_name' => $row['display_name'],
                'status' => $row['status'],
                'headline' => $row['headline'],
                'dbs_status' => $row['dbs_status'],
                'approval_status' => $row['approval_status'],
                'approved_at' => $row['approved_at'],
                'approved_by' => $row['approved_by'] ? (int) $row['approved_by'] : null,
                'subjects' => !empty($row['subjects_json']) ? json_decode($row['subjects_json'], true) : [],
                'curriculum' => !empty($row['curriculum_json']) ? json_decode($row['curriculum_json'], true) : [],
                'is_bookable' => ($row['status'] === 'ACTIVE' && $row['approval_status'] === self::APPROVAL_APPROVED && $row['dbs_status'] === self::DBS_VERIFIED),
                'registered_at' => $row['registered_at'],
            ];
        }, $rows);
    }
}
