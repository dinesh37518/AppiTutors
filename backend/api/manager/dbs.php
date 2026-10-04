<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AuthenticationException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Logging\Logger;
use App\Services\DbsService;
use App\Services\Exceptions\ValidationException;
use App\Services\ManagerService;
use App\Support\Request;
use App\Support\Response;

$logger = new Logger();
$dbsService = new DbsService();
$managerService = new ManagerService();
$verifier = new FirebaseTokenVerifier();

try {
    $method = Request::getMethod();

    // 1. Strict Manager Authentication & Authority
    $manager = $verifier->authenticateRequest();
    Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
    Authorization::requireActiveStatus($manager);

    if ($method === 'GET') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $sortBy = (string) ($_GET['sort_by'] ?? 'updated_at');
        $sortDir = (string) ($_GET['sort_dir'] ?? 'DESC');

        $result = $managerService->listDbsApplications($manager, $_GET, $page, $perPage, $sortBy, $sortDir);
        Response::success($result);

    } elseif ($method === 'POST') {
        $body = Request::getJsonBody();

        $tutorUserId = (int) ($body['tutor_user_id'] ?? 0);
        $decision = (string) ($body['decision'] ?? '');
        $notes = isset($body['notes']) ? (string) $body['notes'] : null;

        if ($tutorUserId <= 0) {
            Response::error('Valid tutor_user_id is required.', 'INVALID_PARAMS', 422);
        }

        $result = $dbsService->managerReviewDbs($tutorUserId, $decision, $notes, $manager);
        Response::success([
            'message' => "DBS decision '{$decision}' recorded successfully.",
            'dbs_review' => $result,
        ]);

    } else {
        Response::error('Method not allowed. Use GET or POST.', 'METHOD_NOT_ALLOWED', 405);
    }

} catch (InvalidTokenException | AuthenticationException $e) {
    Response::error($e->getMessage(), 'UNAUTHENTICATED', 401);
} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (Throwable $e) {
    $logger->error('Unexpected error in /api/manager/dbs.php: ' . $e->getMessage());
    Response::error('Manager DBS action could not be completed.', 'INTERNAL_ERROR', 500);
}
