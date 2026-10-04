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

// Determine active student/parent context (from session, auth token, or query param)
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

// Fallback: If no student specified, look up first student/parent in DB for demonstration
if ($currentUser === null) {
    $stmt = $db->query("SELECT * FROM `users` WHERE `role` = 'STUDENT_PARENT' LIMIT 1");
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Handle Form Submissions (Profile Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh the page and try again.';
    } else {
        try {
            $updateData = [
                'display_name' => $_POST['display_name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'postcode' => $_POST['postcode'] ?? '',
            ];

            $studentParentService->updateProfile($currentUser->id, $updateData, $currentUser);
            $successMessage = 'Profile updated successfully.';
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$profile = null;
if ($currentUser !== null) {
    try {
        $profile = $studentParentService->getProfile($currentUser->id, $currentUser);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'student-profile',
    [
        'profile' => $profile,
        'currentUser' => $currentUser,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Student & Parent Profile — AppTutors UK',
    'Manage your student/parent contact information and preferences for UK 1-to-1 tutoring.'
);
