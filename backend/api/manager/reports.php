<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AuthenticationException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Authorization\Authorization;
use App\Authorization\ForbiddenException;
use App\Logging\Logger;
use App\Services\Exceptions\ValidationException;
use App\Services\ManagerService;
use App\Support\Response;

$logger = new Logger();
$managerService = new ManagerService();
$verifier = new FirebaseTokenVerifier();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        Response::error('Method not allowed. Use GET.', 'METHOD_NOT_ALLOWED', 405);
    }

    // 1. Strict Server-Side Manager Authentication & Role Authority
    $manager = $verifier->authenticateRequest();
    Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
    Authorization::requireActiveStatus($manager);

    $reports = $managerService->getReports($manager);

    Response::success($reports);

} catch (InvalidTokenException | AuthenticationException $e) {
    Response::error($e->getMessage(), 'UNAUTHENTICATED', 401);
} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (Throwable $e) {
    $logger->error('Error in /api/manager/reports.php: ' . $e->getMessage());
    Response::error('Failed to generate operational reports.', 'INTERNAL_ERROR', 500);
}
