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

// Determine active tutor context (from session, auth token, or query param)
$currentUserId = $_SESSION['user_id'] ?? (isset($_GET['id']) ? (int) $_GET['id'] : null);
$currentUser = null;

if ($currentUserId !== null) {
    $stmt = $db->prepare('SELECT * FROM `users` WHERE `id` = :id');
    $stmt->execute([':id' => $currentUserId]);
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Fallback: If no tutor specified, look up first active tutor in DB for demonstration
if ($currentUser === null) {
    $stmt = $db->query("
        SELECT u.* 
        FROM `users` u 
        JOIN `tutor_profiles` tp ON u.id = tp.user_id 
        WHERE u.role = 'TUTOR' 
        LIMIT 1
    ");
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $postId = (int) ($_POST['post_id'] ?? 0);

        try {
            if ($action === 'create_post') {
                $newPost = $blogService->createPost($currentUser, [
                    'title' => trim((string) ($_POST['title'] ?? '')),
                    'slug' => trim((string) ($_POST['slug'] ?? '')),
                    'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
                    'body' => trim((string) ($_POST['body'] ?? '')),
                    'status' => 'DRAFT',
                ]);
                $successMessage = "Draft blog post '{$newPost['title']}' created successfully.";
            } elseif ($action === 'submit_post') {
                $subPost = $blogService->submitPost($currentUser, $postId);
                $successMessage = "Blog post '{$subPost['title']}' submitted to Manager for moderation and review.";
            } elseif ($action === 'update_post') {
                $upPost = $blogService->updatePost($currentUser, $postId, [
                    'title' => trim((string) ($_POST['title'] ?? '')),
                    'slug' => trim((string) ($_POST['slug'] ?? '')),
                    'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
                    'body' => trim((string) ($_POST['body'] ?? '')),
                ]);
                $successMessage = "Blog post '{$upPost['title']}' updated.";
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$posts = [];
if ($currentUser !== null) {
    $stmt = $db->prepare('SELECT * FROM `blog_posts` WHERE `author_user_id` = ? ORDER BY `created_at` DESC');
    $stmt->execute([$currentUser->id]);
    $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

$csrfToken = Csrf::generateToken();

View::render(
    'tutor-blog',
    [
        'posts' => $posts,
        'currentUser' => $currentUser,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Tutor Blog & Articles — AppTutors UK',
    'Author and submit educational articles and revision guides for Manager review and publication.'
);
