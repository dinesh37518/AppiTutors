<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\DbsService;
use App\Services\TutorService;
use App\Support\Csrf;
use App\Support\View;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$tutorService = new TutorService($db);
$dbsService = new DbsService($db);

$successMessage = '';
$errorMessage = '';

if (!empty($_GET['welcome'])) {
    $successMessage = 'Welcome to the AppTutors UK Educator Portal! Your academic qualifications and DBS credentials have been received and tutor access is active.';
}

// Determine active tutor context (from session, auth token, or test param)
$currentUserId = $_SESSION['user_id'] ?? (isset($_GET['id']) ? (int) $_GET['id'] : null);
$currentUser = null;

if ($currentUserId !== null) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $currentUserId]);
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Fallback: If no tutor specified, look up first tutor in DB for view demonstration
if ($currentUser === null) {
    $stmt = $db->query("SELECT u.* FROM users u JOIN tutor_profiles tp ON u.id = tp.user_id WHERE u.role = 'TUTOR' LIMIT 1");
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Handle Form Submissions (Profile Update or DBS Upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? $_POST['form_action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            try {
                $subjects = !empty($_POST['subjects']) ? array_map('trim', explode(',', (string)$_POST['subjects'])) : [];
                $updateData = [
                    'headline' => $_POST['headline'] ?? '',
                    'bio' => $_POST['bio'] ?? '',
                    'qualifications' => $_POST['qualifications'] ?? '',
                    'hourly_rate' => !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null,
                    'subjects' => $subjects,
                ];

                $tutorService->updateProfile($currentUser->id, $updateData, $currentUser);
                $successMessage = 'Profile updated successfully.';
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        } elseif ($action === 'submit_dbs') {
            try {
                $certNumber = $_POST['certificate_number'] ?? $_POST['dbs_certificate_number'] ?? '';
                $uploadedFile = $_FILES['dbs_document'] ?? $_FILES['dbs_file'] ?? null;
                $dbsService->submitDbs($currentUser->id, $certNumber, $uploadedFile, $currentUser);
                $successMessage = 'Enhanced DBS certificate submitted for managerial safeguarding review.';
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }
    }
}

$profile = null;
$dbsMeta = null;
if ($currentUser !== null) {
    try {
        $profile = $tutorService->getProfile($currentUser->id, $currentUser);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }

    $metaPath = dirname(__DIR__, 2) . "/storage/private/dbs/meta_{$currentUser->id}.json";
    if (file_exists($metaPath)) {
        $dbsMeta = json_decode((string) file_get_contents($metaPath), true) ?: null;
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'tutor-profile',
    [
        'profile' => $profile,
        'currentUser' => $currentUser,
        'dbsMeta' => $dbsMeta,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Tutor Profile & Safeguarding — AppTutors UK',
    'Manage your teaching profile, qualifications, and DBS safeguarding credentials.'
);
