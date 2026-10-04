<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Auth\UserContext;
use App\Database\Database;
use App\Services\BookingService;
use App\Services\StudentParentService;
use App\Services\TutorService;
use App\Support\Csrf;
use App\Support\View;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$bookingService = new BookingService($db);
$studentParentService = new StudentParentService($db);
$tutorService = new TutorService($db);

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

// Load Approved & Bookable Tutors
$stmtTutors = $db->query("
    SELECT u.id, u.display_name, tp.headline 
    FROM `users` u 
    JOIN `tutor_profiles` tp ON u.id = tp.user_id 
    WHERE u.status = 'ACTIVE' 
      AND tp.approval_status = 'APPROVED' 
      AND tp.dbs_status = 'VERIFIED'
    ORDER BY u.display_name ASC
");
$tutors = $stmtTutors->fetchAll(PDO::FETCH_ASSOC);

$selectedTutorId = isset($_GET['tutor_id']) ? (int) $_GET['tutor_id'] : (isset($_POST['tutor_user_id']) ? (int) $_POST['tutor_user_id'] : 0);
if ($selectedTutorId <= 0 && !empty($tutors)) {
    $selectedTutorId = (int) $tutors[0]['id'];
}

// Load Available Slots for Selected Tutor
$availableSlots = [];
if ($selectedTutorId > 0) {
    $availableSlots = $bookingService->getAvailableSlotsForTutor($selectedTutorId);
}

// Load Children for the Active Parent
$children = [];
if ($currentUser !== null) {
    try {
        $children = $studentParentService->getChildren($currentUser->id, $currentUser);
    } catch (Throwable $e) {
        // Non-fatal if parent has no children
        $children = [];
    }
}

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser !== null) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Csrf::validateToken($csrfToken)) {
        $errorMessage = 'CSRF validation failed. Please refresh the page and try again.';
    } else {
        try {
            $booking = $bookingService->createBooking($_POST, $currentUser);
            $successMessage = "Booking request submitted successfully! Booking ID #{$booking['id']} is currently PENDING confirmation from {$booking['tutor_name']}.";
            // Refresh slots after booking
            if ($selectedTutorId > 0) {
                $availableSlots = $bookingService->getAvailableSlotsForTutor($selectedTutorId);
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$csrfToken = Csrf::generateToken();

View::render(
    'book-session',
    [
        'tutors' => $tutors,
        'selectedTutorId' => $selectedTutorId,
        'availableSlots' => $availableSlots,
        'children' => $children,
        'currentUser' => $currentUser,
        'csrfToken' => $csrfToken,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
    ],
    'Book a Session — AppTutors UK',
    'Select a verified UK tutor and reserve an availability slot for 1-to-1 tutoring.'
);
