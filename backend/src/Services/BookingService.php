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

class BookingService
{
    // Approved Master Document Booking Lifecycle States (NO RESCHEDULED state)
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_RESCHEDULE_PROPOSED = 'RESCHEDULE_PROPOSED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_SYSTEM_CANCELLED = 'SYSTEM_CANCELLED';
    public const STATUS_COMPLETED = 'COMPLETED';

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_REJECTED,
        self::STATUS_RESCHEDULE_PROPOSED,
        self::STATUS_CANCELLED,
        self::STATUS_SYSTEM_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    private PDO $pdo;
    private Logger $logger;
    private AuditService $audit;
    private TutorService $tutorService;
    private StudentParentService $studentParentService;
    private EmailService $emailService;

    public function __construct(
        ?PDO $pdo = null,
        ?Logger $logger = null,
        ?AuditService $audit = null,
        ?TutorService $tutorService = null,
        ?StudentParentService $studentParentService = null,
        ?EmailService $emailService = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
        $this->audit = $audit ?? new AuditService($this->pdo, $this->logger);
        $this->tutorService = $tutorService ?? new TutorService($this->pdo, $this->logger, $this->audit);
        $this->studentParentService = $studentParentService ?? new StudentParentService($this->pdo, $this->logger, $this->audit);
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
     * Create a new booking request in PENDING state against an eligible tutor's published availability slot.
     * Enforces concurrency safety via transactional SELECT ... FOR UPDATE row locking.
     *
     * @param array $data ['tutor_user_id' => int, 'slot_id' => int, 'child_id' => ?int, 'inquiry_notes' => ?string]
     * @param UserContext|null $currentUser
     * @return array Created booking details
     * @throws ForbiddenException
     * @throws BookabilityException
     * @throws ValidationException
     */
    public function createBooking(array $data, ?UserContext $currentUser): array
    {
        // 1. Authentication & Role Authority Check
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        // Only STUDENT_PARENT or MANAGER can create a booking request
        if (!$currentUser->isManager()) {
            Authorization::requireRole($currentUser, [Authorization::ROLE_STUDENT_PARENT]);
        }

        // 2. Validate Input Parameters
        $tutorUserId = isset($data['tutor_user_id']) ? (int) $data['tutor_user_id'] : 0;
        $slotId = isset($data['slot_id']) ? (int) $data['slot_id'] : 0;
        $childId = !empty($data['child_id']) ? (int) $data['child_id'] : null;

        if ($tutorUserId <= 0) {
            throw new ValidationException('Tutor user ID is required.', 'VALIDATION_ERROR', 422, ['tutor_user_id' => 'Required']);
        }

        if ($slotId <= 0) {
            throw new ValidationException('Availability slot ID is required.', 'VALIDATION_ERROR', 422, ['slot_id' => 'Required']);
        }

        // 3. Child Ownership Verification (IDOR/BOLA Protection)
        if ($childId !== null) {
            $stmtChild = $this->pdo->prepare('SELECT id, parent_user_id, active FROM `children` WHERE id = ? LIMIT 1');
            $stmtChild->execute([$childId]);
            $childRow = $stmtChild->fetch(PDO::FETCH_ASSOC);

            if (!$childRow) {
                throw new ValidationException('Child record not found.', 'CHILD_NOT_FOUND', 404);
            }

            if (!$currentUser->isManager()) {
                if ((int) $childRow['parent_user_id'] !== $currentUser->id) {
                    throw new ForbiddenException(
                        'You cannot create a booking for a child that does not belong to your account.',
                        'UNAUTHORIZED_RESOURCE_OWNERSHIP'
                    );
                }
            }

            if ((int) $childRow['active'] !== 1) {
                throw new ValidationException('Selected child profile is inactive.', 'CHILD_INACTIVE', 422);
            }
        }

        // 4. Sanitize and Validate Inquiry Notes
        $inquiryNotes = null;
        if (!empty($data['inquiry_notes'])) {
            $rawNotes = (string) $data['inquiry_notes'];
            if (mb_strlen($rawNotes) > 2000) {
                throw new ValidationException('Inquiry notes cannot exceed 2000 characters.', 'VALIDATION_ERROR', 422, ['inquiry_notes' => 'Too long']);
            }
            $inquiryNotes = Validator::sanitizeString($rawNotes);
        }

        // 5. Concurrency-Safe Booking Transaction with Exclusive Row Lock
        $this->pdo->beginTransaction();
        try {
            // Acquire exclusive row lock on target availability slot
            $stmtSlot = $this->pdo->prepare('
                SELECT id, tutor_user_id, starts_at_utc, ends_at_utc, status, max_students 
                FROM `availability_slots` 
                WHERE id = ? 
                FOR UPDATE
            ');
            $stmtSlot->execute([$slotId]);
            $slot = $stmtSlot->fetch(PDO::FETCH_ASSOC);

            // 5a. Verify slot existence
            if (!$slot) {
                throw new ValidationException('Availability slot not found.', 'SLOT_NOT_FOUND', 404);
            }

            // 5b. Verify slot belongs to requested tutor
            if ((int) $slot['tutor_user_id'] !== $tutorUserId) {
                throw new ValidationException(
                    'Availability slot does not belong to the requested tutor.',
                    'SLOT_TUTOR_MISMATCH',
                    422
                );
            }

            // 5c. Verify Tutor Eligibility (Bookability Gate)
            // Tutors must have status ACTIVE, approval_status APPROVED, and dbs_status VERIFIED
            if (!$this->tutorService->isBookable($tutorUserId)) {
                throw new BookabilityException(
                    'Tutor is currently not eligible for booking (requires verified Enhanced DBS and manager approval).',
                    'TUTOR_NOT_BOOKABLE',
                    403
                );
            }

            // 5d. Verify slot is currently available (PUBLISHED)
            if ($slot['status'] !== AvailabilityService::STATUS_PUBLISHED) {
                throw new ValidationException(
                    "Availability slot is no longer available for booking (status: {$slot['status']}).",
                    'SLOT_UNAVAILABLE',
                    409
                );
            }

            // 5e. Concurrency-safe 1-to-many capacity verification
            $maxStudents = (int) ($slot['max_students'] ?? 1);
            if ($maxStudents < 1) {
                $maxStudents = 1;
            }

            $stmtCount = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM `bookings` 
                WHERE `slot_id` = ? AND `status` IN ('PENDING', 'CONFIRMED')
            ");
            $stmtCount->execute([$slotId]);
            $activeCount = (int) $stmtCount->fetchColumn();

            if ($activeCount >= $maxStudents) {
                throw new ValidationException(
                    "Availability slot has reached maximum capacity of {$maxStudents} student(s).",
                    'SLOT_CAPACITY_REACHED',
                    409
                );
            }

            // 5f. Verify slot starts in the future (UTC)
            $nowUtc = Timezone::nowUtc();
            if ($slot['starts_at_utc'] <= $nowUtc) {
                throw new ValidationException(
                    'Cannot book an availability slot in the past.',
                    'SLOT_EXPIRED',
                    422
                );
            }

            // 5g. Insert PENDING booking record
            $stmtBooking = $this->pdo->prepare('
                INSERT INTO `bookings` (
                    `student_user_id`, `child_id`, `tutor_user_id`, `slot_id`,
                    `status`, `inquiry_notes`, `proposed_starts_at_utc`, `proposed_ends_at_utc`,
                    `created_at`, `updated_at`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ');
            $stmtBooking->execute([
                $currentUser->id,
                $childId,
                $tutorUserId,
                $slotId,
                self::STATUS_PENDING,
                $inquiryNotes,
                $slot['starts_at_utc'],
                $slot['ends_at_utc'],
            ]);
            $bookingId = (int) $this->pdo->lastInsertId();

            // 5h. Record initial state in booking_status_history
            $metadataJson = json_encode([
                'slot_id' => $slotId,
                'child_id' => $childId,
                'starts_at_utc' => $slot['starts_at_utc'],
                'ends_at_utc' => $slot['ends_at_utc'],
                'capacity' => $maxStudents,
                'current_student_number' => $activeCount + 1,
            ], JSON_UNESCAPED_SLASHES);

            $stmtHistory = $this->pdo->prepare('
                INSERT INTO `booking_status_history` (
                    `booking_id`, `changed_by_user_id`, `old_status`, `new_status`, `reason`, `metadata_json`, `created_at`
                ) VALUES (?, ?, NULL, ?, ?, ?, UTC_TIMESTAMP())
            ');
            $stmtHistory->execute([
                $bookingId,
                $currentUser->id,
                self::STATUS_PENDING,
                'Initial booking request created by student/parent',
                $metadataJson,
            ]);

            // 5i. Update availability slot state: Mark BOOKED only if capacity is full
            $newActiveCount = $activeCount + 1;
            if ($newActiveCount >= $maxStudents) {
                $stmtUpdateSlot = $this->pdo->prepare('
                    UPDATE `availability_slots` 
                    SET `status` = ?, `updated_at` = UTC_TIMESTAMP() 
                    WHERE `id` = ?
                ');
                $stmtUpdateSlot->execute([
                    AvailabilityService::STATUS_BOOKED,
                    $slotId,
                ]);
            }

            // Commit transaction
            $this->pdo->commit();

            // 6. Audit Logging
            $this->audit->log(
                action: 'BOOKING_CREATED',
                entityType: 'booking',
                entityId: $bookingId,
                actorUserId: $currentUser->id,
                metadata: [
                    'tutor_user_id' => $tutorUserId,
                    'slot_id' => $slotId,
                    'child_id' => $childId,
                    'status' => self::STATUS_PENDING,
                ]
            );

            $this->logger->info("Booking created: ID {$bookingId} (Student ID: {$currentUser->id}, Tutor ID: {$tutorUserId}, Slot ID: {$slotId})");

            $bookingDetails = $this->getBooking($bookingId, $currentUser);

            // 7. Transactional Email Notification (Dispatched strictly after commit)
            try {
                $slotDisplay = Timezone::utcToLondonDisplay($slot['starts_at_utc']) . ' - ' . Timezone::utcToLondonDisplay($slot['ends_at_utc']);
                $childName = $bookingDetails['child_name'];
                $subject = 'New Lesson Request: ' . ($childName ?? $bookingDetails['student_name']);

                $this->emailService->send(
                    toEmail: $bookingDetails['tutor_email'],
                    toName: $bookingDetails['tutor_name'],
                    subject: $subject,
                    templateName: 'booking_inquiry_received',
                    templateData: [
                        'recipient_name' => $bookingDetails['tutor_name'],
                        'student_name' => $bookingDetails['student_name'],
                        'child_name' => $childName,
                        'child_school_year' => $bookingDetails['child_school_year'] ?? null,
                        'slot_time' => $slotDisplay,
                        'notes' => $inquiryNotes,
                        'booking_id' => $bookingId,
                    ]
                );
            } catch (Throwable $mailEx) {
                $this->logger->error('Failed to dispatch booking inquiry notification email: ' . $mailEx->getMessage());
            }

            return $bookingDetails;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Booking creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve a specific booking by ID, strictly enforcing actor ownership.
     *
     * @param int $bookingId
     * @param UserContext|null $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getBooking(int $bookingId, ?UserContext $currentUser): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        $stmt = $this->pdo->prepare('
            SELECT 
                b.id, b.student_user_id, b.child_id, b.tutor_user_id, b.slot_id,
                b.status, b.inquiry_notes, b.proposed_starts_at_utc, b.proposed_ends_at_utc,
                b.confirmed_starts_at_utc, b.confirmed_ends_at_utc,
                b.created_at, b.updated_at,
                stu.display_name AS student_name, stu.email AS student_email,
                tut.display_name AS tutor_name, tut.email AS tutor_email,
                ch.first_name AS child_first_name, ch.last_name AS child_last_name,
                ch.school_year AS child_school_year, ch.curriculum AS child_curriculum,
                sl.starts_at_utc AS slot_starts_at, sl.ends_at_utc AS slot_ends_at, sl.status AS slot_status
            FROM `bookings` b
            JOIN `users` stu ON b.student_user_id = stu.id
            JOIN `users` tut ON b.tutor_user_id = tut.id
            LEFT JOIN `children` ch ON b.child_id = ch.id
            LEFT JOIN `availability_slots` sl ON b.slot_id = sl.id
            WHERE b.id = ?
            LIMIT 1
        ');
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidationException('Booking record not found.', 'BOOKING_NOT_FOUND', 404);
        }

        // Ownership Verification:
        // - Manager has global oversight
        // - Student/Parent can only view own bookings
        // - Tutor can only view bookings assigned to them
        if (!$currentUser->isManager()) {
            if ($currentUser->isStudentParent()) {
                if ((int) $row['student_user_id'] !== $currentUser->id) {
                    throw new ForbiddenException(
                        'You do not have permission to view this booking.',
                        'UNAUTHORIZED_RESOURCE_OWNERSHIP'
                    );
                }
            } elseif ($currentUser->isTutor()) {
                if ((int) $row['tutor_user_id'] !== $currentUser->id) {
                    throw new ForbiddenException(
                        'You do not have permission to view this booking.',
                        'UNAUTHORIZED_RESOURCE_OWNERSHIP'
                    );
                }
            } else {
                throw new ForbiddenException('Access denied.', 'UNAUTHORIZED_RESOURCE_OWNERSHIP');
            }
        }

        // Retrieve status history audit trail
        $stmtHistory = $this->pdo->prepare('
            SELECT bsh.id, bsh.booking_id, bsh.changed_by_user_id, bsh.old_status, bsh.new_status,
                   bsh.reason, bsh.metadata_json, bsh.created_at,
                   u.display_name AS changed_by_name, u.role AS changed_by_role
            FROM `booking_status_history` bsh
            LEFT JOIN `users` u ON bsh.changed_by_user_id = u.id
            WHERE bsh.booking_id = ?
            ORDER BY bsh.id ASC
        ');
        $stmtHistory->execute([$bookingId]);
        $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

        $parsedHistory = array_map(function ($h) {
            return [
                'id' => (int) $h['id'],
                'changed_by_user_id' => $h['changed_by_user_id'] ? (int) $h['changed_by_user_id'] : null,
                'changed_by_name' => $h['changed_by_name'] ?? 'System',
                'changed_by_role' => $h['changed_by_role'] ?? 'SYSTEM',
                'old_status' => $h['old_status'],
                'new_status' => $h['new_status'],
                'reason' => $h['reason'],
                'metadata' => !empty($h['metadata_json']) ? json_decode($h['metadata_json'], true) : [],
                'created_at' => $h['created_at'],
            ];
        }, $history);

        $meetingLink = null;
        foreach (array_reverse($parsedHistory) as $h) {
            if (!empty($h['metadata']['meeting_link'])) {
                $meetingLink = (string) $h['metadata']['meeting_link'];
                break;
            }
        }

        return [
            'id' => (int) $row['id'],
            'student_user_id' => (int) $row['student_user_id'],
            'student_name' => $row['student_name'],
            'student_email' => $row['student_email'],
            'child_id' => $row['child_id'] ? (int) $row['child_id'] : null,
            'child_name' => $row['child_id'] ? trim($row['child_first_name'] . ' ' . ($row['child_last_name'] ?? '')) : null,
            'child_school_year' => $row['child_school_year'],
            'child_curriculum' => $row['child_curriculum'],
            'tutor_user_id' => (int) $row['tutor_user_id'],
            'tutor_name' => $row['tutor_name'],
            'tutor_email' => $row['tutor_email'],
            'slot_id' => $row['slot_id'] ? (int) $row['slot_id'] : null,
            'slot_starts_at' => $row['slot_starts_at'],
            'slot_ends_at' => $row['slot_ends_at'],
            'status' => $row['status'],
            'inquiry_notes' => $row['inquiry_notes'],
            'proposed_starts_at_utc' => $row['proposed_starts_at_utc'],
            'proposed_ends_at_utc' => $row['proposed_ends_at_utc'],
            'confirmed_starts_at_utc' => $row['confirmed_starts_at_utc'],
            'confirmed_ends_at_utc' => $row['confirmed_ends_at_utc'],
            'meeting_link' => $meetingLink,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'history' => $parsedHistory,
        ];
    }

    /**
     * List bookings for the authenticated actor, strictly respecting role boundaries.
     *
     * @param UserContext|null $currentUser
     * @param array $filters ['status' => ?string, 'tutor_id' => ?int, 'student_id' => ?int]
     * @return array List of bookings
     * @throws ForbiddenException
     */
    public function listBookings(?UserContext $currentUser, array $filters = []): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        $sql = '
            SELECT 
                b.id, b.student_user_id, b.child_id, b.tutor_user_id, b.slot_id,
                b.status, b.inquiry_notes, b.proposed_starts_at_utc, b.proposed_ends_at_utc,
                b.confirmed_starts_at_utc, b.confirmed_ends_at_utc,
                b.created_at, b.updated_at,
                stu.display_name AS student_name,
                tut.display_name AS tutor_name,
                ch.first_name AS child_first_name, ch.last_name AS child_last_name,
                ch.school_year AS child_school_year, ch.curriculum AS child_curriculum,
                (SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata_json, "$.meeting_link")) FROM booking_status_history WHERE booking_id = b.id AND metadata_json LIKE "%meeting_link%" ORDER BY id DESC LIMIT 1) AS meeting_link
            FROM `bookings` b
            JOIN `users` stu ON b.student_user_id = stu.id
            JOIN `users` tut ON b.tutor_user_id = tut.id
            LEFT JOIN `children` ch ON b.child_id = ch.id
            WHERE 1=1
        ';
        $params = [];

        // Role-based scoping
        if ($currentUser->isStudentParent()) {
            $sql .= ' AND b.student_user_id = ?';
            $params[] = $currentUser->id;
        } elseif ($currentUser->isTutor()) {
            $sql .= ' AND b.tutor_user_id = ?';
            $params[] = $currentUser->id;
        } elseif ($currentUser->isManager()) {
            if (!empty($filters['student_id'])) {
                $sql .= ' AND b.student_user_id = ?';
                $params[] = (int) $filters['student_id'];
            }
            if (!empty($filters['tutor_id'])) {
                $sql .= ' AND b.tutor_user_id = ?';
                $params[] = (int) $filters['tutor_id'];
            }
        } else {
            throw new ForbiddenException('Access denied.', 'UNAUTHORIZED_RESOURCE_OWNERSHIP');
        }

        // Optional status filter
        if (!empty($filters['status'])) {
            $statusFilter = strtoupper(trim((string) $filters['status']));
            if (in_array($statusFilter, self::ALLOWED_STATUSES, true)) {
                $sql .= ' AND b.status = ?';
                $params[] = $statusFilter;
            }
        }

        $sql .= ' ORDER BY b.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'student_user_id' => (int) $row['student_user_id'],
                'student_name' => $row['student_name'],
                'child_id' => $row['child_id'] ? (int) $row['child_id'] : null,
                'child_name' => $row['child_id'] ? trim($row['child_first_name'] . ' ' . ($row['child_last_name'] ?? '')) : null,
                'child_school_year' => $row['child_school_year'],
                'child_curriculum' => $row['child_curriculum'],
                'tutor_user_id' => (int) $row['tutor_user_id'],
                'tutor_name' => $row['tutor_name'],
                'slot_id' => $row['slot_id'] ? (int) $row['slot_id'] : null,
                'status' => $row['status'],
                'inquiry_notes' => $row['inquiry_notes'],
                'proposed_starts_at_utc' => $row['proposed_starts_at_utc'],
                'proposed_ends_at_utc' => $row['proposed_ends_at_utc'],
                'confirmed_starts_at_utc' => $row['confirmed_starts_at_utc'],
                'confirmed_ends_at_utc' => $row['confirmed_ends_at_utc'],
                'meeting_link' => !empty($row['meeting_link']) ? (string) $row['meeting_link'] : null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);
    }

    /**
     * Confirm a PENDING booking request.
     * Transition: PENDING -> CONFIRMED
     * Actor: Assigned Tutor or Manager.
     *
     * @param int $bookingId
     * @param UserContext|null $currentUser
     * @param string|null $reason
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function confirmBooking(
        int $bookingId,
        ?UserContext $currentUser,
        ?string $reason = null,
        ?string $meetingLink = null
    ): array {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        $this->pdo->beginTransaction();
        try {
            // Lock booking row
            $stmt = $this->pdo->prepare('SELECT * FROM `bookings` WHERE id = ? FOR UPDATE');
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                throw new ValidationException('Booking record not found.', 'BOOKING_NOT_FOUND', 404);
            }

            // Actor verification: Only assigned tutor or manager can confirm
            if (!$currentUser->isManager()) {
                if ((int) $booking['tutor_user_id'] !== $currentUser->id) {
                    throw new ForbiddenException(
                        'You do not have permission to confirm this booking.',
                        'UNAUTHORIZED_RESOURCE_OWNERSHIP'
                    );
                }
            }

            // State validation: only PENDING can transition to CONFIRMED
            if ($booking['status'] !== self::STATUS_PENDING) {
                throw new ValidationException(
                    "Cannot confirm booking with status '{$booking['status']}'. Only PENDING bookings can be confirmed.",
                    'INVALID_STATE_TRANSITION',
                    422
                );
            }

            // Update booking status and confirmed time window
            $stmtUpdate = $this->pdo->prepare('
                UPDATE `bookings` 
                SET `status` = ?, 
                    `confirmed_starts_at_utc` = `proposed_starts_at_utc`, 
                    `confirmed_ends_at_utc` = `proposed_ends_at_utc`, 
                    `updated_at` = UTC_TIMESTAMP() 
                WHERE id = ?
            ');
            $stmtUpdate->execute([self::STATUS_CONFIRMED, $bookingId]);

            // Record status history with meeting link metadata if provided
            $metadataJson = !empty($meetingLink) ? json_encode([
                'meeting_link' => trim($meetingLink),
                'confirmed_by_user_id' => $currentUser->id,
            ], JSON_UNESCAPED_SLASHES) : null;

            $stmtHistory = $this->pdo->prepare('
                INSERT INTO `booking_status_history` (
                    `booking_id`, `changed_by_user_id`, `old_status`, `new_status`, `reason`, `metadata_json`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
            ');
            $stmtHistory->execute([
                $bookingId,
                $currentUser->id,
                self::STATUS_PENDING,
                self::STATUS_CONFIRMED,
                $reason ?? 'Booking request accepted and confirmed by tutor',
                $metadataJson,
            ]);

            $this->pdo->commit();

            // Audit
            $this->audit->log(
                action: 'BOOKING_CONFIRMED',
                entityType: 'booking',
                entityId: $bookingId,
                actorUserId: $currentUser->id,
                metadata: [
                    'old_status' => self::STATUS_PENDING,
                    'new_status' => self::STATUS_CONFIRMED,
                    'reason' => $reason,
                    'has_meeting_link' => !empty($meetingLink),
                ]
            );

            $this->logger->info("Booking confirmed: ID {$bookingId} by User ID {$currentUser->id}");

            $bookingDetails = $this->getBooking($bookingId, $currentUser);

            // Transactional Email Notification (Dispatched strictly after commit)
            try {
                $slotDisplay = Timezone::utcToLondonDisplay($bookingDetails['slot_starts_at']) . ' - ' . Timezone::utcToLondonDisplay($bookingDetails['slot_ends_at']);
                $this->emailService->send(
                    toEmail: $bookingDetails['student_email'],
                    toName: $bookingDetails['student_name'],
                    subject: 'Lesson Confirmed with ' . $bookingDetails['tutor_name'],
                    templateName: 'booking_confirmed',
                    templateData: [
                        'recipient_name' => $bookingDetails['student_name'],
                        'tutor_name' => $bookingDetails['tutor_name'],
                        'child_name' => $bookingDetails['child_name'],
                        'slot_time' => $slotDisplay,
                        'meeting_link' => $meetingLink ?? ($bookingDetails['meeting_link'] ?? null),
                        'booking_id' => $bookingId,
                    ]
                );
            } catch (Throwable $mailEx) {
                $this->logger->error('Failed to dispatch booking confirmation email: ' . $mailEx->getMessage());
            }

            return $bookingDetails;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Booking confirmation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reject a PENDING booking request.
     * Transition: PENDING -> REJECTED
     * Releases availability slot back to PUBLISHED if slot is still in future.
     * Actor: Assigned Tutor or Manager.
     *
     * @param int $bookingId
     * @param UserContext|null $currentUser
     * @param string|null $reason
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function rejectBooking(int $bookingId, ?UserContext $currentUser, ?string $reason = null): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        $this->pdo->beginTransaction();
        try {
            // Lock booking row
            $stmt = $this->pdo->prepare('SELECT * FROM `bookings` WHERE id = ? FOR UPDATE');
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                throw new ValidationException('Booking record not found.', 'BOOKING_NOT_FOUND', 404);
            }

            // Actor verification: Only assigned tutor or manager can reject
            if (!$currentUser->isManager()) {
                if ((int) $booking['tutor_user_id'] !== $currentUser->id) {
                    throw new ForbiddenException(
                        'You do not have permission to reject this booking.',
                        'UNAUTHORIZED_RESOURCE_OWNERSHIP'
                    );
                }
            }

            // State validation: only PENDING can transition to REJECTED
            if ($booking['status'] !== self::STATUS_PENDING) {
                throw new ValidationException(
                    "Cannot reject booking with status '{$booking['status']}'. Only PENDING bookings can be rejected.",
                    'INVALID_STATE_TRANSITION',
                    422
                );
            }

            // Update booking status
            $stmtUpdate = $this->pdo->prepare('
                UPDATE `bookings` 
                SET `status` = ?, `updated_at` = UTC_TIMESTAMP() 
                WHERE id = ?
            ');
            $stmtUpdate->execute([self::STATUS_REJECTED, $bookingId]);

            // Release availability slot back to PUBLISHED if in future
            if (!empty($booking['slot_id'])) {
                $stmtSlot = $this->pdo->prepare('
                    UPDATE `availability_slots` 
                    SET `status` = ?, `updated_at` = UTC_TIMESTAMP() 
                    WHERE `id` = ? AND `starts_at_utc` > UTC_TIMESTAMP()
                ');
                $stmtSlot->execute([AvailabilityService::STATUS_PUBLISHED, $booking['slot_id']]);
            }

            // Record status history
            $stmtHistory = $this->pdo->prepare('
                INSERT INTO `booking_status_history` (
                    `booking_id`, `changed_by_user_id`, `old_status`, `new_status`, `reason`, `metadata_json`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, NULL, UTC_TIMESTAMP())
            ');
            $stmtHistory->execute([
                $bookingId,
                $currentUser->id,
                self::STATUS_PENDING,
                self::STATUS_REJECTED,
                $reason ?? 'Booking request rejected by tutor',
            ]);

            $this->pdo->commit();

            // Audit
            $this->audit->log(
                action: 'BOOKING_REJECTED',
                entityType: 'booking',
                entityId: $bookingId,
                actorUserId: $currentUser->id,
                metadata: [
                    'old_status' => self::STATUS_PENDING,
                    'new_status' => self::STATUS_REJECTED,
                    'reason' => $reason,
                ]
            );

            $this->logger->info("Booking rejected: ID {$bookingId} by User ID {$currentUser->id}");

            $bookingDetails = $this->getBooking($bookingId, $currentUser);

            // Transactional Email Notification (Dispatched strictly after commit)
            try {
                $slotDisplay = Timezone::utcToLondonDisplay($bookingDetails['slot_starts_at']) . ' - ' . Timezone::utcToLondonDisplay($bookingDetails['slot_ends_at']);
                $this->emailService->send(
                    toEmail: $bookingDetails['student_email'],
                    toName: $bookingDetails['student_name'],
                    subject: 'Lesson Request Declined: ' . $bookingDetails['tutor_name'],
                    templateName: 'booking_rejected',
                    templateData: [
                        'recipient_name' => $bookingDetails['student_name'],
                        'tutor_name' => $bookingDetails['tutor_name'],
                        'slot_time' => $slotDisplay,
                        'reason' => $reason,
                        'booking_id' => $bookingId,
                    ]
                );
            } catch (Throwable $mailEx) {
                $this->logger->error('Failed to dispatch booking rejection email: ' . $mailEx->getMessage());
            }

            return $bookingDetails;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Booking rejection error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel a PENDING booking request by the owning student/parent or manager.
     * Transition: PENDING -> CANCELLED
     * Releases availability slot back to PUBLISHED if in future.
     *
     * @param int $bookingId
     * @param UserContext|null $currentUser
     * @param string|null $reason
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function cancelBooking(int $bookingId, ?UserContext $currentUser, ?string $reason = null): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM `bookings` WHERE id = ? FOR UPDATE');
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                throw new ValidationException('Booking record not found.', 'BOOKING_NOT_FOUND', 404);
            }

            // Actor verification: Only owning student/parent or manager can cancel
            if (!$currentUser->isManager()) {
                if ((int) $booking['student_user_id'] !== $currentUser->id) {
                    throw new ForbiddenException(
                        'You do not have permission to cancel this booking.',
                        'UNAUTHORIZED_RESOURCE_OWNERSHIP'
                    );
                }
            }

            // State validation: can cancel PENDING (or CONFIRMED)
            if (!in_array($booking['status'], [self::STATUS_PENDING, self::STATUS_CONFIRMED], true)) {
                throw new ValidationException(
                    "Cannot cancel booking with status '{$booking['status']}'.",
                    'INVALID_STATE_TRANSITION',
                    422
                );
            }

            $oldStatus = $booking['status'];

            $stmtUpdate = $this->pdo->prepare('
                UPDATE `bookings` 
                SET `status` = ?, `updated_at` = UTC_TIMESTAMP() 
                WHERE id = ?
            ');
            $stmtUpdate->execute([self::STATUS_CANCELLED, $bookingId]);

            // Release availability slot back to PUBLISHED if in future
            if (!empty($booking['slot_id'])) {
                $stmtSlot = $this->pdo->prepare('
                    UPDATE `availability_slots` 
                    SET `status` = ?, `updated_at` = UTC_TIMESTAMP() 
                    WHERE `id` = ? AND `starts_at_utc` > UTC_TIMESTAMP()
                ');
                $stmtSlot->execute([AvailabilityService::STATUS_PUBLISHED, $booking['slot_id']]);
            }

            // Record status history
            $stmtHistory = $this->pdo->prepare('
                INSERT INTO `booking_status_history` (
                    `booking_id`, `changed_by_user_id`, `old_status`, `new_status`, `reason`, `metadata_json`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, NULL, UTC_TIMESTAMP())
            ');
            $stmtHistory->execute([
                $bookingId,
                $currentUser->id,
                $oldStatus,
                self::STATUS_CANCELLED,
                $reason ?? 'Booking request cancelled by user',
            ]);

            $this->pdo->commit();

            $this->audit->log(
                action: 'BOOKING_CANCELLED',
                entityType: 'booking',
                entityId: $bookingId,
                actorUserId: $currentUser->id,
                metadata: [
                    'old_status' => $oldStatus,
                    'new_status' => self::STATUS_CANCELLED,
                    'reason' => $reason,
                ]
            );

            $bookingDetails = $this->getBooking($bookingId, $currentUser);

            // Transactional Email Notification (Dispatched strictly after commit)
            try {
                $slotDisplay = Timezone::utcToLondonDisplay($bookingDetails['slot_starts_at']) . ' - ' . Timezone::utcToLondonDisplay($bookingDetails['slot_ends_at']);
                $isStudent = ($currentUser->id === (int) $booking['student_user_id']);
                $recipientEmail = $isStudent ? $bookingDetails['tutor_email'] : $bookingDetails['student_email'];
                $recipientName = $isStudent ? $bookingDetails['tutor_name'] : $bookingDetails['student_name'];
                $cancellerName = $currentUser->displayName ?? 'User';

                if (!empty($recipientEmail)) {
                    $this->emailService->send(
                        toEmail: $recipientEmail,
                        toName: $recipientName,
                        subject: 'Lesson Cancelled: ' . $slotDisplay,
                        templateName: 'booking_cancelled',
                        templateData: [
                            'recipient_name' => $recipientName,
                            'cancelled_by' => $cancellerName,
                            'slot_time' => $slotDisplay,
                            'reason' => $reason,
                            'booking_id' => $bookingId,
                        ]
                    );
                }
            } catch (Throwable $mailEx) {
                $this->logger->error('Failed to dispatch booking cancellation email: ' . $mailEx->getMessage());
            }

            return $bookingDetails;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('Booking cancellation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve available published slots for an eligible tutor.
     * Used by the booking workflow UI.
     *
     * @param int $tutorUserId
     * @return array
     */
    public function getAvailableSlotsForTutor(int $tutorUserId): array
    {
        if (!$this->tutorService->isBookable($tutorUserId)) {
            return [];
        }

        $stmt = $this->pdo->prepare('
            SELECT id, tutor_user_id, starts_at_utc, ends_at_utc, status, max_students 
            FROM `availability_slots` 
            WHERE tutor_user_id = ? 
              AND status = ? 
              AND starts_at_utc > UTC_TIMESTAMP() 
            ORDER BY starts_at_utc ASC
        ');
        $stmt->execute([$tutorUserId, AvailabilityService::STATUS_PUBLISHED]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'tutor_user_id' => (int) $row['tutor_user_id'],
                'starts_at_utc' => $row['starts_at_utc'],
                'ends_at_utc' => $row['ends_at_utc'],
                'starts_at_london' => Timezone::utcToLondon($row['starts_at_utc']),
                'ends_at_london' => Timezone::utcToLondon($row['ends_at_utc']),
                'status' => $row['status'],
                'max_students' => (int) ($row['max_students'] ?? 1),
                'is_group' => ((int) ($row['max_students'] ?? 1)) > 1,
            ];
        }, $rows);
    }

    /**
     * Automatically cancel bookings that have remained in PENDING status without response for 24+ hours.
     * Transitions: PENDING -> SYSTEM_CANCELLED.
     * Concurrency-safe batch execution with row-level locks and slot availability recovery.
     *
     * @param int $hours Default 24 hours
     * @return int Number of stale bookings cancelled
     */
    public function autoCancelStaleBookings(int $hours = 24): int
    {
        $stmt = $this->pdo->prepare("
            SELECT id, slot_id 
            FROM `bookings` 
            WHERE `status` = 'PENDING' 
              AND `created_at` <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$hours]);
        $staleBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cancelledCount = 0;

        foreach ($staleBookings as $b) {
            $bookingId = (int) $b['id'];
            $slotId = $b['slot_id'] ? (int) $b['slot_id'] : null;

            try {
                $this->pdo->beginTransaction();

                $stmtLock = $this->pdo->prepare("SELECT id, status, student_user_id, tutor_user_id FROM `bookings` WHERE id = ? AND status = 'PENDING' FOR UPDATE");
                $stmtLock->execute([$bookingId]);
                $booking = $stmtLock->fetch(PDO::FETCH_ASSOC);

                if (!$booking) {
                    $this->pdo->rollBack();
                    continue;
                }

                $stmtUpdate = $this->pdo->prepare("
                    UPDATE `bookings` 
                    SET `status` = ?, `updated_at` = UTC_TIMESTAMP() 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([self::STATUS_SYSTEM_CANCELLED, $bookingId]);

                $stmtHistory = $this->pdo->prepare('
                    INSERT INTO `booking_status_history` (
                        `booking_id`, `changed_by_user_id`, `old_status`, `new_status`, `reason`, `metadata_json`, `created_at`
                    ) VALUES (?, NULL, ?, ?, ?, NULL, UTC_TIMESTAMP())
                ');
                $stmtHistory->execute([
                    $bookingId,
                    self::STATUS_PENDING,
                    self::STATUS_SYSTEM_CANCELLED,
                    "Automatic {$hours}-hour no-response cancellation",
                ]);

                // Restore slot to PUBLISHED if capacity available and in future
                if ($slotId !== null) {
                    $stmtSlotCap = $this->pdo->prepare('SELECT max_students FROM `availability_slots` WHERE id = ?');
                    $stmtSlotCap->execute([$slotId]);
                    $slotCap = (int) ($stmtSlotCap->fetchColumn() ?: 1);

                    $stmtActive = $this->pdo->prepare("
                        SELECT COUNT(*) FROM `bookings` 
                        WHERE `slot_id` = ? AND id != ? AND `status` IN ('PENDING', 'CONFIRMED')
                    ");
                    $stmtActive->execute([$slotId, $bookingId]);
                    $remainingActive = (int) $stmtActive->fetchColumn();

                    if ($remainingActive < $slotCap) {
                        $stmtSlot = $this->pdo->prepare('
                            UPDATE `availability_slots` 
                            SET `status` = ?, `updated_at` = UTC_TIMESTAMP() 
                            WHERE `id` = ? AND `starts_at_utc` > UTC_TIMESTAMP()
                        ');
                        $stmtSlot->execute([AvailabilityService::STATUS_PUBLISHED, $slotId]);
                    }
                }

                $this->pdo->commit();
                $cancelledCount++;

                $this->audit->log(
                    action: 'BOOKING_SYSTEM_CANCELLED',
                    entityType: 'booking',
                    entityId: $bookingId,
                    actorUserId: null,
                    metadata: [
                        'old_status' => self::STATUS_PENDING,
                        'new_status' => self::STATUS_SYSTEM_CANCELLED,
                        'reason' => "Automatic {$hours}-hour no-response cancellation",
                    ]
                );

                $this->logger->info("Auto-cancelled stale booking ID {$bookingId} (awaiting response > {$hours} hours)");
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $this->logger->error("Failed to auto-cancel booking ID {$bookingId}: " . $e->getMessage());
            }
        }

        return $cancelledCount;
    }
}
