<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\BlogService;
use App\Support\Csrf;
use App\Support\View;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$blogService = new BlogService($db);

$successMessage = '';
$errorMessage = '';

// Determine active manager context
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUser = null;

if ($currentUserId !== null) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $currentUserId]);
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Fallback: If no session user, look up manager user in DB for governance portal view
if ($currentUser === null || !$currentUser->isManager()) {
    $stmt = $db->query("SELECT * FROM users WHERE role = 'MANAGER' AND status = 'ACTIVE' LIMIT 1");
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Handle Manager Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null && $currentUser->isManager()) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh and try again.';
    } else {
        $action = strtoupper(trim((string) ($_POST['action'] ?? '')));
        $postId = (int) ($_POST['post_id'] ?? 0);

        try {
            switch ($action) {
                case 'CREATE':
                    $newPost = $blogService->createPost($currentUser, $_POST);
                    $successMessage = "Blog post '{$newPost['title']}' created successfully.";
                    break;

                case 'UPDATE':
                    if ($postId <= 0) {
                        $errorMessage = 'Valid post ID is required for editing.';
                    } else {
                        $updatedPost = $blogService->updatePost($currentUser, $postId, $_POST);
                        $successMessage = "Blog post '{$updatedPost['title']}' updated successfully.";
                    }
                    break;

                case 'APPROVE':
                    if ($postId <= 0) {
                        $errorMessage = 'Valid post ID is required for approval.';
                    } else {
                        $appPost = $blogService->approvePost($currentUser, $postId);
                        $successMessage = "Blog post '{$appPost['title']}' approved successfully.";
                    }
                    break;

                case 'REJECT':
                    if ($postId <= 0) {
                        $errorMessage = 'Valid post ID is required for rejection.';
                    } else {
                        $reason = trim((string) ($_POST['reason'] ?? 'Editorial criteria not met'));
                        $rejPost = $blogService->rejectPost($currentUser, $postId, $reason);
                        $successMessage = "Blog post '{$rejPost['title']}' rejected.";
                    }
                    break;

                case 'PUBLISH':
                    if ($postId <= 0) {
                        $errorMessage = 'Valid post ID is required for publishing.';
                    } else {
                        $currentPost = $blogService->getPostById($postId, $currentUser);
                        if ($currentPost['status'] === 'SUBMITTED') {
                            $blogService->approvePost($currentUser, $postId);
                        }
                        $pubPost = $blogService->publishPost($currentUser, $postId);
                        $successMessage = "Blog post '{$pubPost['title']}' published successfully.";
                    }
                    break;

                case 'SEND_NEWSLETTER':
                    if ($postId <= 0) {
                        $errorMessage = 'Valid post ID is required for newsletter broadcast.';
                    } else {
                        $newsletterService = new \App\Services\NewsletterService($db);
                        $result = $newsletterService->broadcastPublishedBlogPost($postId, $currentUser);
                        $successMessage = "Newsletter successfully sent to {$result['sent_count']} subscriber(s) for '{$result['title']}'.";
                    }
                    break;

                case 'ARCHIVE':
                    if ($postId <= 0) {
                        $errorMessage = 'Valid post ID is required for archiving.';
                    } else {
                        $archPost = $blogService->archivePost($currentUser, $postId);
                        $successMessage = "Blog post '{$archPost['title']}' moved to archive.";
                    }
                    break;

                default:
                    $errorMessage = "Unknown editorial action '{$action}'.";
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

// Fetch posts according to filter if manager context available
$filterStatus = $_GET['status'] ?? 'ALL';
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['search'] ?? ''));

$postsResult = ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 15, 'total' => 0, 'total_pages' => 1]];
$editPost = null;

if ($currentUser !== null && $currentUser->isManager()) {
    try {
        $filters = [
            'status' => $filterStatus,
            'page' => $page,
            'per_page' => 15,
            'search' => $search,
        ];
        $postsResult = $blogService->listPosts($filters, true, $currentUser);

        $editId = (int) ($_GET['edit_id'] ?? 0);
        if ($editId > 0) {
            $editPost = $blogService->getPostById($editId, $currentUser);
        }
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = 'Access restricted: Manager authentication required to view blog management.';
}

$csrfToken = Csrf::generateToken();

View::render(
    'manager-blog',
    [
        'posts' => $postsResult['items'],
        'pagination' => $postsResult['pagination'],
        'filterStatus' => $filterStatus,
        'search' => $search,
        'editPost' => $editPost,
        'currentUser' => $currentUser,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Blog & Editorial Management — Platform Administration — AppTutors UK',
    'Managerial editorial controls for authoring, reviewing, publishing, and archiving educational blog articles.'
);
