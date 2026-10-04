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

class BlogService
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const ALLOWED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_PUBLISHED,
        self::STATUS_REJECTED,
        self::STATUS_ARCHIVED,
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
     * Enforce active author role (tutor or manager).
     *
     * @param UserContext|null $author
     * @throws ForbiddenException
     */
    private function requireAuthor(?UserContext $author): void
    {
        Authorization::requireAuthenticatedUser($author);
        Authorization::requireRole($author, [Authorization::ROLE_MANAGER, Authorization::ROLE_TUTOR]);
        Authorization::requireActiveStatus($author);
    }

    /**
     * Validate pagination and sort parameters.
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
     * List blog posts with role-aware visibility controls.
     * Managers can view all statuses (DRAFT, PUBLISHED, ARCHIVED, etc.) and filter by status.
     * Non-managers and public users are strictly restricted to PUBLISHED posts only.
     *
     * @param UserContext|null $currentUser
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortDir
     * @return array
     * @throws ValidationException
     */
    public function listPosts(
        mixed $first = null,
        mixed $second = [],
        mixed $third = 1,
        mixed $fourth = 20,
        mixed $fifth = 'created_at',
        mixed $sixth = 'DESC'
    ): array {
        if ($first instanceof UserContext || ($first === null && is_array($second))) {
            $currentUser = $first;
            $filters = (array) $second;
            $page = isset($filters['page']) ? (int) $filters['page'] : (is_int($third) ? $third : 1);
            $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : (is_int($fourth) ? $fourth : 20);
            $sortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : (is_string($fifth) && !in_array(strtoupper($fifth), ['ASC', 'DESC'], true) ? $fifth : 'created_at');
            $sortDir = isset($filters['sort_dir']) ? (string) $filters['sort_dir'] : (in_array(strtoupper((string) $sixth), ['ASC', 'DESC'], true) ? $sixth : 'DESC');
        } elseif (is_array($first)) {
            $filters = $first;
            if ($second instanceof UserContext) {
                $currentUser = $second;
            } elseif ($third instanceof UserContext) {
                $currentUser = $third;
            } else {
                $currentUser = null;
            }
            $page = isset($filters['page']) ? (int) $filters['page'] : (is_int($second) ? $second : 1);
            $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : (is_int($third) ? $third : 20);
            $sortBy = isset($filters['sort_by']) ? (string) $filters['sort_by'] : 'created_at';
            $sortDir = isset($filters['sort_dir']) ? (string) $filters['sort_dir'] : 'DESC';
        } else {
            $currentUser = null;
            $filters = [];
            $page = 1;
            $perPage = 20;
            $sortBy = 'created_at';
            $sortDir = 'DESC';
        }
        $allowedSortFields = ['id', 'title', 'status', 'published_at', 'created_at'];
        $this->validatePaginationAndSort($page, $perPage, $sortBy, $sortDir, $allowedSortFields);

        $isManager = ($currentUser !== null && $currentUser->isManager() && $currentUser->isActive());
        $isTutor = ($currentUser !== null && $currentUser->isTutor() && $currentUser->isActive());

        $where = [];
        $params = [];

        if ($isManager) {
            if (!empty($filters['status'])) {
                $status = strtoupper((string) $filters['status']);
                if (in_array($status, self::ALLOWED_STATUSES, true)) {
                    $where[] = 'bp.status = ?';
                    $params[] = $status;
                }
            }
        } elseif ($isTutor && (!empty($filters['own']) || (isset($filters['author_user_id']) && (int) $filters['author_user_id'] === $currentUser->id))) {
            $where[] = 'bp.author_user_id = ?';
            $params[] = $currentUser->id;
            if (!empty($filters['status'])) {
                $status = strtoupper((string) $filters['status']);
                if (in_array($status, self::ALLOWED_STATUSES, true)) {
                    $where[] = 'bp.status = ?';
                    $params[] = $status;
                }
            }
        } else {
            // Strictly enforce published visibility for non-managers / public
            $where[] = "bp.status = 'PUBLISHED'";
        }

        if (!empty($filters['search'])) {
            $term = '%' . Validator::sanitizeString((string) $filters['search']) . '%';
            $where[] = '(bp.title LIKE ? OR bp.excerpt LIKE ? OR bp.slug LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 1. Total count query
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `blog_posts` bp {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $orderDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumn = match ($sortBy) {
            'title' => 'bp.title',
            'status' => 'bp.status',
            'published_at' => 'bp.published_at',
            'id' => 'bp.id',
            default => 'bp.created_at',
        };

        // 2. Paginated data query
        $sql = "
            SELECT 
                bp.id, bp.author_user_id, bp.reviewed_by_user_id, bp.title, bp.slug,
                bp.excerpt, bp.body, bp.status, bp.published_at, bp.reviewed_at,
                bp.created_at, bp.updated_at,
                u.display_name AS author_name,
                r.display_name AS reviewer_name
            FROM `blog_posts` bp
            LEFT JOIN `users` u ON bp.author_user_id = u.id
            LEFT JOIN `users` r ON bp.reviewed_by_user_id = r.id
            {$whereClause}
            ORDER BY {$sortColumn} {$orderDir}, bp.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $execParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'author_user_id' => (int) $row['author_user_id'],
                'author_name' => $row['author_name'] ?? 'Editorial Educator',
                'reviewed_by_user_id' => $row['reviewed_by_user_id'] ? (int) $row['reviewed_by_user_id'] : null,
                'reviewer_name' => $row['reviewer_name'] ?? null,
                'title' => $row['title'],
                'slug' => $row['slug'],
                'excerpt' => $row['excerpt'],
                'body' => $row['body'],
                'status' => $row['status'],
                'published_at' => $row['published_at'],
                'published_date_london' => !empty($row['published_at']) 
                    ? Timezone::utcToLondon($row['published_at'], 'j F Y') 
                    : null,
                'reviewed_at' => $row['reviewed_at'],
                'created_at' => $row['created_at'],
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
     * Retrieve a blog post by its unique URL slug.
     * Non-managers are strictly restricted to PUBLISHED posts only.
     *
     * @param string $slug
     * @param UserContext|null $currentUser
     * @return array
     * @throws ValidationException
     */
    public function getPostBySlug(string $slug, ?UserContext $currentUser = null): array
    {
        $cleanSlug = trim($slug);
        if ($cleanSlug === '' || strlen($cleanSlug) > 255) {
            throw new ValidationException('Invalid article slug.', 'INVALID_SLUG', 422);
        }

        // Strict slug format: lowercase letters, numbers, hyphens
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $cleanSlug)) {
            throw new ValidationException('Malformed article slug format.', 'INVALID_SLUG', 422);
        }

        $isManager = ($currentUser !== null && $currentUser->isManager() && $currentUser->isActive());

        $sql = "
            SELECT 
                bp.id, bp.author_user_id, bp.reviewed_by_user_id, bp.title, bp.slug,
                bp.excerpt, bp.body, bp.status, bp.published_at, bp.reviewed_at,
                bp.created_at, bp.updated_at,
                u.display_name AS author_name,
                r.display_name AS reviewer_name
            FROM `blog_posts` bp
            LEFT JOIN `users` u ON bp.author_user_id = u.id
            LEFT JOIN `users` r ON bp.reviewed_by_user_id = r.id
            WHERE bp.slug = ?
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cleanSlug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidationException('Article not found.', 'POST_NOT_FOUND', 404);
        }

        // Non-managers can ONLY view PUBLISHED posts
        if (!$isManager && $row['status'] !== self::STATUS_PUBLISHED) {
            throw new ValidationException('Article not found.', 'POST_NOT_FOUND', 404);
        }

        return [
            'id' => (int) $row['id'],
            'author_user_id' => (int) $row['author_user_id'],
            'author_name' => $row['author_name'] ?? 'Editorial Educator',
            'reviewed_by_user_id' => $row['reviewed_by_user_id'] ? (int) $row['reviewed_by_user_id'] : null,
            'reviewer_name' => $row['reviewer_name'] ?? null,
            'title' => $row['title'],
            'slug' => $row['slug'],
            'excerpt' => $row['excerpt'],
            'body' => $row['body'],
            'status' => $row['status'],
            'published_at' => $row['published_at'],
            'published_date_london' => !empty($row['published_at']) 
                ? Timezone::utcToLondon($row['published_at'], 'j F Y') 
                : null,
            'reviewed_at' => $row['reviewed_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * Retrieve post by primary key ID.
     * Managers can view any post.
     * Tutors can view their own posts regardless of status.
     * Non-authors cannot view unpublished posts (tutors get 403, public gets 404).
     *
     * @param mixed $first
     * @param mixed $second
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function getPostById(mixed $first, mixed $second = null): array
    {
        if ($first instanceof UserContext || $first === null) {
            $user = $first;
            $postId = (int) $second;
        } else {
            $postId = (int) $first;
            $user = $second instanceof UserContext ? $second : null;
        }

        if ($postId <= 0) {
            throw new ValidationException('Valid article ID is required.', 'INVALID_ID', 422);
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                bp.id, bp.author_user_id, bp.reviewed_by_user_id, bp.title, bp.slug,
                bp.excerpt, bp.body, bp.status, bp.published_at, bp.reviewed_at,
                bp.created_at, bp.updated_at,
                u.display_name AS author_name,
                r.display_name AS reviewer_name
            FROM `blog_posts` bp
            LEFT JOIN `users` u ON bp.author_user_id = u.id
            LEFT JOIN `users` r ON bp.reviewed_by_user_id = r.id
            WHERE bp.id = ?
            LIMIT 1
        ");
        $stmt->execute([$postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ValidationException('Article not found.', 'POST_NOT_FOUND', 404);
        }

        $isManager = ($user !== null && $user->isManager() && $user->isActive());
        $isAuthor = ($user !== null && (int) $row['author_user_id'] === $user->id && $user->isActive());
        $isPublished = ($row['status'] === self::STATUS_PUBLISHED);

        if (!$isManager && !$isAuthor) {
            if (!$isPublished) {
                if ($user !== null && $user->isTutor()) {
                    throw new ForbiddenException('You do not have permission to view another tutor\'s unpublished post.', 'FORBIDDEN');
                }
                throw new ValidationException('Article not found.', 'POST_NOT_FOUND', 404);
            }
        }

        return [
            'id' => (int) $row['id'],
            'author_user_id' => (int) $row['author_user_id'],
            'author_name' => $row['author_name'] ?? 'Editorial Educator',
            'reviewed_by_user_id' => $row['reviewed_by_user_id'] ? (int) $row['reviewed_by_user_id'] : null,
            'reviewer_name' => $row['reviewer_name'] ?? null,
            'title' => $row['title'],
            'slug' => $row['slug'],
            'excerpt' => $row['excerpt'],
            'body' => $row['body'],
            'status' => $row['status'],
            'published_at' => $row['published_at'],
            'published_date_london' => !empty($row['published_at']) 
                ? Timezone::utcToLondon($row['published_at'], 'j F Y') 
                : null,
            'reviewed_at' => $row['reviewed_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * Create a new blog post in DRAFT status.
     * Author must be an active TUTOR or MANAGER.
     * Tutors can only author DRAFT posts. Managers create posts through the moderation workflow.
     *
     * @param mixed $first
     * @param mixed $second
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function createPost(mixed $first, mixed $second = null): array
    {
        if ($first instanceof UserContext || $first === null) {
            $author = $first;
            $data = (array) $second;
        } else {
            $data = (array) $first;
            $author = $second instanceof UserContext ? $second : null;
        }

        $this->requireAuthor($author);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException('Article title is required.', 'TITLE_REQUIRED', 422);
        }
        if (mb_strlen($title) > 255) {
            throw new ValidationException('Article title cannot exceed 255 characters.', 'TITLE_TOO_LONG', 422);
        }

        // Slug derivation / validation
        $rawSlug = trim((string) ($data['slug'] ?? ''));
        $slug = $rawSlug !== '' ? $this->sanitizeSlug($rawSlug) : $this->slugify($title);

        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new ValidationException('Valid article slug is required (alphanumeric and hyphens only).', 'INVALID_SLUG', 422);
        }
        if (strlen($slug) > 255) {
            throw new ValidationException('Slug cannot exceed 255 characters.', 'SLUG_TOO_LONG', 422);
        }

        // Uniqueness check for slug
        if ($rawSlug !== '') {
            $stmtSlugCheck = $this->pdo->prepare('SELECT id FROM `blog_posts` WHERE slug = ? LIMIT 1');
            $stmtSlugCheck->execute([$slug]);
            if ($stmtSlugCheck->fetch()) {
                throw new ValidationException("An article with slug '{$slug}' already exists.", 'SLUG_ALREADY_EXISTS', 409);
            }
        } else {
            // Auto-generated slug: automatically make unique if collision occurs
            $baseSlug = $slug;
            $counter = 1;
            $stmtCheck = $this->pdo->prepare('SELECT id FROM `blog_posts` WHERE slug = ? LIMIT 1');
            while (true) {
                $stmtCheck->execute([$slug]);
                if (!$stmtCheck->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            throw new ValidationException('Article body content is required.', 'BODY_REQUIRED', 422);
        }

        $excerpt = isset($data['excerpt']) && trim((string) $data['excerpt']) !== '' 
            ? trim((string) $data['excerpt']) 
            : null;

        // Status governance: Tutors can ONLY author DRAFT posts
        $requestedStatus = isset($data['status']) ? strtoupper(trim((string) $data['status'])) : self::STATUS_DRAFT;

        if ($author->isTutor()) {
            if ($requestedStatus !== self::STATUS_DRAFT) {
                throw new ForbiddenException('Tutors can only create blog posts in DRAFT status.', 'FORBIDDEN');
            }
            $status = self::STATUS_DRAFT;
        } else {
            // Manager cannot create directly as PUBLISHED bypassing moderation workflow
            if ($requestedStatus === self::STATUS_PUBLISHED) {
                throw new ValidationException('Posts cannot be created directly as PUBLISHED. Must follow moderation workflow (DRAFT -> SUBMITTED -> APPROVED -> PUBLISHED).', 'INVALID_STATUS_TRANSITION', 422);
            }
            if (!in_array($requestedStatus, [self::STATUS_DRAFT, self::STATUS_SUBMITTED], true)) {
                throw new ValidationException("Invalid initial status '{$requestedStatus}'.", 'INVALID_STATUS', 422);
            }
            $status = $requestedStatus;
        }

        $now = Timezone::nowUtc();

        $stmt = $this->pdo->prepare("
            INSERT INTO `blog_posts` (
                author_user_id, reviewed_by_user_id, title, slug, excerpt, body,
                status, published_at, reviewed_at, created_at, updated_at
            ) VALUES (?, NULL, ?, ?, ?, ?, ?, NULL, NULL, ?, ?)
        ");

        $stmt->execute([
            $author->id,
            $title,
            $slug,
            $excerpt,
            $body,
            $status,
            $now,
            $now,
        ]);

        $postId = (int) $this->pdo->lastInsertId();

        // Audit log action
        $this->audit->log(
            action: 'BLOG_POST_CREATED',
            entityType: 'BLOG_POST',
            entityId: $postId,
            actorUserId: $author->id,
            metadata: [
                'title' => $title,
                'slug' => $slug,
                'status' => $status,
                'author_role' => $author->role,
            ]
        );

        $this->logger->info("User ID {$author->id} ({$author->role}) created blog post ID {$postId} (slug: {$slug}, status: {$status})");

        return $this->getPostById($postId, $author);
    }

    /**
     * Update an existing blog post.
     * Tutors can only update their own DRAFT or REJECTED posts.
     * Editing a REJECTED post returns its status to DRAFT.
     * Managers can update editorial content.
     *
     * @param mixed $first
     * @param mixed $second
     * @param mixed $third
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function updatePost(mixed $first, mixed $second = null, mixed $third = null): array
    {
        if ($first instanceof UserContext || ($first === null && is_int($second))) {
            $user = $first;
            $postId = (int) $second;
            $data = (array) $third;
        } elseif (is_int($first)) {
            $postId = (int) $first;
            if ($second instanceof UserContext || $second === null) {
                $user = $second;
                $data = (array) $third;
            } else {
                $data = (array) $second;
                $user = $third instanceof UserContext ? $third : null;
            }
        } else {
            $user = null;
            $postId = (int) $second;
            $data = (array) $third;
        }

        $this->requireAuthor($user);

        if ($postId <= 0) {
            throw new ValidationException('Valid article ID is required.', 'INVALID_ID', 422);
        }

        $existing = $this->getPostById($postId, $user);

        // Ownership and status validation for Tutors
        if ($user->isTutor()) {
            if ((int) $existing['author_user_id'] !== $user->id) {
                throw new ForbiddenException('You can only edit your own blog posts.', 'FORBIDDEN');
            }
            if (!in_array($existing['status'], [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
                throw new ValidationException("Cannot edit post in '{$existing['status']}' status. Only DRAFT or REJECTED posts can be edited.", 'INVALID_STATUS', 422);
            }
            // Editing a rejected post returns it to DRAFT for resubmission
            $status = self::STATUS_DRAFT;
        } else {
            // Manager editing
            $status = $existing['status'];
            if (isset($data['status'])) {
                $newStatus = strtoupper(trim((string) $data['status']));
                if (!in_array($newStatus, self::ALLOWED_STATUSES, true)) {
                    throw new ValidationException("Invalid status '{$newStatus}'.", 'INVALID_STATUS', 422);
                }
                if ($newStatus === self::STATUS_PUBLISHED && $existing['status'] !== self::STATUS_APPROVED) {
                    throw new ValidationException("Cannot transition post directly to PUBLISHED. Must follow moderation workflow (SUBMITTED -> APPROVED -> PUBLISHED).", 'INVALID_STATUS_TRANSITION', 422);
                }
                $status = $newStatus;
            }
        }

        $title = isset($data['title']) ? trim((string) $data['title']) : $existing['title'];
        if ($title === '') {
            throw new ValidationException('Article title cannot be empty.', 'TITLE_REQUIRED', 422);
        }
        if (mb_strlen($title) > 255) {
            throw new ValidationException('Article title cannot exceed 255 characters.', 'TITLE_TOO_LONG', 422);
        }

        // Slug update check
        $slug = $existing['slug'];
        if (isset($data['slug'])) {
            $rawSlug = trim((string) $data['slug']);
            $newSlug = $this->sanitizeSlug($rawSlug);
            if ($newSlug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $newSlug)) {
                throw new ValidationException('Valid article slug is required (alphanumeric and hyphens only).', 'INVALID_SLUG', 422);
            }
            if ($newSlug !== $existing['slug']) {
                $stmtSlugCheck = $this->pdo->prepare('SELECT id FROM `blog_posts` WHERE slug = ? AND id != ? LIMIT 1');
                $stmtSlugCheck->execute([$newSlug, $postId]);
                if ($stmtSlugCheck->fetch()) {
                    throw new ValidationException("An article with slug '{$newSlug}' already exists.", 'SLUG_ALREADY_EXISTS', 409);
                }
                $slug = $newSlug;
            }
        }

        $body = isset($data['body']) ? trim((string) $data['body']) : $existing['body'];
        if ($body === '') {
            throw new ValidationException('Article body content cannot be empty.', 'BODY_REQUIRED', 422);
        }

        $excerpt = isset($data['excerpt']) ? (trim((string) $data['excerpt']) ?: null) : $existing['excerpt'];
        $now = Timezone::nowUtc();

        $stmt = $this->pdo->prepare("
            UPDATE `blog_posts`
            SET title = ?, slug = ?, excerpt = ?, body = ?, status = ?, updated_at = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $title,
            $slug,
            $excerpt,
            $body,
            $status,
            $now,
            $postId,
        ]);

        $this->audit->log(
            action: 'BLOG_POST_UPDATED',
            entityType: 'BLOG_POST',
            entityId: $postId,
            actorUserId: $user->id,
            metadata: [
                'title' => $title,
                'slug' => $slug,
                'status' => $status,
                'previous_status' => $existing['status'],
            ]
        );

        $this->logger->info("User ID {$user->id} updated blog post ID {$postId}");

        return $this->getPostById($postId, $user);
    }

    /**
     * Submit a draft or rejected post for managerial moderation.
     * Tutors can submit their own posts; Managers can submit posts.
     * Transitions: DRAFT -> SUBMITTED or REJECTED -> SUBMITTED.
     *
     * @param mixed $first
     * @param mixed $second
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function submitPost(mixed $first, mixed $second = null): array
    {
        if ($first instanceof UserContext || $first === null) {
            $user = $first;
            $postId = (int) $second;
        } else {
            $postId = (int) $first;
            $user = $second instanceof UserContext ? $second : null;
        }

        $this->requireAuthor($user);

        if ($postId <= 0) {
            throw new ValidationException('Valid article ID is required.', 'INVALID_ID', 422);
        }

        $existing = $this->getPostById($postId, $user);

        if ($user->isTutor() && (int) $existing['author_user_id'] !== $user->id) {
            throw new ForbiddenException('You can only submit your own blog posts for review.', 'FORBIDDEN');
        }

        if (!in_array($existing['status'], [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            throw new ValidationException("Only DRAFT or REJECTED posts can be submitted for moderation. Current status is '{$existing['status']}'.", 'INVALID_STATUS_TRANSITION', 422);
        }

        $now = Timezone::nowUtc();

        $stmt = $this->pdo->prepare("
            UPDATE `blog_posts`
            SET status = 'SUBMITTED', updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$now, $postId]);

        $this->audit->log(
            action: 'BLOG_POST_SUBMITTED',
            entityType: 'BLOG_POST',
            entityId: $postId,
            actorUserId: $user->id,
            metadata: [
                'previous_status' => $existing['status'],
                'title' => $existing['title'],
                'slug' => $existing['slug'],
            ]
        );

        $this->logger->info("User ID {$user->id} submitted blog post ID {$postId} for moderation");

        return $this->getPostById($postId, $user);
    }

    /**
     * Approve a submitted post for publication (manager-authorized).
     * Transition: SUBMITTED -> APPROVED.
     *
     * @param mixed $first
     * @param mixed $second
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function approvePost(mixed $first, mixed $second = null): array
    {
        if ($first instanceof UserContext || $first === null) {
            $manager = $first;
            $postId = (int) $second;
        } else {
            $postId = (int) $first;
            $manager = $second instanceof UserContext ? $second : null;
        }

        $this->requireManager($manager);

        if ($postId <= 0) {
            throw new ValidationException('Valid article ID is required.', 'INVALID_ID', 422);
        }

        $existing = $this->getPostById($postId, $manager);

        if ($existing['status'] !== self::STATUS_SUBMITTED) {
            throw new ValidationException("Only SUBMITTED posts can be approved. Current status is '{$existing['status']}'.", 'INVALID_STATUS_TRANSITION', 422);
        }

        $now = Timezone::nowUtc();

        $stmt = $this->pdo->prepare("
            UPDATE `blog_posts`
            SET status = 'APPROVED',
                reviewed_by_user_id = ?,
                reviewed_at = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$manager->id, $now, $now, $postId]);

        $this->audit->log(
            action: 'BLOG_POST_APPROVED',
            entityType: 'BLOG_POST',
            entityId: $postId,
            actorUserId: $manager->id,
            metadata: [
                'previous_status' => $existing['status'],
                'title' => $existing['title'],
                'slug' => $existing['slug'],
            ]
        );

        $this->logger->info("Manager ID {$manager->id} approved blog post ID {$postId}");

        return $this->getPostById($postId, $manager);
    }

    /**
     * Reject a submitted or approved post (manager-authorized).
     * Transition: SUBMITTED -> REJECTED or APPROVED -> REJECTED.
     *
     * @param mixed $first
     * @param mixed $second
     * @param mixed $third
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function rejectPost(mixed $first, mixed $second = null, mixed $third = null): array
    {
        if ($first instanceof UserContext || ($first === null && is_int($second))) {
            $manager = $first;
            $postId = (int) $second;
            $reason = is_string($third) ? $third : null;
        } elseif (is_int($first)) {
            $postId = (int) $first;
            if ($second instanceof UserContext || $second === null) {
                $manager = $second;
                $reason = is_string($third) ? $third : null;
            } else {
                $reason = is_string($second) ? $second : null;
                $manager = $third instanceof UserContext ? $third : null;
            }
        } else {
            $manager = null;
            $postId = (int) $second;
            $reason = null;
        }

        $this->requireManager($manager);

        if ($postId <= 0) {
            throw new ValidationException('Valid article ID is required.', 'INVALID_ID', 422);
        }

        $existing = $this->getPostById($postId, $manager);

        if (!in_array($existing['status'], [self::STATUS_SUBMITTED, self::STATUS_APPROVED], true)) {
            throw new ValidationException("Only SUBMITTED or APPROVED posts can be rejected. Current status is '{$existing['status']}'.", 'INVALID_STATUS_TRANSITION', 422);
        }

        $now = Timezone::nowUtc();

        $stmt = $this->pdo->prepare("
            UPDATE `blog_posts`
            SET status = 'REJECTED',
                reviewed_by_user_id = ?,
                reviewed_at = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$manager->id, $now, $now, $postId]);

        $this->audit->log(
            action: 'BLOG_POST_REJECTED',
            entityType: 'BLOG_POST',
            entityId: $postId,
            actorUserId: $manager->id,
            metadata: [
                'previous_status' => $existing['status'],
                'reason' => $reason,
                'title' => $existing['title'],
                'slug' => $existing['slug'],
            ]
        );

        $this->logger->info("Manager ID {$manager->id} rejected blog post ID {$postId}");

        return $this->getPostById($postId, $manager);
    }

    /**
     * Publish an approved article (manager-authorized).
     * Publication strictly requires the post to be in APPROVED state.
     * Direct DRAFT -> PUBLISHED transitions are blocked.
     * Transition: APPROVED -> PUBLISHED.
     *
     * @param mixed $first
     * @param mixed $second
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function publishPost(mixed $first, mixed $second = null): array
    {
        if ($first instanceof UserContext || $first === null) {
            $manager = $first;
            $postId = (int) $second;
        } else {
            $postId = (int) $first;
            $manager = $second instanceof UserContext ? $second : null;
        }

        $this->requireManager($manager);

        if ($postId <= 0) {
            throw new ValidationException('Valid article ID is required.', 'INVALID_ID', 422);
        }

        $existing = $this->getPostById($postId, $manager);

        if ($existing['status'] !== self::STATUS_APPROVED) {
            throw new ValidationException("Post must be in APPROVED status before it can be published. Current status is '{$existing['status']}'.", 'INVALID_STATUS_TRANSITION', 422);
        }

        $now = Timezone::nowUtc();

        $stmt = $this->pdo->prepare("
            UPDATE `blog_posts`
            SET status = 'PUBLISHED',
                published_at = COALESCE(published_at, ?),
                reviewed_by_user_id = COALESCE(reviewed_by_user_id, ?),
                reviewed_at = COALESCE(reviewed_at, ?),
                updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$now, $manager->id, $now, $now, $postId]);

        $this->audit->log(
            action: 'BLOG_POST_PUBLISHED',
            entityType: 'BLOG_POST',
            entityId: $postId,
            actorUserId: $manager->id,
            metadata: [
                'previous_status' => $existing['status'],
                'slug' => $existing['slug'],
            ]
        );

        $this->logger->info("Manager ID {$manager->id} published blog post ID {$postId}");

        return $this->getPostById($postId, $manager);
    }

    /**
     * Archive an article (manager-authorized non-destructive status transition).
     * Transitions: PUBLISHED -> ARCHIVED or APPROVED -> ARCHIVED.
     *
     * @param mixed $first
     * @param mixed $second
     * @return array
     * @throws ForbiddenException
     * @throws ValidationException
     */
    public function archivePost(mixed $first, mixed $second = null): array
    {
        if ($first instanceof UserContext || $first === null) {
            $manager = $first;
            $postId = (int) $second;
        } else {
            $postId = (int) $first;
            $manager = $second instanceof UserContext ? $second : null;
        }

        $this->requireManager($manager);

        if ($postId <= 0) {
            throw new ValidationException('Valid article ID is required.', 'INVALID_ID', 422);
        }

        $existing = $this->getPostById($postId, $manager);

        if (!in_array($existing['status'], [self::STATUS_PUBLISHED, self::STATUS_APPROVED], true)) {
            throw new ValidationException("Only PUBLISHED or APPROVED posts can be archived. Current status is '{$existing['status']}'.", 'INVALID_STATUS_TRANSITION', 422);
        }

        $now = Timezone::nowUtc();

        $stmt = $this->pdo->prepare("
            UPDATE `blog_posts`
            SET status = 'ARCHIVED', updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$now, $postId]);

        $this->audit->log(
            action: 'BLOG_POST_ARCHIVED',
            entityType: 'BLOG_POST',
            entityId: $postId,
            actorUserId: $manager->id,
            metadata: [
                'previous_status' => $existing['status'],
                'slug' => $existing['slug'],
            ]
        );

        $this->logger->info("Manager ID {$manager->id} archived blog post ID {$postId}");

        return $this->getPostById($postId, $manager);
    }

    /**
     * Return summary metrics for manager editorial desk.
     *
     * @param UserContext|null $manager
     * @return array
     * @throws ForbiddenException
     */
    public function getBlogStats(?UserContext $manager): array
    {
        $this->requireManager($manager);

        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END) AS submitted,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'PUBLISHED' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = 'ARCHIVED' THEN 1 ELSE 0 END) AS archived
            FROM `blog_posts`
        ");

        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'drafts' => (int) ($stats['drafts'] ?? 0),
            'submitted' => (int) ($stats['submitted'] ?? 0),
            'approved' => (int) ($stats['approved'] ?? 0),
            'published' => (int) ($stats['published'] ?? 0),
            'archived' => (int) ($stats['archived'] ?? 0),
        ];
    }

    /**
     * Helper to slugify a text string safely.
     */
    public function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        // Replace non-alphanumeric characters with hyphens
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Helper to sanitize and format a manually supplied slug.
     */
    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '', $slug);
        $slug = (string) preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
