<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\UserContext;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Logging\Logger;
use App\Services\Exceptions\BookabilityException;
use App\Services\Exceptions\OverlapException;
use App\Services\Exceptions\ValidationException;
use App\Support\Timezone;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PDO;
use Throwable;

class AvailabilityService
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_BOOKED = 'BOOKED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public const ALLOWED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_BLOCKED,
        self::STATUS_BOOKED,
        self::STATUS_EXPIRED,
    ];

    private PDO $pdo;
    private Logger $logger;
    private AuditService $audit;
    private TutorService $tutorService;

    public function __construct(
        ?PDO $pdo = null,
        ?Logger $logger = null,
        ?AuditService $audit = null,
        ?TutorService $tutorService = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
        $this->audit = $audit ?? new AuditService($this->pdo, $this->logger);
        $this->tutorService = $tutorService ?? new TutorService($this->pdo, $this->logger, $this->audit);
    }

    /**
     * Create a new availability slot for an approved, bookable tutor.
     * Enforces the bookability gate, positive duration, and overlap prevention.
     *
     * @param int $tutorUserId
     * @param string $startsAt
     * @param string $endsAt
     * @param string $timezone 'Europe/London' or 'UTC'
     * @param string $status Default 'PUBLISHED'
     * @param UserContext|null $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws BookabilityException
     * @throws OverlapException
     * @throws ValidationException
     */
    public function createSlot(
        int $tutorUserId,
        string $startsAt,
        string $endsAt,
        mixed $arg4 = 'Europe/London',
        mixed $arg5 = self::STATUS_PUBLISHED,
        ?UserContext $currentUser = null
    ): array {
        $timezone = 'Europe/London';
        $status = self::STATUS_PUBLISHED;

        if ($arg4 instanceof UserContext) {
            $currentUser = $arg4;
        } elseif (is_string($arg4)) {
            $timezone = $arg4;
        }

        if ($arg5 instanceof UserContext) {
            $currentUser = $arg5;
        } elseif (is_string($arg5)) {
            $status = $arg5;
        }

        // 1. Authorization & Ownership
        if ($currentUser !== null && !$currentUser->isManager()) {
            Authorization::assertOwnership($tutorUserId, $currentUser->id, 'You cannot manage availability slots for another tutor.');
            if ($currentUser->status === 'SUSPENDED' || $currentUser->status === 'DELETED') {
                throw new ForbiddenException("Account is {$currentUser->status}. Action denied.", 'ACCOUNT_NOT_ACTIVE');
            }
        }

        // 2. Mandatory Safeguarding Bookability Gate
        // Tutors must have status ACTIVE, approval_status APPROVED, and dbs_status VERIFIED
        if (!$this->tutorService->isBookable($tutorUserId)) {
            throw new BookabilityException(
                'Tutors cannot create or publish availability slots until approved by a manager and DBS is verified.',
                'TUTOR_NOT_BOOKABLE',
                403
            );
        }

        // 3. Timezone conversion and duration validation
        [$startsAtUtc, $endsAtUtc] = $this->parseAndValidateTimes($startsAt, $endsAt, $timezone);

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new ValidationException("Invalid slot status: {$status}.", 'INVALID_STATUS', 422);
        }

        // 4. Overlap Prevention with Concurrency Protection (Transaction + FOR UPDATE)
        try {
            $this->pdo->beginTransaction();

            $this->assertNoOverlap($tutorUserId, $startsAtUtc, $endsAtUtc);

            $stmt = $this->pdo->prepare('
                INSERT INTO `availability_slots` 
                (`tutor_user_id`, `starts_at_utc`, `ends_at_utc`, `status`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ');
            $stmt->execute([$tutorUserId, $startsAtUtc, $endsAtUtc, $status]);
            $slotId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // 5. Audit Logging
        $this->audit->log(
            action: 'AVAILABILITY_SLOT_CREATED',
            entityType: 'availability_slot',
            entityId: $slotId,
            actorUserId: $currentUser?->id ?? $tutorUserId,
            metadata: [
                'tutor_user_id' => $tutorUserId,
                'starts_at_utc' => $startsAtUtc,
                'ends_at_utc' => $endsAtUtc,
                'status' => $status,
            ]
        );

        $this->logger->info("Availability slot created: Tutor ID {$tutorUserId}, Slot ID {$slotId} ({$startsAtUtc} - {$endsAtUtc} UTC)");

        return $this->formatSlot([
            'id' => $slotId,
            'tutor_user_id' => $tutorUserId,
            'starts_at_utc' => $startsAtUtc,
            'ends_at_utc' => $endsAtUtc,
            'status' => $status,
            'created_at' => Timezone::nowUtc(),
            'updated_at' => Timezone::nowUtc(),
        ]);
    }

    /**
     * Update an existing availability slot.
     */
    public function updateSlot(
        int $slotId,
        mixed $arg2,
        mixed $arg3,
        mixed $arg4 = 'Europe/London',
        mixed $arg5 = null,
        mixed $arg6 = null,
        ?UserContext $currentUser = null
    ): array {
        $stmtSlot = $this->pdo->prepare('SELECT id, tutor_user_id, status FROM `availability_slots` WHERE `id` = ? LIMIT 1');
        $stmtSlot->execute([$slotId]);
        $existing = $stmtSlot->fetch();
        if (!$existing) {
            throw new ValidationException('Availability slot not found.', 'SLOT_NOT_FOUND', 404);
        }
        $slotOwnerId = (int) $existing['tutor_user_id'];

        if (is_numeric($arg2) && is_string($arg3)) {
            $tutorUserId = (int) $arg2;
            $startsAt = (string) $arg3;
            $endsAt = (string) $arg4;
            $timezone = is_string($arg5) ? $arg5 : 'Europe/London';
            $status = is_string($arg6) ? $arg6 : null;
            if ($arg5 instanceof UserContext) $currentUser = $arg5;
            if ($arg6 instanceof UserContext) $currentUser = $arg6;
        } else {
            $tutorUserId = $slotOwnerId;
            $startsAt = (string) $arg2;
            $endsAt = (string) $arg3;
            $timezone = is_string($arg4) ? $arg4 : 'Europe/London';
            $status = is_string($arg5) ? $arg5 : null;
            if ($arg4 instanceof UserContext) $currentUser = $arg4;
            if ($arg5 instanceof UserContext) $currentUser = $arg5;
            if ($arg6 instanceof UserContext) $currentUser = $arg6;
        }

        // 1. Authorization
        if ($currentUser !== null && !$currentUser->isManager()) {
            Authorization::assertOwnership($slotOwnerId, $currentUser->id, 'You cannot modify another tutor\'s availability slot.');
            if ($currentUser->status === 'SUSPENDED' || $currentUser->status === 'DELETED') {
                throw new ForbiddenException("Account is {$currentUser->status}. Action denied.", 'ACCOUNT_NOT_ACTIVE');
            }
        }

        if ((int) $existing['tutor_user_id'] !== $tutorUserId) {
            throw new ForbiddenException('Slot does not belong to specified tutor.', 'UNAUTHORIZED_RESOURCE_OWNERSHIP', 403);
        }

        if ($existing['status'] === self::STATUS_BOOKED) {
            throw new ValidationException('Cannot modify a slot that is already booked.', 'SLOT_ALREADY_BOOKED', 409);
        }

        // 4. Validate times
        [$startsAtUtc, $endsAtUtc] = $this->parseAndValidateTimes($startsAt, $endsAt, $timezone);

        $newStatus = $status ?? $existing['status'];
        if (!in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            throw new ValidationException("Invalid slot status: {$newStatus}.", 'INVALID_STATUS', 422);
        }

        // 5. Overlap check excluding self with transaction locking
        try {
            $this->pdo->beginTransaction();

            $this->assertNoOverlap($tutorUserId, $startsAtUtc, $endsAtUtc, $slotId);

            $stmtUpdate = $this->pdo->prepare('
                UPDATE `availability_slots` 
                SET `starts_at_utc` = ?, `ends_at_utc` = ?, `status` = ?, `updated_at` = UTC_TIMESTAMP() 
                WHERE `id` = ?
            ');
            $stmtUpdate->execute([$startsAtUtc, $endsAtUtc, $newStatus, $slotId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // 6. Audit Logging
        $this->audit->log(
            action: 'AVAILABILITY_SLOT_UPDATED',
            entityType: 'availability_slot',
            entityId: $slotId,
            actorUserId: $currentUser?->id ?? $tutorUserId,
            metadata: [
                'tutor_user_id' => $tutorUserId,
                'starts_at_utc' => $startsAtUtc,
                'ends_at_utc' => $endsAtUtc,
                'status' => $newStatus,
            ]
        );

        return $this->formatSlot([
            'id' => $slotId,
            'tutor_user_id' => $tutorUserId,
            'starts_at_utc' => $startsAtUtc,
            'ends_at_utc' => $endsAtUtc,
            'status' => $newStatus,
            'updated_at' => Timezone::nowUtc(),
        ]);
    }

    /**
     * Delete an unbooked availability slot.
     *
     * @param int $slotId
     * @param int $tutorUserId
     * @param UserContext|null $currentUser
     * @return bool
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function deleteSlot(int $slotId, mixed $tutorUserIdOrUser, ?UserContext $currentUser = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT id, tutor_user_id, status FROM `availability_slots` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$slotId]);
        $slot = $stmt->fetch();
        if (!$slot) {
            throw new ValidationException('Availability slot not found.', 'SLOT_NOT_FOUND', 404);
        }

        $slotOwnerId = (int) $slot['tutor_user_id'];

        if ($tutorUserIdOrUser instanceof UserContext) {
            $currentUser = $tutorUserIdOrUser;
            $tutorUserId = $slotOwnerId;
        } else {
            $tutorUserId = (int) $tutorUserIdOrUser;
        }

        if ($currentUser !== null && !$currentUser->isManager()) {
            Authorization::assertOwnership($slotOwnerId, $currentUser->id, 'You cannot delete another tutor\'s availability slot.');
            if ($currentUser->status === 'SUSPENDED' || $currentUser->status === 'DELETED') {
                throw new ForbiddenException("Account is {$currentUser->status}. Action denied.", 'ACCOUNT_NOT_ACTIVE');
            }
        }

        if ($slotOwnerId !== $tutorUserId) {
            throw new ForbiddenException('Slot does not belong to specified tutor.', 'UNAUTHORIZED_RESOURCE_OWNERSHIP', 403);
        }

        if ($slot['status'] === self::STATUS_BOOKED) {
            throw new ValidationException('Cannot delete an availability slot that has active bookings.', 'SLOT_ALREADY_BOOKED', 409);
        }

        $stmtDel = $this->pdo->prepare('DELETE FROM `availability_slots` WHERE `id` = ?');
        $stmtDel->execute([$slotId]);

        $this->audit->log(
            action: 'AVAILABILITY_SLOT_DELETED',
            entityType: 'availability_slot',
            entityId: $slotId,
            actorUserId: $currentUser?->id ?? $tutorUserId,
            metadata: ['tutor_user_id' => $tutorUserId]
        );

        $this->logger->info("Availability slot ID {$slotId} deleted for Tutor ID {$tutorUserId}");

        return true;
    }

    /**
     * Retrieve availability slots for a tutor.
     * Formats results with both UTC database timestamps and Europe/London display strings.
     *
     * @param int $tutorUserId
     * @param string|null $fromUtc Optional start filter
     * @param string|null $toUtc Optional end filter
     * @param string $displayTimezone
     * @return array
     */
    public function getTutorSlots(
        int $tutorUserId,
        ?string $fromUtc = null,
        ?string $toUtc = null,
        string $displayTimezone = 'Europe/London'
    ): array {
        $sql = '
            SELECT id, tutor_user_id, starts_at_utc, ends_at_utc, status, created_at, updated_at 
            FROM `availability_slots` 
            WHERE `tutor_user_id` = ? AND `status` != "EXPIRED"
        ';
        $params = [$tutorUserId];

        if (!empty($fromUtc)) {
            $sql .= ' AND `ends_at_utc` >= ?';
            $params[] = $fromUtc;
        }

        if (!empty($toUtc)) {
            $sql .= ' AND `starts_at_utc` <= ?';
            $params[] = $toUtc;
        }

        $sql .= ' ORDER BY `starts_at_utc` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) use ($displayTimezone) {
            return $this->formatSlot($row, $displayTimezone);
        }, $rows);
    }

    /**
     * Alias convenience method for UI templates.
     */
    public function getSlotsForTutor(
        int $tutorUserId,
        ?UserContext $currentUser = null,
        ?string $fromUtc = null,
        ?string $toUtc = null,
        string $displayTimezone = 'Europe/London'
    ): array {
        return $this->getTutorSlots($tutorUserId, $fromUtc, $toUtc, $displayTimezone);
    }

    /**
     * Assert that candidate time range does not collide with existing slots of the tutor.
     * Overlap condition: (existing.starts < new.ends) AND (existing.ends > new.starts)
     * Adjacent slots: existing.ends == new.starts OR existing.starts == new.ends do NOT overlap.
     *
     * @param int $tutorUserId
     * @param string $startsAtUtc
     * @param string $endsAtUtc
     * @param int|null $excludeSlotId
     * @throws OverlapException
     */
    private function assertNoOverlap(int $tutorUserId, string $startsAtUtc, string $endsAtUtc, ?int $excludeSlotId = null): void
    {
        $sql = '
            SELECT id, starts_at_utc, ends_at_utc, status 
            FROM `availability_slots` 
            WHERE `tutor_user_id` = :tutor_id 
              AND `status` NOT IN ("EXPIRED", "BLOCKED") 
              AND `starts_at_utc` < :ends_at_utc 
              AND `ends_at_utc` > :starts_at_utc
        ';

        if ($excludeSlotId !== null) {
            $sql .= ' AND `id` != :exclude_id';
        }

        $sql .= ' FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $binds = [
            ':tutor_id' => $tutorUserId,
            ':ends_at_utc' => $endsAtUtc,
            ':starts_at_utc' => $startsAtUtc,
        ];
        if ($excludeSlotId !== null) {
            $binds[':exclude_id'] = $excludeSlotId;
        }

        $stmt->execute($binds);
        $overlapping = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($overlapping) {
            $existingId = $overlapping['id'];
            $existingStart = $overlapping['starts_at_utc'];
            $existingEnd = $overlapping['ends_at_utc'];
            $londonStart = Timezone::utcToLondon($existingStart);
            $londonEnd = Timezone::utcToLondon($existingEnd);

            throw new OverlapException(
                "Availability slot overlaps with an existing slot (ID: {$existingId}: {$londonStart} - {$londonEnd} UK time / {$existingStart} - {$existingEnd} UTC).",
                'OVERLAPPING_SLOT',
                409
            );
        }
    }

    /**
     * Parse date/time strings, normalize to UTC, and ensure duration is positive.
     *
     * @param string $startsAt
     * @param string $endsAt
     * @param string $inputTimezone
     * @return array [string $startsAtUtc, string $endsAtUtc]
     * @throws ValidationException
     */
    private function parseAndValidateTimes(string $startsAt, string $endsAt, string $inputTimezone): array
    {
        $startsAt = trim($startsAt);
        $endsAt = trim($endsAt);

        if (empty($startsAt) || empty($endsAt)) {
            throw new ValidationException('Starts at and ends at timestamps are required.', 'VALIDATION_ERROR', 422);
        }

        try {
            $tz = new DateTimeZone($inputTimezone);
            $utcTz = new DateTimeZone('UTC');

            $dtStart = new DateTimeImmutable($startsAt, $tz);
            $dtEnd = new DateTimeImmutable($endsAt, $tz);

            $startsAtUtc = $dtStart->setTimezone($utcTz)->format('Y-m-d H:i:s');
            $endsAtUtc = $dtEnd->setTimezone($utcTz)->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new ValidationException("Invalid date format or timezone ({$inputTimezone}): " . $e->getMessage(), 'INVALID_DATETIME', 422);
        }

        // Check positive duration (ends must be strictly after starts)
        if ($endsAtUtc <= $startsAtUtc) {
            throw new ValidationException('Slot ends_at must be strictly after starts_at (positive duration required).', 'INVALID_DURATION', 422);
        }

        return [$startsAtUtc, $endsAtUtc];
    }

    /**
     * Format a database slot row with both UTC and Europe/London representations.
     *
     * @param array $row
     * @param string $displayTimezone
     * @return array
     */
    private function formatSlot(array $row, string $displayTimezone = 'Europe/London'): array
    {
        $startsUtc = (string) $row['starts_at_utc'];
        $endsUtc = (string) $row['ends_at_utc'];

        return [
            'id' => (int) $row['id'],
            'tutor_user_id' => (int) $row['tutor_user_id'],
            'starts_at_utc' => $startsUtc,
            'ends_at_utc' => $endsUtc,
            'starts_at_london' => Timezone::utcToLondon($startsUtc, 'd M Y, H:i'),
            'ends_at_london' => Timezone::utcToLondon($endsUtc, 'd M Y, H:i'),
            'starts_at_london_iso' => Timezone::utcToLondon($startsUtc, 'Y-m-d H:i:s'),
            'ends_at_london_iso' => Timezone::utcToLondon($endsUtc, 'Y-m-d H:i:s'),
            'is_bst' => Timezone::isBst($startsUtc),
            'status' => $row['status'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
