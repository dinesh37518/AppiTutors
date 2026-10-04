<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/bootstrap.php';

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed. Use POST.', 'METHOD_NOT_ALLOWED', 405);
    }

    $currentUser = $verifier->authenticateRequest(null, false);

    $metadata = [
        'certificate_number' => $_POST['certificate_number'] ?? '',
        'issue_date' => $_POST['issue_date'] ?? '',
    ];

    $file = $_FILES['dbs_document'] ?? null;

    $targetUserId = ($currentUser->isManager() && !empty($_POST['tutor_user_id']))
        ? (int) $_POST['tutor_user_id']
        : $currentUser->id;

    $result = $dbsService->submitDbs($targetUserId, $metadata, $file, $currentUser);
    Response::success([
        'message' => 'DBS documentation submitted successfully and is awaiting review.',
        'dbs' => $result,
    ], 201);

} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (Throwable $e) {
    $logger->error('Unexpected error in /api/dbs/upload.php: ' . $e->getMessage());
    Response::error('DBS submission could not be completed.', 'INTERNAL_ERROR', 500);
}
