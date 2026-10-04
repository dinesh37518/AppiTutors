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
use App\Services\TutorService;
use App\Support\Request;
use App\Support\Response;

$logger = new Logger();
$tutorService = new TutorService();
$managerService = new ManagerService();
$verifier = new FirebaseTokenVerifier();

try {
    $method = Request::getMethod();

    // 1. Strict Manager Authentication & Authority
    $manager = $verifier->authenticateRequest();
    Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
    Authorization::requireActiveStatus($manager);

    if ($method === 'GET') {
        $tutorId = (int) ($_GET['id'] ?? $_GET['tutor_id'] ?? 0);

        if ($tutorId > 0) {
            $tutor = $managerService->getTutorDetails($tutorId, $manager);
            Response::success(['tutor' => $tutor]);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $sortBy = (string) ($_GET['sort_by'] ?? 'created_at');
        $sortDir = (string) ($_GET['sort_dir'] ?? 'DESC');

        $result = $managerService->listTutors($manager, $_GET, $page, $perPage, $sortBy, $sortDir);

        Response::success([
            'tutors' => $result['items'],
            'items' => $result['items'],
            'pagination' => $result['pagination'],
        ]);

    } elseif ($method === 'POST') {
        $body = Request::getJsonBody();

        $tutorUserId = (int) ($body['tutor_user_id'] ?? 0);
        $action = strtoupper(trim((string) ($body['action'] ?? '')));
        $reason = trim((string) ($body['reason'] ?? ''));

        if ($tutorUserId <= 0) {
            Response::error('Valid tutor_user_id is required.', 'INVALID_PARAMS', 422);
        }

        switch ($action) {
            case 'APPROVE':
                $result = $tutorService->managerApproveTutor($tutorUserId, $manager);
                Response::success(['message' => 'Tutor successfully approved and activated.', 'tutor' => $result]);
                break;

            case 'REJECT':
                $result = $tutorService->managerRejectTutor($tutorUserId, $reason, $manager);
                Response::success(['message' => 'Tutor application rejected.', 'tutor' => $result]);
                break;

            case 'SUSPEND':
                $result = $tutorService->managerSuspendTutor($tutorUserId, $reason, $manager);
                Response::success(['message' => 'Tutor account suspended.', 'tutor' => $result]);
                break;

            case 'REINSTATE':
            case 'RESTORE':
                $result = $tutorService->managerReinstateTutor($tutorUserId, $manager);
                Response::success(['message' => 'Tutor account reinstated to active/approved.', 'tutor' => $result]);
                break;

            default:
                Response::error("Unknown action '{$action}'. Allowed actions: APPROVE, REJECT, SUSPEND, REINSTATE.", 'INVALID_ACTION', 422);
        }

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
    $logger->error('Unexpected error in /api/manager/tutors.php: ' . $e->getMessage());
    Response::error('Manager action could not be completed.', 'INTERNAL_ERROR', 500);
}
