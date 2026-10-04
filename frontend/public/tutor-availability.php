<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\AvailabilityService;
use App\Services\TutorService;
use App\Support\Csrf;
use App\Support\View;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$tutorService = new TutorService($db);
$availabilityService = new AvailabilityService($db);

$successMessage = '';
$errorMessage = '';

// Determine active tutor context
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

$isBookable = false;
$slots = [];

if ($currentUser !== null) {
    $isBookable = $tutorService->isBookable($currentUser->id);

    // Handle Slot Operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Csrf::validateToken($csrfToken)) {
            $errorMessage = 'CSRF validation failed. Please refresh and try again.';
        } else {
            $action = $_POST['action'] ?? 'create';

            if ($action === 'create') {
                try {
                    $startsAt = (string)($_POST['starts_at'] ?? '');
                    $endsAt = (string)($_POST['ends_at'] ?? '');
                    $availabilityService->createSlot($currentUser->id, $startsAt, $endsAt, $currentUser);
                    $successMessage = 'Availability slot published successfully.';
                } catch (Throwable $e) {
                    $errorMessage = $e->getMessage();
                }
            } elseif ($action === 'delete') {
                try {
                    $slotId = (int)($_POST['slot_id'] ?? 0);
                    $availabilityService->deleteSlot($slotId, $currentUser);
                    $successMessage = 'Availability slot removed successfully.';
                } catch (Throwable $e) {
                    $errorMessage = $e->getMessage();
                }
            }
        }
    }

    try {
        $slots = $availabilityService->getSlotsForTutor($currentUser->id, $currentUser);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'tutor-availability',
    [
        'slots' => $slots,
        'currentUser' => $currentUser,
        'isBookable' => $isBookable,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Tutor Availability Calendar — AppTutors UK',
    'Manage your weekly teaching schedule and discrete availability windows.'
);
