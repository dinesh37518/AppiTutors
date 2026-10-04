<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\StudentParentService;
use App\Support\Csrf;
use App\Support\View;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$studentParentService = new StudentParentService($db);

$successMessage = '';
$errorMessage = '';

// Determine active parent context (from session, auth token, or query param)
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

// Fallback: If no parent specified, look up first student/parent in DB for demonstration
if ($currentUser === null) {
    $stmt = $db->query("SELECT * FROM `users` WHERE `role` = 'STUDENT_PARENT' LIMIT 1");
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Handle Form Submissions (Add or Delete Child)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_child') {
            try {
                $childData = [
                    'first_name' => $_POST['first_name'] ?? '',
                    'last_name' => $_POST['last_name'] ?? '',
                    'date_of_birth' => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
                    'school_year' => $_POST['school_year'] ?? '',
                    'curriculum' => $_POST['curriculum'] ?? '',
                ];

                $studentParentService->createChild($currentUser->id, $childData, $currentUser);
                $successMessage = 'Child profile added successfully.';
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        } elseif ($action === 'delete_child') {
            try {
                $childId = (int) ($_POST['child_id'] ?? 0);
                $studentParentService->deleteChild($childId, $currentUser, true);
                $successMessage = 'Child profile removed successfully.';
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }
    }
}

$children = [];
if ($currentUser !== null) {
    try {
        $children = $studentParentService->getChildren($currentUser->id, $currentUser, true);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'parent-children',
    [
        'children' => $children,
        'currentUser' => $currentUser,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Children & Dependents — AppTutors UK',
    'Manage educational profiles for your children under UK curriculum standards.'
);
