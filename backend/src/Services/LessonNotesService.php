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

class LessonNotesService
{
    public const VISIBILITY_INTERNAL = 'INTERNAL';
    public const VISIBILITY_PARENT_VISIBLE = 'PARENT_VISIBLE';

    public const ALLOWED_VISIBILITIES = [
        self::VISIBILITY_INTERNAL,
        self::VISIBILITY_PARENT_VISIBLE,
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
     * Create a lesson note for a specific booked session.
     * Only assigned tutor or manager can author lesson notes.
     *
     * @param int $bookingId
     * @param string $notes
     * @param string $visibility 'PARENT_VISIBLE' or 'INTERNAL'
     * @param UserContext|null $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function createNote(
        int $bookingId,
        string $notes,
        string $visibility = self::VISIBILITY_PARENT_VISIBLE,
        ?UserContext $currentUser = null
    ): array {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        if ($bookingId <= 0) {
            throw new ValidationException('Valid booking ID is required.', 'VALIDATION_ERROR', 422);
        }

        $cleanNotes = trim($notes);
        if (empty($cleanNotes)) {
            throw new ValidationException('Lesson notes content cannot be empty.', 'VALIDATION_ERROR', 422);
        }

        if (mb_strlen($cleanNotes) > 5000) {
            throw new ValidationException('Lesson notes cannot exceed 5000 characters.', 'VALIDATION_ERROR', 422);
        }

        $upperVisibility = strtoupper(trim($visibility));
        if (!in_array($upperVisibility, self::ALLOWED_VISIBILITIES, true)) {
            throw new ValidationException("Invalid visibility '{$visibility}'. Allowed: INTERNAL, PARENT_VISIBLE", 'VALIDATION_ERROR', 422);
        }

        // Verify booking existence and tutor ownership
        $stmtBooking = $this->pdo->prepare('
            SELECT id, tutor_user_id, student_user_id, child_id, status 
            FROM `bookings` 
            WHERE id = ? 
            LIMIT 1
        ');
        $stmtBooking->execute([$bookingId]);
        $booking = $stmtBooking->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new ValidationException('Booking record not found.', 'BOOKING_NOT_FOUND', 404);
        }

        // Actor check: Only assigned tutor or manager
        if (!$currentUser->isManager()) {
            if ((int) $booking['tutor_user_id'] !== $currentUser->id) {
                throw new ForbiddenException(
                    'You do not have permission to author lesson notes for this booking.',
                    'UNAUTHORIZED_RESOURCE_OWNERSHIP'
                );
            }
        }

        $tutorUserId = (int) $booking['tutor_user_id'];

        $stmt = $this->pdo->prepare('
            INSERT INTO `lesson_notes` (`booking_id`, `tutor_user_id`, `notes`, `visibility`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ');
        $stmt->execute([$bookingId, $tutorUserId, $cleanNotes, $upperVisibility]);
        $noteId = (int) $this->pdo->lastInsertId();

        $this->audit->log(
            action: 'LESSON_NOTE_CREATED',
            entityType: 'lesson_note',
            entityId: $noteId,
            actorUserId: $currentUser->id,
            metadata: [
                'booking_id' => $bookingId,
                'tutor_user_id' => $tutorUserId,
                'visibility' => $upperVisibility,
            ]
        );

        $this->logger->info("Lesson note created: Note ID {$noteId} for Booking ID {$bookingId} by User ID {$currentUser->id}");

        return $this->getNoteById($noteId, $currentUser);
    }

    /**
     * Get notes for a booking, enforcing strict IDOR/BOLA role visibility:
     * - Tutor of booking & Manager: Can see all notes (INTERNAL and PARENT_VISIBLE)
     * - Student/Parent of booking: Can ONLY see notes where visibility = 'PARENT_VISIBLE'
     * - Other users: 403 Forbidden.
     *
     * @param int $bookingId
     * @param UserContext|null $currentUser
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getNotesForBooking(int $bookingId, ?UserContext $currentUser = null): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        $stmtBooking = $this->pdo->prepare('
            SELECT id, tutor_user_id, student_user_id, child_id 
            FROM `bookings` 
            WHERE id = ? 
            LIMIT 1
        ');
        $stmtBooking->execute([$bookingId]);
        $booking = $stmtBooking->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new ValidationException('Booking record not found.', 'BOOKING_NOT_FOUND', 404);
        }

        $isManager = $currentUser->isManager();
        $isTutor = ((int) $booking['tutor_user_id'] === $currentUser->id);
        $isStudentParent = ((int) $booking['student_user_id'] === $currentUser->id);

        if (!$isManager && !$isTutor && !$isStudentParent) {
            throw new ForbiddenException(
                'You do not have authorization to view lesson notes for this session.',
                'FORBIDDEN'
            );
        }

        if ($isManager || $isTutor) {
            $stmt = $this->pdo->prepare('
                SELECT ln.*, u.display_name AS tutor_name
                FROM `lesson_notes` ln
                JOIN `users` u ON ln.tutor_user_id = u.id
                WHERE ln.booking_id = ?
                ORDER BY ln.created_at ASC
            ');
            $stmt->execute([$bookingId]);
        } else {
            // Student/Parent: strictly PARENT_VISIBLE only
            $stmt = $this->pdo->prepare("
                SELECT ln.*, u.display_name AS tutor_name
                FROM `lesson_notes` ln
                JOIN `users` u ON ln.tutor_user_id = u.id
                WHERE ln.booking_id = ? AND ln.visibility = 'PARENT_VISIBLE'
                ORDER BY ln.created_at ASC
            ");
            $stmt->execute([$bookingId]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'booking_id' => (int) $row['booking_id'],
                'tutor_user_id' => (int) $row['tutor_user_id'],
                'tutor_name' => (string) ($row['tutor_name'] ?? 'Tutor'),
                'notes' => (string) $row['notes'],
                'content' => (string) $row['notes'],
                'visibility' => (string) $row['visibility'],
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }, $rows);
    }

    /**
     * Get a specific note by ID with authorization check.
     */
    public function getNoteById(int $noteId, ?UserContext $currentUser = null): array
    {
        Authorization::requireAuthenticatedUser($currentUser);
        Authorization::requireActiveStatus($currentUser);

        $stmt = $this->pdo->prepare('
            SELECT ln.*, b.student_user_id, u.display_name AS tutor_name
            FROM `lesson_notes` ln
            JOIN `bookings` b ON ln.booking_id = b.id
            JOIN `users` u ON ln.tutor_user_id = u.id
            WHERE ln.id = ?
            LIMIT 1
        ');
        $stmt->execute([$noteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidationException('Lesson note not found.', 'NOT_FOUND', 404);
        }

        $isManager = $currentUser->isManager();
        $isTutor = ((int) $row['tutor_user_id'] === $currentUser->id);
        $isStudentParent = ((int) $row['student_user_id'] === $currentUser->id);

        if (!$isManager && !$isTutor && !$isStudentParent) {
            throw new ForbiddenException('You do not have permission to view this lesson note.', 'FORBIDDEN');
        }

        if ($isStudentParent && $row['visibility'] !== self::VISIBILITY_PARENT_VISIBLE) {
            throw new ForbiddenException('This lesson note is internal to the tutor and manager.', 'FORBIDDEN');
        }

        return [
            'id' => (int) $row['id'],
            'booking_id' => (int) $row['booking_id'],
            'tutor_user_id' => (int) $row['tutor_user_id'],
            'tutor_name' => (string) ($row['tutor_name'] ?? 'Tutor'),
            'notes' => (string) $row['notes'],
            'content' => (string) $row['notes'],
            'visibility' => (string) $row['visibility'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}
