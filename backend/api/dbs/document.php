<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\AuthenticationException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\Exceptions\UserNotRegisteredException;
use App\Auth\FirebaseTokenVerifier;
use App\Authorization\ForbiddenException;
use App\Logging\Logger;
use App\Services\DbsService;
use App\Services\Exceptions\ValidationException;
use App\Support\Response;
use Throwable;

$logger = new Logger();
$dbsService = new DbsService();
$verifier = new FirebaseTokenVerifier();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::error('Method not allowed. Use GET.', 'METHOD_NOT_ALLOWED', 405);
    }

    $currentUser = $verifier->authenticateRequest(null, false);

    $tutorUserId = 0;
    if (isset($_GET['tutor_user_id'])) {
        $tutorUserId = (int) $_GET['tutor_user_id'];
    } elseif (isset($_GET['tutor_id'])) {
        $tutorUserId = (int) $_GET['tutor_id'];
    }

    if ($tutorUserId <= 0) {
        Response::error('Invalid or missing tutor ID.', 'INVALID_PARAMETERS', 422);
    }

    $doc = $dbsService->getDbsDocument($tutorUserId, $currentUser);

    if (!headers_sent()) {
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: inline; filename="' . $doc['original_name'] . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Content-Length: ' . filesize($doc['path']));
    }

    readfile($doc['path']);
    exit;

} catch (InvalidTokenException | AuthenticationException $e) {
    Response::error($e->getMessage(), 'UNAUTHENTICATED', 401);
} catch (UserNotRegisteredException $e) {
    Response::error($e->getMessage(), 'USER_NOT_REGISTERED', 404);
} catch (AccountInactiveException $e) {
    Response::error($e->getMessage(), 'ACCOUNT_INACTIVE', 403);
} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode());
} catch (Throwable $e) {
    $logger->error('Unexpected error in /api/dbs/document.php: ' . $e->getMessage());
    Response::error('Failed to retrieve DBS document.', 'INTERNAL_ERROR', 500);
}
