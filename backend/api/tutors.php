<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\AuthenticationException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Authorization\ForbiddenException;
use App\Logging\Logger;
use App\Services\Exceptions\BookabilityException;
use App\Services\Exceptions\ValidationException;
use App\Services\TutorService;
use App\Support\Request;
use App\Support\Response;
use Throwable;

$logger = new Logger();
$tutorService = new TutorService();
$verifier = new FirebaseTokenVerifier();

try {
    $method = Request::getMethod();

    // Try extracting authenticated user if Authorization header is provided
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
            // If token invalid, reject immediately
            Response::error($e->getMessage(), 'INVALID_TOKEN', 401);
        }
    }

    if ($method === 'GET') {
        // Query parameters
        $requestedTutorId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_GET['tutor_id']) ? (int) $_GET['tutor_id'] : null);

        // If no ID specified and user is logged in as tutor, show own profile
        if ($requestedTutorId === null && $currentUser !== null && $currentUser->isTutor()) {
            $requestedTutorId = $currentUser->id;
        }

        if ($requestedTutorId === null) {
            Response::error('Tutor ID is required (?id=123).', 'INVALID_PARAMS', 400);
        }

        $profile = $tutorService->getProfile($requestedTutorId, $currentUser);
        Response::success(['tutor_profile' => $profile]);

    } elseif ($method === 'PUT') {
        // PUT requires authenticated TUTOR or MANAGER
        if ($currentUser === null) {
            Response::error('Authentication required to modify tutor profile.', 'UNAUTHENTICATED', 401);
        }

        $body = Request::getJsonBody();

        // Target tutor ID: Defaults to current user if tutor; or specified in body if manager
        $targetUserId = ($currentUser->isManager() && !empty($body['tutor_user_id']))
            ? (int) $body['tutor_user_id']
            : $currentUser->id;

        $updatedProfile = $tutorService->updateProfile($targetUserId, $body, $currentUser);
        Response::success(['tutor_profile' => $updatedProfile]);

    } else {
        Response::error('Method not allowed. Use GET or PUT.', 'METHOD_NOT_ALLOWED', 405);
    }

} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (BookabilityException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (Throwable $e) {
    $logger->error('Unexpected error in /api/tutors.php: ' . $e->getMessage());
    Response::error('An error occurred processing the tutor profile request.', 'INTERNAL_ERROR', 500);
}
