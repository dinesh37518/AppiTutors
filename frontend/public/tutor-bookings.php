<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\BookingService;
use App\Support\Csrf;
use App\Support\View;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$bookingService = new BookingService($db);

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

// Fallback: If no tutor specified, look up first active approved tutor in DB for demonstration
if ($currentUser === null) {
    $stmt = $db->query("
        SELECT u.* 
        FROM `users` u 
        JOIN `tutor_profiles` tp ON u.id = tp.user_id 
        WHERE u.role = 'TUTOR' 
          AND tp.approval_status = 'APPROVED' 
          AND tp.dbs_status = 'VERIFIED' 
        LIMIT 1
    ");
    $userRow = $stmt->fetch();
    if ($userRow) {
        $currentUser = UserContext::fromDatabaseRow($userRow);
    }
}

// Handle Form Submissions (Confirm or Reject Booking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $bookingId = (int) ($_POST['booking_id'] ?? 0);

        if ($action === 'confirm_booking') {
            try {
                $meetingLink = !empty($_POST['meeting_link']) ? trim((string) $_POST['meeting_link']) : null;
                $bookingService->confirmBooking($bookingId, $currentUser, 'Accepted by tutor via portal', $meetingLink);
                $successMessage = "Booking #{$bookingId} accepted and confirmed.";
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        } elseif ($action === 'reject_booking') {
            try {
                $reason = !empty($_POST['reason']) ? trim((string) $_POST['reason']) : 'Declined by tutor';
                $bookingService->rejectBooking($bookingId, $currentUser, $reason);
                $successMessage = "Booking #{$bookingId} declined. The availability slot has been reopened.";
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        } elseif ($action === 'save_notes') {
            try {
                $notes = trim((string) ($_POST['notes'] ?? ''));
                $visibility = (string) ($_POST['visibility'] ?? 'PARENT_VISIBLE');
                $notesService = new \App\Services\LessonNotesService($db);
                $notesService->createNote($bookingId, $notes, $visibility, $currentUser);
                $successMessage = "Lesson notes saved for Booking #{$bookingId}.";
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }
    }
}

$bookings = [];
$bookingNotes = [];
if ($currentUser !== null) {
    try {
        $bookings = $bookingService->listBookings($currentUser);
        $notesService = new \App\Services\LessonNotesService($db);
        foreach ($bookings as $b) {
            $bookingNotes[$b['id']] = $notesService->getNotesForBooking((int)$b['id'], $currentUser);
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'tutor-bookings',
    [
        'bookings' => $bookings,
        'bookingNotes' => $bookingNotes,
        'currentUser' => $currentUser,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Lesson Requests & Bookings — AppTutors UK',
    'Manage student lesson inquiries, accept bookings, and view your schedule.'
);
