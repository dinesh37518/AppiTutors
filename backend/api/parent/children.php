<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Auth\UserContext;
use App\Authorization\ForbiddenException;
use App\Database\Database;
use App\Logging\Logger;
use App\Services\Exceptions\ValidationException;
use App\Services\StudentParentService;
use App\Support\Request;
use App\Support\Response;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logger = new Logger();
$studentParentService = new StudentParentService();
$verifier = new FirebaseTokenVerifier();

try {
    $method = Request::getMethod();
    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
        Response::error('Method not allowed. Use GET, POST, PUT, or DELETE.', 'METHOD_NOT_ALLOWED', 405);
    }

    // 1. Resolve Authenticated User Context (Bearer Token preferred, fallback to active session)
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
        Response::error('Authentication required to manage child records.', 'UNAUTHENTICATED', 401);
    }

    // 2. Handle HTTP Methods
    if ($method === 'GET') {
        if (isset($_GET['id']) && trim((string) $_GET['id']) !== '') {
            $childId = (int) $_GET['id'];
            $child = $studentParentService->getChild($childId, $currentUser);
            Response::success(['child' => $child]);
        } else {
            $targetParentId = ($currentUser->isManager() && isset($_GET['parent_id']))
                ? (int) $_GET['parent_id']
                : $currentUser->id;

            $children = $studentParentService->getChildren($targetParentId, $currentUser);
            Response::success(['children' => $children]);
        }

    } elseif ($method === 'POST') {
        $body = Request::getJsonBody(true);

        $targetParentId = ($currentUser->isManager() && !empty($body['parent_user_id']))
            ? (int) $body['parent_user_id']
            : $currentUser->id;

        $child = $studentParentService->createChild($targetParentId, $body, $currentUser);
        Response::success(['child' => $child], 201);

    } elseif ($method === 'PUT') {
        $body = Request::getJsonBody();

        $childId = isset($_GET['id'])
            ? (int) $_GET['id']
            : (int) ($body['id'] ?? $body['child_id'] ?? 0);

        if ($childId <= 0) {
            Response::error('Valid Child ID is required for update.', 'VALIDATION_ERROR', 422, ['id' => 'Required']);
        }

        $updatedChild = $studentParentService->updateChild($childId, $body, $currentUser);
        Response::success(['child' => $updatedChild]);

    } elseif ($method === 'DELETE') {
        $body = Request::getJsonBody();

        $childId = isset($_GET['id'])
            ? (int) $_GET['id']
            : (int) ($body['id'] ?? $body['child_id'] ?? 0);

        if ($childId <= 0) {
            Response::error('Valid Child ID is required for deletion.', 'VALIDATION_ERROR', 422, ['id' => 'Required']);
        }

        $studentParentService->deleteChild($childId, $currentUser, true);
        Response::success(['message' => 'Child record removed successfully.']);

    } else {
        Response::error('Method not allowed. Use GET, POST, PUT, or DELETE.', 'METHOD_NOT_ALLOWED', 405);
    }

} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (\Throwable $e) {
    $logger->error('Unexpected error in /api/parent/children.php: ' . $e->getMessage());
    Response::error('An error occurred processing the child record request.', 'INTERNAL_ERROR', 500);
}
