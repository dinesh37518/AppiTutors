<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Auth\UserContext;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Logging\Logger;
use App\Services\BookingService;
use App\Services\Exceptions\BookabilityException;
use App\Services\Exceptions\ValidationException;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logger = new Logger();
$bookingService = new BookingService();
$verifier = new FirebaseTokenVerifier();

try {
    $method = Request::getMethod();

    // 1. Enforce Allowed HTTP Methods
    if (!in_array($method, ['GET', 'POST', 'PATCH', 'PUT'], true)) {
        Response::error('Method not allowed. Use GET, POST, PATCH, or PUT.', 'METHOD_NOT_ALLOWED', 405);
    }

    // 2. Resolve Authenticated User Context (Bearer Token preferred, fallback to active session)
    $currentUser = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!empty($authHeader)) {
        try {
            $currentUser = $verifier->authenticateRequest(null, true);
        } catch (InvalidTokenException $e) {
            Response::error($e->getMessage(), 'INVALID_TOKEN', 401);
        } catch (AccountInactiveException $e) {
            Response::error($e->getMessage(), 'ACCOUNT_INACTIVE', 403);
        }
    } elseif (!empty($_SESSION['user_id'])) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, firebase_uid, email, display_name, role, status FROM `users` WHERE `id` = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $currentUser = UserContext::fromDatabaseRow($row);
            if (!$currentUser->isActive()) {
                Response::error('User account is currently inactive.', 'ACCOUNT_INACTIVE', 403);
            }
        }
    }

    if ($currentUser === null) {
        Response::error('Authentication required to access booking engine.', 'UNAUTHENTICATED', 401);
    }

    // 3. Dispatch HTTP Methods
    if ($method === 'GET') {
        if (isset($_GET['id']) && trim((string) $_GET['id']) !== '') {
            $bookingId = (int) $_GET['id'];
            $booking = $bookingService->getBooking($bookingId, $currentUser);
            Response::success(['booking' => $booking]);
        } else {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'tutor_id' => $_GET['tutor_id'] ?? null,
                'student_id' => $_GET['student_id'] ?? null,
            ];
            $bookings = $bookingService->listBookings($currentUser, $filters);
            Response::success(['bookings' => $bookings]);
        }

    } elseif ($method === 'POST') {
        RateLimiter::enforce('booking_create', null, 60, 60);
        $body = Request::getJsonBody(true);

        $booking = $bookingService->createBooking($body, $currentUser);
        Response::success(['booking' => $booking], 201);

    } elseif ($method === 'PATCH' || $method === 'PUT') {
        $body = Request::getJsonBody();

        $bookingId = isset($_GET['id'])
            ? (int) $_GET['id']
            : (int) ($body['id'] ?? $body['booking_id'] ?? 0);

        if ($bookingId <= 0) {
            Response::error('Valid Booking ID is required.', 'VALIDATION_ERROR', 422, ['id' => 'Required']);
        }

        $transition = strtoupper(trim((string) ($body['status'] ?? $body['action'] ?? '')));
        $reason = !empty($body['reason']) ? trim((string) $body['reason']) : null;

        if ($transition === BookingService::STATUS_CONFIRMED) {
            $updated = $bookingService->confirmBooking($bookingId, $currentUser, $reason);
        } elseif ($transition === BookingService::STATUS_REJECTED) {
            $updated = $bookingService->rejectBooking($bookingId, $currentUser, $reason);
        } elseif ($transition === BookingService::STATUS_CANCELLED) {
            $updated = $bookingService->cancelBooking($bookingId, $currentUser, $reason);
        } else {
            throw new ValidationException(
                "Unsupported booking transition: '{$transition}'. Supported transitions: CONFIRMED, REJECTED, CANCELLED.",
                'INVALID_STATE_TRANSITION',
                422
            );
        }

        Response::success(['booking' => $updated]);
    }

} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (BookabilityException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (\Throwable $e) {
    $logger->error('Unexpected error in /api/bookings.php: ' . $e->getMessage());
    Response::error('An error occurred processing the booking request.', 'INTERNAL_ERROR', 500);
}
