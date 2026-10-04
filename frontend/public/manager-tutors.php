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

$filter = $_GET['status'] ?? 'ALL';

// Handle Manager Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null && $currentUser->isManager()) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh and try again.';
    } else {
        $tutorId = (int)($_POST['tutor_id'] ?? 0);
        $workflow = $_POST['workflow'] ?? 'APPROVAL';

        if ($workflow === 'DBS') {
            $dbsAction = strtoupper(trim($_POST['dbs_action'] ?? ''));
            try {
                if ($dbsAction === 'VERIFY') {
                    $dbsService->verifyDbs($tutorId, $currentUser);
                    $successMessage = "DBS certificate verified successfully for Tutor #{$tutorId}.";
                } elseif ($dbsAction === 'REJECT') {
                    $reason = $_POST['reason'] ?? 'DBS credentials rejected by manager review.';
                    $dbsService->rejectDbs($tutorId, $reason, $currentUser);
                    $successMessage = "DBS certificate rejected for Tutor #{$tutorId}.";
                }
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $action = strtoupper(trim($_POST['action'] ?? ''));
            try {
                if ($action === 'APPROVE') {
                    $tutorService->managerApproveTutor($tutorId, $currentUser);
                    $successMessage = "Tutor #{$tutorId} approved successfully.";
                } elseif ($action === 'REJECT') {
                    $reason = $_POST['reason'] ?? 'Application does not meet platform criteria.';
                    $tutorService->managerRejectTutor($tutorId, $reason, $currentUser);
                    $successMessage = "Tutor #{$tutorId} application rejected.";
                } elseif ($action === 'SUSPEND') {
                    $reason = $_POST['reason'] ?? 'Manager administrative suspension.';
                    $tutorService->managerSuspendTutor($tutorId, $reason, $currentUser);
                    $successMessage = "Tutor #{$tutorId} suspended.";
                } elseif ($action === 'REINSTATE') {
                    $tutorService->managerReinstateTutor($tutorId, $currentUser);
                    $successMessage = "Tutor #{$tutorId} reinstated.";
                }
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }
    }
}

// Fetch tutors according to filter if manager context available
$statusParam = ($filter === 'ALL') ? null : $filter;
$tutors = [];
if ($currentUser !== null && $currentUser->isManager()) {
    try {
        $tutors = $tutorService->listTutorsForManager($currentUser, $statusParam);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'manager-tutors',
    [
        'tutors' => $tutors,
        'currentUser' => $currentUser,
        'filter' => $filter,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Tutor Safeguarding & Governance — AppTutors UK',
    'Managerial review queue for tutor onboarding, DBS verification, and account status controls.'
);
