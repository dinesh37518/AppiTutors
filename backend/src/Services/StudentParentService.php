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
use DateTimeImmutable;
use PDO;
use Throwable;

class StudentParentService
{
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
     * Register a new Student/Parent user and initialize their student profile.
     * Enforces that the client cannot self-assign MANAGER or other roles.
     *
     * @param array $data
     * @param UserContext|null $creator
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function registerStudentParent(array $data, ?UserContext $creator = null): array
    {
        // 1. Strictly block self-assigned MANAGER role
        if (!empty($data['role'])) {
            Authorization::assertCannotSelfAssignManager((string) $data['role']);
        }

        // 2. Discard all client-supplied authorization/role authority fields
        unset(
            $data['role'],
            $data['status'],
            $data['is_manager'],
            $data['is_admin'],
            $data['id'],
            $data['user_id']
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

        if (mb_strlen($displayName) > 150) {
            throw new ValidationException('Display name cannot exceed 150 characters.', 'VALIDATION_ERROR', 422, ['display_name' => 'Too long']);
        }

        // 3. Prevent duplicate account registrations
        $stmtCheck = $this->pdo->prepare('SELECT id, role, firebase_uid, email FROM `users` WHERE `firebase_uid` = ? OR `email` = ? LIMIT 1');
        $stmtCheck->execute([$firebaseUid, $email]);
        if ($stmtCheck->fetch()) {
            throw new ValidationException('User account already exists with this email or Firebase UID.', 'USER_ALREADY_EXISTS', 409);
        }

        $phone = !empty($data['phone']) ? Validator::sanitizeString((string) $data['phone']) : null;
        if ($phone !== null && mb_strlen($phone) > 40) {
            throw new ValidationException('Phone number cannot exceed 40 characters.', 'VALIDATION_ERROR', 422, ['phone' => 'Too long']);
        }

        $postcode = !empty($data['postcode']) ? strtoupper(Validator::sanitizeString((string) $data['postcode'])) : null;
        if ($postcode !== null && mb_strlen($postcode) > 20) {
            throw new ValidationException('Postcode cannot exceed 20 characters.', 'VALIDATION_ERROR', 422, ['postcode' => 'Too long']);
        }

        $parentEmail = !empty($data['parent_email']) ? strtolower(trim((string) $data['parent_email'])) : null;
        if ($parentEmail !== null) {
            if (!Validator::validateEmail($parentEmail)) {
                throw new ValidationException('Invalid parent email address.', 'VALIDATION_ERROR', 422, ['parent_email' => 'Invalid email']);
            }
        }

        // 4. Atomic Registration Transaction
        $this->pdo->beginTransaction();
        try {
            $stmtUser = $this->pdo->prepare(
                'INSERT INTO `users` (`firebase_uid`, `email`, `display_name`, `role`, `status`, `email_verified_at`, `created_at`, `updated_at`)
                 VALUES (?, ?, ?, "STUDENT_PARENT", "ACTIVE", ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $verifiedAt = !empty($data['email_verified']) ? Timezone::nowUtc() : null;
            $stmtUser->execute([$firebaseUid, $email, $displayName, $verifiedAt]);
            $userId = (int) $this->pdo->lastInsertId();

            $stmtProfile = $this->pdo->prepare(
                'INSERT INTO `student_profiles` (`user_id`, `phone`, `postcode`, `parent_email`, `created_at`, `updated_at`)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $stmtProfile->execute([$userId, $phone, $postcode, $parentEmail]);

            $this->pdo->commit();

            // 5. Audit Logging
            $this->logger->info("Student/Parent registered: {$email} (User ID: {$userId})");
            $this->audit->log(
                action: 'USER_REGISTERED_STUDENT',
                entityType: 'user',
                entityId: $userId,
                actorUserId: $userId,
                metadata: ['role' => 'STUDENT_PARENT', 'status' => 'ACTIVE']
            );

            return [
                'user' => [
                    'id' => $userId,
                    'firebase_uid' => $firebaseUid,
                    'email' => $email,
                    'display_name' => $displayName,
                    'role' => 'STUDENT_PARENT',
                    'status' => 'ACTIVE',
                    'email_verified' => !empty($data['email_verified']),
                ],
                'profile' => [
                    'user_id' => $userId,
                    'phone' => $phone,
                    'postcode' => $postcode,
                    'parent_email' => $parentEmail,
                ],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Student/Parent registration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve student/parent profile with strict ownership verification.
     *
     * @param int $userId
     * @param UserContext $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getProfile(int $userId, ?UserContext $currentUser): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        // Ownership enforcement: Only owner or Manager can view profile
        if (!$currentUser->isManager()) {
            Authorization::requireRole($currentUser, [Authorization::ROLE_STUDENT_PARENT]);
            Authorization::assertOwnership($userId, $currentUser->id, 'You cannot access another user\'s profile.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.firebase_uid, u.email, u.display_name, u.role, u.status, u.created_at,
                    sp.phone, sp.postcode, sp.parent_email, sp.updated_at
             FROM `users` u
             LEFT JOIN `student_profiles` sp ON u.id = sp.user_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidationException('Student/Parent profile not found.', 'PROFILE_NOT_FOUND', 404);
        }

        return [
            'id' => (int) $row['id'],
            'firebase_uid' => (string) $row['firebase_uid'],
            'email' => (string) $row['email'],
            'display_name' => (string) $row['display_name'],
            'role' => (string) $row['role'],
            'status' => (string) $row['status'],
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            'postcode' => $row['postcode'] !== null ? (string) $row['postcode'] : null,
            'parent_email' => $row['parent_email'] !== null ? (string) $row['parent_email'] : null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => $row['updated_at'] !== null ? (string) $row['updated_at'] : (string) $row['created_at'],
        ];
    }

    /**
     * Update permitted fields of a student/parent profile.
     *
     * @param int $userId
     * @param array $data
     * @param UserContext $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function updateProfile(int $userId, array $data, ?UserContext $currentUser): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        // Ownership enforcement: Only owner or Manager can update profile
        if (!$currentUser->isManager()) {
            Authorization::requireRole($currentUser, [Authorization::ROLE_STUDENT_PARENT]);
            Authorization::assertOwnership($userId, $currentUser->id, 'You cannot modify another user\'s profile.');
        }

        // Discard protected fields
        unset(
            $data['id'],
            $data['user_id'],
            $data['firebase_uid'],
            $data['email'],
            $data['role'],
            $data['status'],
            $data['is_manager'],
            $data['is_admin'],
            $data['created_at']
        );

        $displayName = null;
        if (array_key_exists('display_name', $data)) {
            $displayName = Validator::sanitizeString((string) $data['display_name']);
            if (empty($displayName)) {
                throw new ValidationException('Display name cannot be empty.', 'VALIDATION_ERROR', 422, ['display_name' => 'Required']);
            }
            if (mb_strlen($displayName) > 150) {
                throw new ValidationException('Display name cannot exceed 150 characters.', 'VALIDATION_ERROR', 422, ['display_name' => 'Too long']);
            }
        }

        $phone = null;
        $hasPhone = array_key_exists('phone', $data);
        if ($hasPhone) {
            $phone = !empty($data['phone']) ? Validator::sanitizeString((string) $data['phone']) : null;
            if ($phone !== null && mb_strlen($phone) > 40) {
                throw new ValidationException('Phone number cannot exceed 40 characters.', 'VALIDATION_ERROR', 422, ['phone' => 'Too long']);
            }
        }

        $postcode = null;
        $hasPostcode = array_key_exists('postcode', $data);
        if ($hasPostcode) {
            $postcode = !empty($data['postcode']) ? strtoupper(Validator::sanitizeString((string) $data['postcode'])) : null;
            if ($postcode !== null && mb_strlen($postcode) > 20) {
                throw new ValidationException('Postcode cannot exceed 20 characters.', 'VALIDATION_ERROR', 422, ['postcode' => 'Too long']);
            }
        }

        $parentEmail = null;
        $hasParentEmail = array_key_exists('parent_email', $data);
        if ($hasParentEmail) {
            $parentEmail = !empty($data['parent_email']) ? strtolower(trim((string) $data['parent_email'])) : null;
            if ($parentEmail !== null && !Validator::validateEmail($parentEmail)) {
                throw new ValidationException('Invalid parent email address.', 'VALIDATION_ERROR', 422, ['parent_email' => 'Invalid email']);
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($displayName !== null) {
                $stmtUser = $this->pdo->prepare('UPDATE `users` SET `display_name` = ?, `updated_at` = UTC_TIMESTAMP() WHERE `id` = ?');
                $stmtUser->execute([$displayName, $userId]);
            }

            if ($hasPhone || $hasPostcode || $hasParentEmail) {
                // Upsert student_profiles
                $stmtCheck = $this->pdo->prepare('SELECT `user_id` FROM `student_profiles` WHERE `user_id` = ? LIMIT 1');
                $stmtCheck->execute([$userId]);
                if ($stmtCheck->fetch()) {
                    $setClauses = [];
                    $params = [];
                    if ($hasPhone) {
                        $setClauses[] = '`phone` = ?';
                        $params[] = $phone;
                    }
                    if ($hasPostcode) {
                        $setClauses[] = '`postcode` = ?';
                        $params[] = $postcode;
                    }
                    if ($hasParentEmail) {
                        $setClauses[] = '`parent_email` = ?';
                        $params[] = $parentEmail;
                    }
                    $setClauses[] = '`updated_at` = UTC_TIMESTAMP()';
                    $params[] = $userId;

                    $sql = 'UPDATE `student_profiles` SET ' . implode(', ', $setClauses) . ' WHERE `user_id` = ?';
                    $stmtUpdate = $this->pdo->prepare($sql);
                    $stmtUpdate->execute($params);
                } else {
                    $stmtInsert = $this->pdo->prepare(
                        'INSERT INTO `student_profiles` (`user_id`, `phone`, `postcode`, `parent_email`, `created_at`, `updated_at`)
                         VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                    );
                    $stmtInsert->execute([$userId, $phone, $postcode, $parentEmail]);
                }
            }

            $this->pdo->commit();

            // Audit
            $this->audit->log(
                action: 'STUDENT_PROFILE_UPDATED',
                entityType: 'user',
                entityId: $userId,
                actorUserId: $currentUser->id,
                metadata: [
                    'updated_fields' => array_keys($data),
                ]
            );

            return $this->getProfile($userId, $currentUser);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Student/Parent profile update error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new child profile associated with the authenticated parent.
     * Enforces that child cannot be created under another parent's identity.
     *
     * @param int $parentUserId
     * @param array $data
     * @param UserContext $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function createChild(int $parentUserId, array $data, ?UserContext $currentUser): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        // Ownership enforcement: Only parent themselves or Manager can add a child
        if (!$currentUser->isManager()) {
            Authorization::requireRole($currentUser, [Authorization::ROLE_STUDENT_PARENT]);
            Authorization::assertOwnership($parentUserId, $currentUser->id, 'You cannot create child records for another parent.');
        }

        // Discard protected fields
        unset($data['id'], $data['parent_user_id'], $data['active'], $data['created_at']);

        $firstName = Validator::sanitizeString((string) ($data['first_name'] ?? ''));
        if (empty($firstName)) {
            throw new ValidationException('Child first name is required.', 'VALIDATION_ERROR', 422, ['first_name' => 'Required']);
        }
        if (mb_strlen($firstName) > 100) {
            throw new ValidationException('Child first name cannot exceed 100 characters.', 'VALIDATION_ERROR', 422, ['first_name' => 'Too long']);
        }

        $lastName = !empty($data['last_name']) ? Validator::sanitizeString((string) $data['last_name']) : null;
        if ($lastName !== null && mb_strlen($lastName) > 100) {
            throw new ValidationException('Child last name cannot exceed 100 characters.', 'VALIDATION_ERROR', 422, ['last_name' => 'Too long']);
        }

        $dob = !empty($data['date_of_birth']) ? trim((string) $data['date_of_birth']) : null;
        if ($dob !== null) {
            if (!Validator::validateDate($dob, 'Y-m-d')) {
                throw new ValidationException('Date of birth must be a valid date formatted as YYYY-MM-DD.', 'VALIDATION_ERROR', 422, ['date_of_birth' => 'Invalid date format']);
            }
            $dobDate = new DateTimeImmutable($dob);
            $today = new DateTimeImmutable('today');
            if ($dobDate > $today) {
                throw new ValidationException('Date of birth cannot be in the future.', 'VALIDATION_ERROR', 422, ['date_of_birth' => 'Future date not allowed']);
            }
        }

        $schoolYear = !empty($data['school_year']) ? Validator::sanitizeString((string) $data['school_year']) : null;
        if ($schoolYear !== null && mb_strlen($schoolYear) > 50) {
            throw new ValidationException('School year cannot exceed 50 characters.', 'VALIDATION_ERROR', 422, ['school_year' => 'Too long']);
        }

        $curriculum = !empty($data['curriculum']) ? Validator::sanitizeString((string) $data['curriculum']) : null;
        if ($curriculum !== null && mb_strlen($curriculum) > 100) {
            throw new ValidationException('Curriculum cannot exceed 100 characters.', 'VALIDATION_ERROR', 422, ['curriculum' => 'Too long']);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `children` (`parent_user_id`, `first_name`, `last_name`, `date_of_birth`, `school_year`, `curriculum`, `active`, `created_at`)
             VALUES (?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $parentUserId,
            $firstName,
            $lastName,
            $dob,
            $schoolYear,
            $curriculum,
        ]);
        $childId = (int) $this->pdo->lastInsertId();

        $this->logger->info("Child created: ID {$childId} for parent ID {$parentUserId}");
        $this->audit->log(
            action: 'CHILD_CREATED',
            entityType: 'child',
            entityId: $childId,
            actorUserId: $currentUser->id,
            metadata: [
                'parent_user_id' => $parentUserId,
                'first_name' => $firstName,
                'school_year' => $schoolYear,
                'curriculum' => $curriculum,
            ]
        );

        return [
            'id' => $childId,
            'parent_user_id' => $parentUserId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => $dob,
            'school_year' => $schoolYear,
            'curriculum' => $curriculum,
            'active' => 1,
        ];
    }

    /**
     * Retrieve all permitted children for a given parent with strict ownership verification.
     * Supports multiple children per parent as defined by the approved schema.
     *
     * @param int $parentUserId
     * @param UserContext $currentUser
     * @param bool $onlyActive
     * @return array
     * @throws ForbiddenException
     */
    public function getChildren(int $parentUserId, ?UserContext $currentUser, bool $onlyActive = true): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        // Ownership enforcement: Only parent themselves or Manager can list children
        if (!$currentUser->isManager()) {
            Authorization::requireRole($currentUser, [Authorization::ROLE_STUDENT_PARENT]);
            Authorization::assertOwnership($parentUserId, $currentUser->id, 'You cannot view child records of another parent.');
        }

        $sql = 'SELECT `id`, `parent_user_id`, `first_name`, `last_name`, `date_of_birth`, `school_year`, `curriculum`, `active`, `created_at`
                FROM `children`
                WHERE `parent_user_id` = ?';
        if ($onlyActive) {
            $sql .= ' AND `active` = 1';
        }
        $sql .= ' ORDER BY `id` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$parentUserId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => (int) $row['id'],
                'parent_user_id' => (int) $row['parent_user_id'],
                'first_name' => (string) $row['first_name'],
                'last_name' => $row['last_name'] !== null ? (string) $row['last_name'] : null,
                'date_of_birth' => $row['date_of_birth'] !== null ? (string) $row['date_of_birth'] : null,
                'school_year' => $row['school_year'] !== null ? (string) $row['school_year'] : null,
                'curriculum' => $row['curriculum'] !== null ? (string) $row['curriculum'] : null,
                'active' => (int) $row['active'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $results;
    }

    /**
     * Retrieve a specific child by ID, strictly enforcing parent-child ownership.
     *
     * @param int $childId
     * @param UserContext $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getChild(int $childId, ?UserContext $currentUser): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        $stmt = $this->pdo->prepare(
            'SELECT `id`, `parent_user_id`, `first_name`, `last_name`, `date_of_birth`, `school_year`, `curriculum`, `active`, `created_at`
             FROM `children`
             WHERE `id` = ? LIMIT 1'
        );
        $stmt->execute([$childId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidationException('Child record not found.', 'CHILD_NOT_FOUND', 404);
        }

        // Ownership verification: Only the parent of this child or a Manager may access
        if (!$currentUser->isManager()) {
            Authorization::requireRole($currentUser, [Authorization::ROLE_STUDENT_PARENT]);
            if ((int) $row['parent_user_id'] !== $currentUser->id) {
                throw new ForbiddenException('Access denied to this child record.', 'UNAUTHORIZED_RESOURCE_OWNERSHIP');
            }
        }

        return [
            'id' => (int) $row['id'],
            'parent_user_id' => (int) $row['parent_user_id'],
            'first_name' => (string) $row['first_name'],
            'last_name' => $row['last_name'] !== null ? (string) $row['last_name'] : null,
            'date_of_birth' => $row['date_of_birth'] !== null ? (string) $row['date_of_birth'] : null,
            'school_year' => $row['school_year'] !== null ? (string) $row['school_year'] : null,
            'curriculum' => $row['curriculum'] !== null ? (string) $row['curriculum'] : null,
            'active' => (int) $row['active'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * Update permitted fields of an existing child record with strict parent ownership verification.
     *
     * @param int $childId
     * @param array $data
     * @param UserContext $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function updateChild(int $childId, array $data, ?UserContext $currentUser): array
    {
        // Enforce existence and ownership
        $existing = $this->getChild($childId, $currentUser);

        // Discard protected fields
        unset(
            $data['id'],
            $data['parent_user_id'],
            $data['created_at']
        );

        $firstName = $existing['first_name'];
        if (array_key_exists('first_name', $data)) {
            $firstName = Validator::sanitizeString((string) $data['first_name']);
            if (empty($firstName)) {
                throw new ValidationException('Child first name cannot be empty.', 'VALIDATION_ERROR', 422, ['first_name' => 'Required']);
            }
            if (mb_strlen($firstName) > 100) {
                throw new ValidationException('Child first name cannot exceed 100 characters.', 'VALIDATION_ERROR', 422, ['first_name' => 'Too long']);
            }
        }

        $lastName = $existing['last_name'];
        if (array_key_exists('last_name', $data)) {
            $lastName = !empty($data['last_name']) ? Validator::sanitizeString((string) $data['last_name']) : null;
            if ($lastName !== null && mb_strlen($lastName) > 100) {
                throw new ValidationException('Child last name cannot exceed 100 characters.', 'VALIDATION_ERROR', 422, ['last_name' => 'Too long']);
            }
        }

        $dob = $existing['date_of_birth'];
        if (array_key_exists('date_of_birth', $data)) {
            $dob = !empty($data['date_of_birth']) ? trim((string) $data['date_of_birth']) : null;
            if ($dob !== null) {
                if (!Validator::validateDate($dob, 'Y-m-d')) {
                    throw new ValidationException('Date of birth must be a valid date formatted as YYYY-MM-DD.', 'VALIDATION_ERROR', 422, ['date_of_birth' => 'Invalid date format']);
                }
                $dobDate = new DateTimeImmutable($dob);
                $today = new DateTimeImmutable('today');
                if ($dobDate > $today) {
                    throw new ValidationException('Date of birth cannot be in the future.', 'VALIDATION_ERROR', 422, ['date_of_birth' => 'Future date not allowed']);
                }
            }
        }

        $schoolYear = $existing['school_year'];
        if (array_key_exists('school_year', $data)) {
            $schoolYear = !empty($data['school_year']) ? Validator::sanitizeString((string) $data['school_year']) : null;
            if ($schoolYear !== null && mb_strlen($schoolYear) > 50) {
                throw new ValidationException('School year cannot exceed 50 characters.', 'VALIDATION_ERROR', 422, ['school_year' => 'Too long']);
            }
        }

        $curriculum = $existing['curriculum'];
        if (array_key_exists('curriculum', $data)) {
            $curriculum = !empty($data['curriculum']) ? Validator::sanitizeString((string) $data['curriculum']) : null;
            if ($curriculum !== null && mb_strlen($curriculum) > 100) {
                throw new ValidationException('Curriculum cannot exceed 100 characters.', 'VALIDATION_ERROR', 422, ['curriculum' => 'Too long']);
            }
        }

        $active = $existing['active'];
        if (array_key_exists('active', $data)) {
            $active = (int) $data['active'] ? 1 : 0;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE `children`
             SET `first_name` = ?, `last_name` = ?, `date_of_birth` = ?, `school_year` = ?, `curriculum` = ?, `active` = ?
             WHERE `id` = ?'
        );
        $stmt->execute([
            $firstName,
            $lastName,
            $dob,
            $schoolYear,
            $curriculum,
            $active,
            $childId,
        ]);

        $this->audit->log(
            action: 'CHILD_UPDATED',
            entityType: 'child',
            entityId: $childId,
            actorUserId: $currentUser->id,
            metadata: [
                'parent_user_id' => $existing['parent_user_id'],
                'updated_fields' => array_keys($data),
            ]
        );

        return $this->getChild($childId, $currentUser);
    }

    /**
     * Delete or soft-deactivate an existing child record with strict parent ownership verification.
     *
     * @param int $childId
     * @param UserContext $currentUser
     * @param bool $softDelete
     * @return bool
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function deleteChild(int $childId, ?UserContext $currentUser, bool $softDelete = true): bool
    {
        // Enforce existence and ownership
        $existing = $this->getChild($childId, $currentUser);

        if ($softDelete) {
            $stmt = $this->pdo->prepare('UPDATE `children` SET `active` = 0 WHERE `id` = ?');
            $stmt->execute([$childId]);
        } else {
            $stmt = $this->pdo->prepare('DELETE FROM `children` WHERE `id` = ?');
            $stmt->execute([$childId]);
        }

        $this->logger->info("Child deleted: ID {$childId} by user ID {$currentUser->id} (soft: " . ($softDelete ? 'yes' : 'no') . ')');
        $this->audit->log(
            action: 'CHILD_DELETED',
            entityType: 'child',
            entityId: $childId,
            actorUserId: $currentUser->id,
            metadata: [
                'parent_user_id' => $existing['parent_user_id'],
                'soft_delete' => $softDelete,
            ]
        );

        return true;
    }
}
