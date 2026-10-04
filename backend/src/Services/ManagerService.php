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

class ManagerService
{
    private PDO $pdo;
    private Logger $logger;
    private AuditService $audit;
    private TutorService $tutorService;
    private BookingService $bookingService;
    private StudentParentService $studentParentService;
    private DbsService $dbsService;
    private AvailabilityService $availabilityService;
    private BlogService $blogService;
    private NewsletterService $newsletterService;

    public function __construct(
        ?PDO $pdo = null,
        ?Logger $logger = null,
        ?AuditService $audit = null,
        ?TutorService $tutorService = null,
        ?BookingService $bookingService = null,
        ?StudentParentService $studentParentService = null,
        ?DbsService $dbsService = null,
        ?AvailabilityService $availabilityService = null,
        ?BlogService $blogService = null,
        ?NewsletterService $newsletterService = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->logger = $logger ?? new Logger();
        $this->audit = $audit ?? new AuditService($this->pdo, $this->logger);
        $this->tutorService = $tutorService ?? new TutorService($this->pdo, $this->logger, $this->audit);
        $this->bookingService = $bookingService ?? new BookingService($this->pdo, $this->logger, $this->audit, $this->tutorService);
        $this->studentParentService = $studentParentService ?? new StudentParentService($this->pdo, $this->logger, $this->audit);
        $this->dbsService = $dbsService ?? new DbsService($this->pdo, $this->logger, $this->audit);
        $this->availabilityService = $availabilityService ?? new AvailabilityService($this->pdo, $this->logger, $this->audit, $this->tutorService);
        $this->blogService = $blogService ?? new BlogService($this->pdo, $this->logger, $this->audit);
        $this->newsletterService = $newsletterService ?? new NewsletterService($this->pdo, $this->logger, $this->audit);
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
     * Validate pagination and sorting parameters against a field whitelist.
     *
     * @param int $page
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortDir
     * @param array $allowedSortFields
     * @throws ValidationException
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
     * Retrieve local DBS document metadata record if present.
     *
     * @param int $tutorUserId
     * @return array|null
     */
    private function getDbsMetadata(int $tutorUserId): ?array
    {
        $metaPath = dirname(__DIR__, 2) . "/storage/dbs/meta_{$tutorUserId}.json";
        if (!file_exists($metaPath)) {
            return null;
        }
        $raw = file_get_contents($metaPath);
        if (!$raw) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * 1. Retrieve factual platform KPIs and summary metrics for manager dashboard.
     *
     * @param UserContext|null $manager
     * @return array
     * @throws ForbiddenException
     */
    public function getDashboardKpis(?UserContext $manager): array
    {
        $this->requireManager($manager);

        // A. Tutors Summary
        $stmtTutors = $this->pdo->query('
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN tp.approval_status = "PENDING" THEN 1 ELSE 0 END) AS pending_approvals,
                SUM(CASE WHEN tp.approval_status = "APPROVED" THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN tp.approval_status = "SUSPENDED" THEN 1 ELSE 0 END) AS suspended,
                SUM(CASE WHEN tp.approval_status = "REJECTED" THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN u.status = "ACTIVE" AND tp.approval_status = "APPROVED" AND tp.dbs_status = "VERIFIED" THEN 1 ELSE 0 END) AS bookable_active
            FROM `tutor_profiles` tp
            JOIN `users` u ON tp.user_id = u.id
        ');
        $tutorStats = $stmtTutors->fetch(PDO::FETCH_ASSOC) ?: [];

        // B. DBS Safeguarding Summary
        $stmtDbs = $this->pdo->query('
            SELECT 
                SUM(CASE WHEN dbs_status = "NOT_SUBMITTED" THEN 1 ELSE 0 END) AS not_submitted,
                SUM(CASE WHEN dbs_status = "SUBMITTED" THEN 1 ELSE 0 END) AS submitted,
                SUM(CASE WHEN dbs_status = "VERIFIED" THEN 1 ELSE 0 END) AS verified,
                SUM(CASE WHEN dbs_status = "REJECTED" THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN dbs_status = "EXPIRED" THEN 1 ELSE 0 END) AS expired
            FROM `tutor_profiles`
        ');
        $dbsStats = $stmtDbs->fetch(PDO::FETCH_ASSOC) ?: [];

        // C. Students / Parents & Children Summary
        $stmtStudents = $this->pdo->query('
            SELECT COUNT(*) AS total_students_parents
            FROM `users`
            WHERE `role` = "STUDENT_PARENT"
        ');
        $studentTotal = (int) ($stmtStudents->fetchColumn() ?: 0);

        $stmtChildren = $this->pdo->query('
            SELECT COUNT(*) AS total_children
            FROM `children`
            WHERE `active` = 1
        ');
        $childrenTotal = (int) ($stmtChildren->fetchColumn() ?: 0);

        // D. Bookings Summary
        $stmtBookings = $this->pdo->query('
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = "PENDING" THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = "CONFIRMED" THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN status = "REJECTED" THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status = "CANCELLED" THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN status = "SYSTEM_CANCELLED" THEN 1 ELSE 0 END) AS system_cancelled,
                SUM(CASE WHEN status = "COMPLETED" THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = "CONFIRMED" AND confirmed_starts_at_utc >= UTC_TIMESTAMP() THEN 1 ELSE 0 END) AS upcoming
            FROM `bookings`
        ');
        $bookingStats = $stmtBookings->fetch(PDO::FETCH_ASSOC) ?: [];

        // E. Availability Slots Summary
        $stmtSlots = $this->pdo->query('
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = "PUBLISHED" THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = "BOOKED" THEN 1 ELSE 0 END) AS booked,
                SUM(CASE WHEN status = "BLOCKED" THEN 1 ELSE 0 END) AS blocked
            FROM `availability_slots`
        ');
        $slotStats = $stmtSlots->fetch(PDO::FETCH_ASSOC) ?: [];

        // F. Recent Audit Activity (Last 5 events)
        $stmtRecent = $this->pdo->query('
            SELECT 
                a.id, a.action, a.entity_type, a.entity_id, a.created_at,
                u.display_name AS actor_name, u.role AS actor_role
            FROM `audit_logs` a
            LEFT JOIN `users` u ON a.actor_user_id = u.id
            ORDER BY a.id DESC
            LIMIT 5
        ');
        $recentAudit = $stmtRecent->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'tutors' => [
                'total' => (int) ($tutorStats['total'] ?? 0),
                'pending_approvals' => (int) ($tutorStats['pending_approvals'] ?? 0),
                'approved' => (int) ($tutorStats['approved'] ?? 0),
                'suspended' => (int) ($tutorStats['suspended'] ?? 0),
                'rejected' => (int) ($tutorStats['rejected'] ?? 0),
                'bookable_active' => (int) ($tutorStats['bookable_active'] ?? 0),
            ],
            'dbs' => [
                'not_submitted' => (int) ($dbsStats['not_submitted'] ?? 0),
                'submitted' => (int) ($dbsStats['submitted'] ?? 0),
                'verified' => (int) ($dbsStats['verified'] ?? 0),
                'rejected' => (int) ($dbsStats['rejected'] ?? 0),
                'expired' => (int) ($dbsStats['expired'] ?? 0),
            ],
            'students_parents' => [
                'total' => $studentTotal,
                'total_children' => $childrenTotal,
            ],
            'bookings' => [
                'total' => (int) ($bookingStats['total'] ?? 0),
                'pending' => (int) ($bookingStats['pending'] ?? 0),
                'confirmed' => (int) ($bookingStats['confirmed'] ?? 0),
                'rejected' => (int) ($bookingStats['rejected'] ?? 0),
                'cancelled' => (int) ($bookingStats['cancelled'] ?? 0),
                'system_cancelled' => (int) ($bookingStats['system_cancelled'] ?? 0),
                'completed' => (int) ($bookingStats['completed'] ?? 0),
                'upcoming' => (int) ($bookingStats['upcoming'] ?? 0),
            ],
            'availability' => [
                'total' => (int) ($slotStats['total'] ?? 0),
                'published' => (int) ($slotStats['published'] ?? 0),
                'booked' => (int) ($slotStats['booked'] ?? 0),
                'blocked' => (int) ($slotStats['blocked'] ?? 0),
            ],
            'blog' => $this->blogService->getBlogStats($manager),
            'newsletter' => $this->newsletterService->getSubscriberStats($manager),
            'recent_activity' => $recentAudit,
            'timestamp_utc' => Timezone::nowUtc(),
            'timestamp_london' => Timezone::utcToLondon(Timezone::nowUtc()),
        ];
    }

    /**
     * 2. List tutors with filtering, search, pagination, and sorting for manager administration.
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
    public function listTutors(
        ?UserContext $manager,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $this->requireManager($manager);

        $allowedSortFields = ['id', 'display_name', 'email', 'approval_status', 'dbs_status', 'status', 'created_at'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $where = ['1=1'];
        $params = [];

        // Filter: approval_status
        if (!empty($filters['approval_status'])) {
            $where[] = 'tp.approval_status = ?';
            $params[] = strtoupper((string) $filters['approval_status']);
        }

        // Filter: dbs_status
        if (!empty($filters['dbs_status'])) {
            $where[] = 'tp.dbs_status = ?';
            $params[] = strtoupper((string) $filters['dbs_status']);
        }

        // Filter: account status
        if (!empty($filters['status'])) {
            $where[] = 'u.status = ?';
            $params[] = strtoupper((string) $filters['status']);
        }

        // Search: name or email
        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = '(u.display_name LIKE ? OR u.email LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        // Count total matching
        $countSql = "SELECT COUNT(*) FROM `tutor_profiles` tp JOIN `users` u ON tp.user_id = u.id WHERE {$whereClause}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Query items
        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        // Map sort field to table column
        $sortColumn = match ($sortBy) {
            'display_name' => 'u.display_name',
            'email' => 'u.email',
            'status' => 'u.status',
            'approval_status' => 'tp.approval_status',
            'dbs_status' => 'tp.dbs_status',
            'id' => 'u.id',
            default => 'tp.created_at',
        };

        $sql = "
            SELECT 
                u.id, u.firebase_uid, u.email, u.display_name, u.role, u.status, u.email_verified_at,
                tp.headline, tp.approval_status, tp.dbs_status,
                tp.approved_at, tp.approved_by, tp.created_at, tp.updated_at,
                (SELECT COUNT(*) FROM `availability_slots` WHERE tutor_user_id = u.id) AS total_slots,
                (SELECT COUNT(*) FROM `bookings` WHERE tutor_user_id = u.id) AS total_bookings
            FROM `tutor_profiles` tp
            JOIN `users` u ON tp.user_id = u.id
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, u.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $execParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            $isBookable = ($row['status'] === 'ACTIVE'
                && $row['approval_status'] === TutorService::APPROVAL_APPROVED
                && $row['dbs_status'] === TutorService::DBS_VERIFIED);

            $dbsMeta = $this->getDbsMetadata((int) $row['id']);

            return [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['id'],
                'email' => $row['email'],
                'display_name' => $row['display_name'],
                'role' => $row['role'],
                'status' => $row['status'],
                'approval_status' => $row['approval_status'],
                'dbs_status' => $row['dbs_status'],
                'dbs_certificate_number' => $dbsMeta['certificate_number'] ?? null,
                'headline' => $row['headline'],
                'hourly_rate' => null,
                'is_bookable' => $isBookable,
                'total_slots' => (int) $row['total_slots'],
                'total_bookings' => (int) $row['total_bookings'],
                'approved_at' => $row['approved_at'],
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
     * Retrieve detailed tutor profile for managerial inspection.
     *
     * @param int $tutorUserId
     * @param UserContext|null $manager
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getTutorDetails(int $tutorUserId, ?UserContext $manager): array
    {
        $this->requireManager($manager);

        if ($tutorUserId <= 0) {
            throw new ValidationException('Valid tutor user ID is required.', 'INVALID_ID', 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT 
                u.id, u.firebase_uid, u.email, u.display_name, u.role, u.status, u.email_verified_at,
                tp.headline, tp.bio, tp.subjects_json, tp.curriculum_json, tp.qualifications,
                tp.approval_status, tp.dbs_status,
                tp.approved_at, tp.approved_by, tp.created_at, tp.updated_at
            FROM `tutor_profiles` tp
            JOIN `users` u ON tp.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ');
        $stmt->execute([$tutorUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidationException('Tutor not found.', 'TUTOR_NOT_FOUND', 404);
        }

        $isBookable = ($row['status'] === 'ACTIVE'
            && $row['approval_status'] === TutorService::APPROVAL_APPROVED
            && $row['dbs_status'] === TutorService::DBS_VERIFIED);

        $dbsMeta = $this->getDbsMetadata($tutorUserId);

        // Fetch recent bookings for this tutor
        $stmtBookings = $this->pdo->prepare('
            SELECT b.id, b.status, b.created_at, b.proposed_starts_at_utc, u.display_name AS student_name
            FROM `bookings` b
            JOIN `users` u ON b.student_user_id = u.id
            WHERE b.tutor_user_id = ?
            ORDER BY b.id DESC
            LIMIT 5
        ');
        $stmtBookings->execute([$tutorUserId]);
        $recentBookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

        // Fetch audit actions concerning this tutor
        $stmtAudit = $this->pdo->prepare('
            SELECT a.id, a.action, a.created_at, u.display_name AS actor_name
            FROM `audit_logs` a
            LEFT JOIN `users` u ON a.actor_user_id = u.id
            WHERE (a.entity_type = "tutor_profile" AND a.entity_id = ?) 
               OR (a.entity_type = "user" AND a.entity_id = ?)
            ORDER BY a.id DESC
            LIMIT 5
        ');
        $stmtAudit->execute([$tutorUserId, $tutorUserId]);
        $recentAudit = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['id'],
            'firebase_uid' => $row['firebase_uid'],
            'email' => $row['email'],
            'display_name' => $row['display_name'],
            'role' => $row['role'],
            'status' => $row['status'],
            'approval_status' => $row['approval_status'],
            'dbs_status' => $row['dbs_status'],
            'dbs_certificate_number' => $dbsMeta['certificate_number'] ?? null,
            'headline' => $row['headline'],
            'bio' => $row['bio'],
            'subjects' => !empty($row['subjects_json']) ? json_decode($row['subjects_json'], true) : [],
            'curriculum' => !empty($row['curriculum_json']) ? json_decode($row['curriculum_json'], true) : [],
            'qualifications' => $row['qualifications'],
            'hourly_rate' => null,
            'is_bookable' => $isBookable,
            'approved_at' => $row['approved_at'],
            'approved_by' => $row['approved_by'] ? (int) $row['approved_by'] : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'recent_bookings' => $recentBookings,
            'recent_audit' => $recentAudit,
        ];
    }

    /**
     * 3. List students/parents with associated child counts and booking metrics.
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
    public function listStudents(
        ?UserContext $manager,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $this->requireManager($manager);

        $allowedSortFields = ['id', 'display_name', 'email', 'status', 'created_at'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $where = ['u.role = "STUDENT_PARENT"'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'u.status = ?';
            $params[] = strtoupper((string) $filters['status']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = '(u.display_name LIKE ? OR u.email LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `users` u WHERE {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumn = match ($sortBy) {
            'display_name' => 'u.display_name',
            'email' => 'u.email',
            'status' => 'u.status',
            'id' => 'u.id',
            default => 'u.created_at',
        };

        $sql = "
            SELECT 
                u.id, u.firebase_uid, u.email, u.display_name, u.role, u.status, u.created_at,
                sp.phone, sp.postcode,
                (SELECT COUNT(*) FROM `children` WHERE parent_user_id = u.id AND active = 1) AS active_children,
                (SELECT COUNT(*) FROM `bookings` WHERE student_user_id = u.id) AS total_bookings
            FROM `users` u
            LEFT JOIN `student_profiles` sp ON u.id = sp.user_id
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, u.id DESC
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
                'display_name' => $row['display_name'],
                'role' => $row['role'],
                'status' => $row['status'],
                'phone' => $row['phone'],
                'postcode' => $row['postcode'],
                'active_children' => (int) $row['active_children'],
                'total_bookings' => (int) $row['total_bookings'],
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
     * Retrieve student/parent profile details and associated children records.
     *
     * @param int $studentUserId
     * @param UserContext|null $manager
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getStudentDetails(int $studentUserId, ?UserContext $manager): array
    {
        $this->requireManager($manager);

        if ($studentUserId <= 0) {
            throw new ValidationException('Valid student user ID is required.', 'INVALID_ID', 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT 
                u.id, u.firebase_uid, u.email, u.display_name, u.role, u.status, u.created_at,
                sp.phone, sp.postcode
            FROM `users` u
            LEFT JOIN `student_profiles` sp ON u.id = sp.user_id
            WHERE u.id = ? AND u.role = "STUDENT_PARENT"
            LIMIT 1
        ');
        $stmt->execute([$studentUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new ValidationException('Student/Parent not found.', 'STUDENT_NOT_FOUND', 404);
        }

        // Retrieve associated children
        $stmtChildren = $this->pdo->prepare('
            SELECT id, first_name, last_name, date_of_birth, school_year, curriculum, active, created_at
            FROM `children`
            WHERE parent_user_id = ? AND active = 1
            ORDER BY id ASC
        ');
        $stmtChildren->execute([$studentUserId]);
        $children = $stmtChildren->fetchAll(PDO::FETCH_ASSOC);

        // Retrieve bookings
        $stmtBookings = $this->pdo->prepare('
            SELECT b.id, b.status, b.proposed_starts_at_utc, b.confirmed_starts_at_utc, b.created_at,
                   tut.display_name AS tutor_name, ch.first_name AS child_name
            FROM `bookings` b
            JOIN `users` tut ON b.tutor_user_id = tut.id
            LEFT JOIN `children` ch ON b.child_id = ch.id
            WHERE b.student_user_id = ?
            ORDER BY b.id DESC
            LIMIT 10
        ');
        $stmtBookings->execute([$studentUserId]);
        $bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'status' => $user['status'],
            'phone' => $user['phone'],
            'postcode' => $user['postcode'],
            'created_at' => $user['created_at'],
            'children' => $children,
            'recent_bookings' => $bookings,
        ];
    }

    /**
     * 4. List children across platform with parent association for safeguarding visibility.
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
    public function listChildren(
        ?UserContext $manager,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $this->requireManager($manager);

        $allowedSortFields = ['id', 'first_name', 'school_year', 'created_at'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $where = ['c.active = 1'];
        $params = [];

        if (!empty($filters['school_year'])) {
            $where[] = 'c.school_year = ?';
            $params[] = (string) $filters['school_year'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = '(c.first_name LIKE ? OR c.last_name LIKE ? OR u.display_name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `children` c JOIN `users` u ON c.parent_user_id = u.id WHERE {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumn = match ($sortBy) {
            'first_name' => 'c.first_name',
            'school_year' => 'c.school_year',
            'id' => 'c.id',
            default => 'c.created_at',
        };

        $sql = "
            SELECT 
                c.id, c.parent_user_id, c.first_name, c.last_name, c.school_year, c.curriculum, c.active, c.created_at,
                u.display_name AS parent_name, u.email AS parent_email
            FROM `children` c
            JOIN `users` u ON c.parent_user_id = u.id
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, c.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $execParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'parent_user_id' => (int) $row['parent_user_id'],
                'child_name' => trim($row['first_name'] . ' ' . ($row['last_name'] ?? '')),
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'school_year' => $row['school_year'],
                'curriculum' => $row['curriculum'],
                'active' => (bool) $row['active'],
                'parent_name' => $row['parent_name'],
                'parent_email' => $row['parent_email'],
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
     * 5. List bookings across platform with comprehensive managerial filters.
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
    public function listBookings(
        ?UserContext $manager,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $this->requireManager($manager);

        $allowedSortFields = ['id', 'status', 'created_at', 'proposed_starts_at_utc'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'b.status = ?';
            $params[] = strtoupper((string) $filters['status']);
        }

        if (!empty($filters['tutor_id'])) {
            $where[] = 'b.tutor_user_id = ?';
            $params[] = (int) $filters['tutor_id'];
        }

        if (!empty($filters['student_id'])) {
            $where[] = 'b.student_user_id = ?';
            $params[] = (int) $filters['student_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = '(stu.display_name LIKE ? OR tut.display_name LIKE ? OR ch.first_name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(*) 
            FROM `bookings` b
            JOIN `users` stu ON b.student_user_id = stu.id
            JOIN `users` tut ON b.tutor_user_id = tut.id
            LEFT JOIN `children` ch ON b.child_id = ch.id
            WHERE {$whereClause}
        ";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumn = match ($sortBy) {
            'status' => 'b.status',
            'proposed_starts_at_utc' => 'b.proposed_starts_at_utc',
            'id' => 'b.id',
            default => 'b.created_at',
        };

        $sql = "
            SELECT 
                b.id, b.student_user_id, b.child_id, b.tutor_user_id, b.slot_id,
                b.status, b.inquiry_notes, b.proposed_starts_at_utc, b.proposed_ends_at_utc,
                b.confirmed_starts_at_utc, b.confirmed_ends_at_utc, b.created_at, b.updated_at,
                stu.display_name AS student_name, stu.email AS student_email,
                tut.display_name AS tutor_name, tut.email AS tutor_email,
                ch.first_name AS child_first_name, ch.school_year AS child_school_year
            FROM `bookings` b
            JOIN `users` stu ON b.student_user_id = stu.id
            JOIN `users` tut ON b.tutor_user_id = tut.id
            LEFT JOIN `children` ch ON b.child_id = ch.id
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, b.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $execParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            $lessonStartUtc = $row['confirmed_starts_at_utc'] ?? $row['proposed_starts_at_utc'];
            $lessonEndUtc = $row['confirmed_ends_at_utc'] ?? $row['proposed_ends_at_utc'];

            return [
                'id' => (int) $row['id'],
                'student_user_id' => (int) $row['student_user_id'],
                'student_name' => $row['student_name'],
                'student_email' => $row['student_email'],
                'tutor_user_id' => (int) $row['tutor_user_id'],
                'tutor_name' => $row['tutor_name'],
                'tutor_email' => $row['tutor_email'],
                'child_id' => $row['child_id'] ? (int) $row['child_id'] : null,
                'child_name' => $row['child_first_name'],
                'child_school_year' => $row['child_school_year'],
                'slot_id' => $row['slot_id'] ? (int) $row['slot_id'] : null,
                'status' => $row['status'],
                'inquiry_notes' => $row['inquiry_notes'],
                'starts_at_utc' => $lessonStartUtc,
                'ends_at_utc' => $lessonEndUtc,
                'lesson_time_london' => $lessonStartUtc ? Timezone::utcToLondon($lessonStartUtc) : 'TBD',
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
     * Retrieve full details of a specific booking with complete status audit trail.
     *
     * @param int $bookingId
     * @param UserContext|null $manager
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getBookingDetails(int $bookingId, ?UserContext $manager): array
    {
        $this->requireManager($manager);

        if ($bookingId <= 0) {
            throw new ValidationException('Valid booking ID is required.', 'INVALID_ID', 422);
        }

        // Delegate to BookingService using manager authority
        return $this->bookingService->getBooking($bookingId, $manager);
    }

    /**
     * 6. List tutor availability slots across the platform.
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
    public function listAvailability(
        ?UserContext $manager,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'starts_at_utc',
        string $sortDir = 'ASC'
    ): array {
        $this->requireManager($manager);

        $allowedSortFields = ['id', 'starts_at_utc', 'status', 'created_at'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'sl.status = ?';
            $params[] = strtoupper((string) $filters['status']);
        }

        if (!empty($filters['tutor_id'])) {
            $where[] = 'sl.tutor_user_id = ?';
            $params[] = (int) $filters['tutor_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = 'u.display_name LIKE ?';
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM `availability_slots` sl JOIN `users` u ON sl.tutor_user_id = u.id WHERE {$whereClause}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumn = match ($sortBy) {
            'status' => 'sl.status',
            'created_at' => 'sl.created_at',
            'id' => 'sl.id',
            default => 'sl.starts_at_utc',
        };

        $sql = "
            SELECT 
                sl.id, sl.tutor_user_id, sl.starts_at_utc, sl.ends_at_utc, sl.status, sl.created_at,
                u.display_name AS tutor_name, u.email AS tutor_email,
                b.id AS booking_id, b.status AS booking_status
            FROM `availability_slots` sl
            JOIN `users` u ON sl.tutor_user_id = u.id
            LEFT JOIN `bookings` b ON sl.id = b.slot_id
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, sl.id ASC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $execParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'tutor_user_id' => (int) $row['tutor_user_id'],
                'tutor_name' => $row['tutor_name'],
                'tutor_email' => $row['tutor_email'],
                'status' => $row['status'],
                'starts_at_utc' => $row['starts_at_utc'],
                'ends_at_utc' => $row['ends_at_utc'],
                'starts_at_london' => Timezone::utcToLondon($row['starts_at_utc']),
                'ends_at_london' => Timezone::utcToLondon($row['ends_at_utc'], 'H:i'),
                'booking_id' => $row['booking_id'] ? (int) $row['booking_id'] : null,
                'booking_status' => $row['booking_status'],
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
     * 7. List DBS verification applications and compliance queue.
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
    public function listDbsApplications(
        ?UserContext $manager,
        array $filters = [],
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'updated_at',
        string $sortDir = 'DESC'
    ): array {
        $this->requireManager($manager);

        $allowedSortFields = ['id', 'display_name', 'dbs_status', 'updated_at'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['dbs_status'])) {
            $where[] = 'tp.dbs_status = ?';
            $params[] = strtoupper((string) $filters['dbs_status']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = '(u.display_name LIKE ? OR u.email LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `tutor_profiles` tp JOIN `users` u ON tp.user_id = u.id WHERE {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumn = match ($sortBy) {
            'display_name' => 'u.display_name',
            'dbs_status' => 'tp.dbs_status',
            'id' => 'u.id',
            default => 'tp.updated_at',
        };

        $sql = "
            SELECT 
                u.id, u.display_name, u.email, u.status AS user_status,
                tp.dbs_status, tp.approval_status, tp.updated_at
            FROM `tutor_profiles` tp
            JOIN `users` u ON tp.user_id = u.id
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, u.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $execParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            $dbsMeta = $this->getDbsMetadata((int) $row['id']);
            $certNum = $dbsMeta['certificate_number'] ?? null;
            // Mask certificate number for managerial display (e.g. ******1234)
            $maskedCert = $certNum && strlen($certNum) >= 4 
                ? str_repeat('*', max(0, strlen($certNum) - 4)) . substr($certNum, -4) 
                : ($certNum ? '******' : 'None');

            return [
                'tutor_user_id' => (int) $row['id'],
                'display_name' => $row['display_name'],
                'email' => $row['email'],
                'dbs_status' => $row['dbs_status'],
                'approval_status' => $row['approval_status'],
                'user_status' => $row['user_status'],
                'dbs_certificate_number_masked' => $maskedCert,
                'updated_at' => $row['updated_at'],
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
     * 8. List audit records protected by server-side authorization and controlled application access, with safe metadata sanitization and PII masking.
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
    public function listAuditLogs(
        ?UserContext $manager,
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $this->requireManager($manager);

        $allowedSortFields = ['id', 'action', 'entity_type', 'created_at'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'a.action = ?';
            $params[] = strtoupper((string) $filters['action']);
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type = ?';
            $params[] = strtolower((string) $filters['entity_type']);
        }

        if (!empty($filters['actor_user_id'])) {
            $where[] = 'a.actor_user_id = ?';
            $params[] = (int) $filters['actor_user_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = '(a.action LIKE ? OR u.display_name LIKE ? OR a.metadata_json LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `audit_logs` a LEFT JOIN `users` u ON a.actor_user_id = u.id WHERE {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumn = match ($sortBy) {
            'action' => 'a.action',
            'entity_type' => 'a.entity_type',
            'id' => 'a.id',
            default => 'a.created_at',
        };

        $sql = "
            SELECT 
                a.id, a.actor_user_id, a.action, a.entity_type, a.entity_id, a.metadata_json, a.created_at,
                u.display_name AS actor_name, u.role AS actor_role
            FROM `audit_logs` a
            LEFT JOIN `users` u ON a.actor_user_id = u.id
            WHERE {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, a.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $execParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            $rawMeta = !empty($row['metadata_json']) ? json_decode($row['metadata_json'], true) : [];
            // Strictly redact any sensitive tokens or internal keys before presentation
            $cleanMeta = $this->logger->redactSensitiveData($rawMeta ?: []);

            return [
                'id' => (int) $row['id'],
                'actor_user_id' => $row['actor_user_id'] ? (int) $row['actor_user_id'] : null,
                'actor_name' => $row['actor_name'] ?? 'System',
                'actor_role' => $row['actor_role'] ?? 'SYSTEM',
                'action' => $row['action'],
                'entity_type' => $row['entity_type'],
                'entity_id' => $row['entity_id'] ? (int) $row['entity_id'] : null,
                'metadata' => $cleanMeta,
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
     * 9. Generate factual operational reports and summaries (no financial / payment assumptions).
     *
     * @param UserContext|null $manager
     * @return array
     * @throws ForbiddenException
     */
    public function getReports(?UserContext $manager): array
    {
        $this->requireManager($manager);

        // 1. Tutor Approval Distribution
        $stmtTutorReport = $this->pdo->query('
            SELECT approval_status, COUNT(*) AS count
            FROM `tutor_profiles`
            GROUP BY approval_status
        ');
        $tutorDistribution = $stmtTutorReport->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 2. DBS Safeguarding Distribution
        $stmtDbsReport = $this->pdo->query('
            SELECT dbs_status, COUNT(*) AS count
            FROM `tutor_profiles`
            GROUP BY dbs_status
        ');
        $dbsDistribution = $stmtDbsReport->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 3. Booking Lifecycle Distribution
        $stmtBookingReport = $this->pdo->query('
            SELECT status, COUNT(*) AS count
            FROM `bookings`
            GROUP BY status
        ');
        $bookingDistribution = $stmtBookingReport->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 4. Availability Slot Status Distribution
        $stmtSlotReport = $this->pdo->query('
            SELECT status, COUNT(*) AS count
            FROM `availability_slots`
            GROUP BY status
        ');
        $slotDistribution = $stmtSlotReport->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 5. Total counts
        $totalTutors = array_sum($tutorDistribution);
        $totalBookings = array_sum($bookingDistribution);
        $verifiedDbs = (int) ($dbsDistribution['VERIFIED'] ?? 0);
        $approvedTutors = (int) ($tutorDistribution['APPROVED'] ?? 0);

        $complianceRate = $totalTutors > 0 ? round(($verifiedDbs / $totalTutors) * 100, 1) : 0.0;

        return [
            'tutors' => [
                'distribution' => $tutorDistribution,
                'total' => $totalTutors,
                'approved' => $approvedTutors,
            ],
            'dbs' => [
                'distribution' => $dbsDistribution,
                'compliance_rate_percent' => $complianceRate,
            ],
            'bookings' => [
                'distribution' => $bookingDistribution,
                'total' => $totalBookings,
            ],
            'availability' => [
                'distribution' => $slotDistribution,
            ],
            'blog' => $this->blogService->getBlogStats($manager),
            'newsletter' => $this->newsletterService->getSubscriberStats($manager),
            'generated_at_utc' => Timezone::nowUtc(),
        ];
    }

    /**
     * Get underlying BlogService instance.
     */
    public function getBlogService(): BlogService
    {
        return $this->blogService;
    }

    /**
     * Get underlying NewsletterService instance.
     */
    public function getNewsletterService(): NewsletterService
    {
        return $this->newsletterService;
    }

    /**
     * List blog posts with pagination and status filtering for manager administration.
     *
     * @param UserContext|null $manager
     * @param array $filters
     * @return array
     * @throws ForbiddenException
     */
    public function listBlogPosts(?UserContext $manager, array $filters = []): array
    {
        $this->requireManager($manager);
        return $this->blogService->listPosts($filters, true, $manager);
    }

    /**
     * Get a specific blog post by ID for manager review.
     *
     * @param UserContext|null $manager
     * @param int $postId
     * @return array
     * @throws ForbiddenException
     */
    public function getBlogPost(?UserContext $manager, int $postId): array
    {
        $this->requireManager($manager);
        return $this->blogService->getPostById($postId, $manager);
    }

    /**
     * Create a new blog post.
     *
     * @param UserContext|null $manager
     * @param array $data
     * @return array
     * @throws ForbiddenException
     */
    public function createBlogPost(?UserContext $manager, array $data): array
    {
        $this->requireManager($manager);
        return $this->blogService->createPost($manager, $data);
    }

    /**
     * Update an existing blog post.
     *
     * @param UserContext|null $manager
     * @param int $postId
     * @param array $data
     * @return array
     * @throws ForbiddenException
     */
    public function updateBlogPost(?UserContext $manager, int $postId, array $data): array
    {
        $this->requireManager($manager);
        return $this->blogService->updatePost($manager, $postId, $data);
    }

    /**
     * Publish a blog post.
     *
     * @param UserContext|null $manager
     * @param int $postId
     * @return array
     * @throws ForbiddenException
     */
    public function publishBlogPost(?UserContext $manager, int $postId): array
    {
        $this->requireManager($manager);
        return $this->blogService->publishPost($manager, $postId);
    }

    /**
     * Archive/unpublish a blog post.
     *
     * @param UserContext|null $manager
     * @param int $postId
     * @return array
     * @throws ForbiddenException
     */
    public function archiveBlogPost(?UserContext $manager, int $postId): array
    {
        $this->requireManager($manager);
        return $this->blogService->archivePost($manager, $postId);
    }

    /**
     * Submit a blog post for moderation.
     *
     * @param UserContext|null $manager
     * @param int $postId
     * @return array
     * @throws ForbiddenException
     */
    public function submitBlogPost(?UserContext $manager, int $postId): array
    {
        $this->requireManager($manager);
        return $this->blogService->submitPost($manager, $postId);
    }

    /**
     * Approve a submitted blog post.
     *
     * @param UserContext|null $manager
     * @param int $postId
     * @return array
     * @throws ForbiddenException
     */
    public function approveBlogPost(?UserContext $manager, int $postId): array
    {
        $this->requireManager($manager);
        return $this->blogService->approvePost($manager, $postId);
    }

    /**
     * Reject a submitted blog post.
     *
     * @param UserContext|null $manager
     * @param int $postId
     * @param string|null $reason
     * @return array
     * @throws ForbiddenException
     */
    public function rejectBlogPost(?UserContext $manager, int $postId, ?string $reason = null): array
    {
        $this->requireManager($manager);
        return $this->blogService->rejectPost($manager, $postId, $reason);
    }

    /**
     * List newsletter subscribers with pagination and filtering for manager administration.
     *
     * @param UserContext|null $manager
     * @param array $filters
     * @return array
     * @throws ForbiddenException
     */
    public function listNewsletterSubscribers(?UserContext $manager, array $filters = []): array
    {
        $this->requireManager($manager);
        return $this->newsletterService->listSubscribers($manager, $filters);
    }

    /**
     * Get subscriber metrics for manager dashboard.
     *
     * @param UserContext|null $manager
     * @return array
     * @throws ForbiddenException
     */
    public function getNewsletterStats(?UserContext $manager): array
    {
        $this->requireManager($manager);
        return $this->newsletterService->getSubscriberStats($manager);
    }
}
