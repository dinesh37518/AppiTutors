<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/bootstrap.php';

use App\Auth\Exceptions\AccountInactiveException;
use App\Auth\Exceptions\InvalidTokenException;
use App\Auth\FirebaseTokenVerifier;
use App\Authorization\ForbiddenException;
use App\Logging\Logger;
use App\Services\Exceptions\ValidationException;
use App\Services\PrivacyService;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;
use Throwable;

$logger = new Logger();
$privacyService = new PrivacyService();
$verifier = new FirebaseTokenVerifier();

try {
    RateLimiter::enforce('privacy_api', null, 30, 60);

    $method = Request::getMethod();
    if (!in_array($method, ['GET', 'POST'], true)) {
        Response::error('Method not allowed. Use GET or POST.', 'METHOD_NOT_ALLOWED', 405);
    }

    // Authenticate user strictly via Firebase Bearer Token
    $currentUser = $verifier->authenticateRequest();

    if ($method === 'GET') {
        $action = strtoupper(trim((string) ($_GET['action'] ?? 'EXPORT')));
        if ($action !== 'EXPORT') {
            Response::error("Unsupported GET action '{$action}'. Supported actions: EXPORT.", 'INVALID_ACTION', 422);
        }

        $targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $currentUser->id;
        $exportData = $privacyService->exportUserData($targetUserId, $currentUser);

        Response::success([
            'message' => 'Personal data export package generated successfully.',
            'export' => $exportData,
        ]);

    } elseif ($method === 'POST') {
        $body = Request::getJsonBody();
        $action = strtoupper(trim((string) ($body['action'] ?? '')));

        if ($action === 'ERASE') {
            $targetUserId = isset($body['user_id']) ? (int) $body['user_id'] : $currentUser->id;
            $result = $privacyService->prepareAccountErasure($targetUserId, $currentUser);

            if (!empty($result['eligible'])) {
                Response::success($result, 200);
            } else {
                Response::error(
                    $result['reason'] ?? 'Account erasure cannot proceed at this time.',
                    'ERASURE_INELIGIBLE',
                    422,
                    $result
                );
            }

        } elseif ($action === 'CONSENT') {
            $consentType = trim((string) ($body['consent_type'] ?? 'general_marketing'));
            $granted = (bool) ($body['granted'] ?? true);
            $targetUserId = isset($body['user_id']) ? (int) $body['user_id'] : $currentUser->id;

            $privacyService->recordConsent($targetUserId, $consentType, $granted, $currentUser);
            Response::success([
                'message' => 'Privacy consent preference recorded successfully.',
                'consent_type' => $consentType,
                'granted' => $granted,
            ]);

        } else {
            Response::error(
                "Unknown privacy action '{$action}'. Allowed actions: ERASE, CONSENT.",
                'INVALID_ACTION',
                422
            );
        }
    }

} catch (InvalidTokenException $e) {
    Response::error($e->getMessage(), 'UNAUTHENTICATED', 401);
} catch (AccountInactiveException $e) {
    Response::error($e->getMessage(), 'ACCOUNT_INACTIVE', 403);
} catch (ForbiddenException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 403);
} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (Throwable $e) {
    $logger->error('Unexpected error in /api/privacy.php: ' . $e->getMessage());
    Response::error('Privacy request could not be processed.', 'INTERNAL_ERROR', 500);
}
