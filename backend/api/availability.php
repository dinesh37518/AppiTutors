<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/bootstrap.php';

use App\Auth\FirebaseTokenVerifier;
use App\Authorization\ForbiddenException;
use App\Logging\Logger;
use App\Services\AvailabilityService;
use App\Services\Exceptions\BookabilityException;
use App\Services\Exceptions\OverlapException;
use App\Services\Exceptions\ValidationException;
use App\Support\Response;
use App\Support\Request;
use Throwable;

$logger = new Logger();
$availabilityService = new AvailabilityService();
$verifier = new FirebaseTokenVerifier();

try {
    $method = Request::getMethod();

    // Authenticate user for write requests or self-lookup
    $currentUser = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!empty($authHeader)) {
        try {
            $currentUser = $verifier->authenticateRequest(null, false);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 'INVALID_TOKEN', 401);
        }
    }

    if ($method === 'GET') {
        $requestedTutorId = isset($_GET['tutor_id']) ? (int) $_GET['tutor_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : null);
        if ($requestedTutorId === null && $currentUser !== null && $currentUser->isTutor()) {
            $requestedTutorId = $currentUser->id;
        }

        if ($requestedTutorId === null) {
            Response::error('Tutor ID is required (?tutor_id=123).', 'INVALID_PARAMS', 400);
        }

        $from = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $to = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $tz = isset($_GET['timezone']) ? (string) $_GET['timezone'] : 'Europe/London';

        $slots = $availabilityService->getTutorSlots($requestedTutorId, $from, $to, $tz);
        Response::success(['slots' => $slots]);

    } elseif ($method === 'POST') {
        if ($currentUser === null) {
            Response::error('Authentication required to create availability slots.', 'UNAUTHENTICATED', 401);
        }

        $body = Request::getJsonBody();

        $targetTutorId = ($currentUser->isManager() && !empty($body['tutor_user_id']))
            ? (int) $body['tutor_user_id']
            : $currentUser->id;

        $startsAt = (string) ($body['starts_at'] ?? '');
        $endsAt = (string) ($body['ends_at'] ?? '');
        $timezone = (string) ($body['timezone'] ?? 'Europe/London');
        $status = (string) ($body['status'] ?? 'PUBLISHED');

        $slot = $availabilityService->createSlot($targetTutorId, $startsAt, $endsAt, $timezone, $status, $currentUser);
        Response::success(['slot' => $slot], 201);

    } elseif ($method === 'PUT') {
        if ($currentUser === null) {
            Response::error('Authentication required to modify availability slots.', 'UNAUTHENTICATED', 401);
        }

        $body = Request::getJsonBody();

        $slotId = (int) ($body['id'] ?? ($_GET['id'] ?? 0));
        if ($slotId <= 0) {
            Response::error('Valid slot ID is required.', 'INVALID_PARAMS', 400);
        }

        $targetTutorId = ($currentUser->isManager() && !empty($body['tutor_user_id']))
            ? (int) $body['tutor_user_id']
            : $currentUser->id;

        $startsAt = (string) ($body['starts_at'] ?? '');
        $endsAt = (string) ($body['ends_at'] ?? '');
        $timezone = (string) ($body['timezone'] ?? 'Europe/London');
        $status = isset($body['status']) ? (string) $body['status'] : null;

        $updated = $availabilityService->updateSlot($slotId, $targetTutorId, $startsAt, $endsAt, $timezone, $status, $currentUser);
        Response::success(['slot' => $updated]);

    } elseif ($method === 'DELETE') {
        if ($currentUser === null) {
            Response::error('Authentication required to delete availability slots.', 'UNAUTHENTICATED', 401);
        }

        $slotId = (int) ($_GET['id'] ?? 0);
        if ($slotId <= 0) {
            $body = Request::getJsonBody();
            $slotId = (int) ($body['id'] ?? 0);
        }

        if ($slotId <= 0) {
            Response::error('Valid slot ID is required (?id=45).', 'INVALID_PARAMS', 400);
        }

        $targetTutorId = ($currentUser->isManager() && !empty($_GET['tutor_user_id']))
            ? (int) $_GET['tutor_user_id']
            : $currentUser->id;

        $availabilityService->deleteSlot($slotId, $targetTutorId, $currentUser);
        Response::success(['deleted' => true, 'slot_id' => $slotId]);

    } else {
        Response::error('Method not allowed. Use GET, POST, PUT, or DELETE.', 'METHOD_NOT_ALLOWED', 405);
    }

} catch (OverlapException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 409);
} catch (BookabilityException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (Throwable $e) {
    $logger->error('Unexpected error in /api/availability.php: ' . $e->getMessage());
    Response::error('An unexpected error occurred processing availability.', 'INTERNAL_ERROR', 500);
}
