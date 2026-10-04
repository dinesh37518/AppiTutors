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

// Handle Form Submissions (Cancel Booking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'cancel_booking') {
            try {
                $bookingId = (int) ($_POST['booking_id'] ?? 0);
                $bookingService->cancelBooking($bookingId, $currentUser, 'Cancelled by user from portal');
                $successMessage = 'Booking request cancelled successfully.';
            } catch (Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }
    }
}

$bookings = [];
if ($currentUser !== null) {
    try {
        $bookings = $bookingService->listBookings($currentUser);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'student-bookings',
    [
        'bookings' => $bookings,
        'currentUser' => $currentUser,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'My Bookings & Lesson Requests — AppTutors UK',
    'Review your requested and confirmed 1-to-1 tutoring sessions with verified UK educators.'
);
