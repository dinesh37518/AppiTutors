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
use App\Services\NewsletterService;
use App\Support\Request;
use App\Support\Response;

$logger = new Logger();
$newsletterService = new NewsletterService();
$verifier = new FirebaseTokenVerifier();

try {
    $method = Request::getMethod();

    // 1. Strict Manager Authentication & Authority
    $manager = $verifier->authenticateRequest();
    Authorization::requireRole($manager, [Authorization::ROLE_MANAGER]);
    Authorization::requireActiveStatus($manager);

    if ($method === 'GET') {
        $result = $newsletterService->listSubscribers($manager, $_GET);
        $stats = $newsletterService->getSubscriberStats($manager);

        Response::success([
            'subscribers' => $result['items'],
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'stats' => $stats,
        ]);

    } elseif ($method === 'POST') {
        $body = Request::getJsonBody();

        $subscriberId = (int) ($body['subscriber_id'] ?? $body['id'] ?? 0);
        $newStatus = strtoupper(trim((string) ($body['status'] ?? '')));

        if ($subscriberId <= 0) {
            Response::error('Valid subscriber_id is required.', 'INVALID_PARAMS', 422);
        }

        if (empty($newStatus)) {
            Response::error('Target status is required.', 'INVALID_PARAMS', 422);
        }

        $subscriber = $newsletterService->updateSubscriberStatus($manager, $subscriberId, $newStatus);

        Response::success([
            'message' => 'Subscriber status updated successfully.',
            'subscriber' => $subscriber,
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
    $logger->error('Unexpected error in /api/manager/subscribers.php: ' . $e->getMessage());
    Response::error('Newsletter administration action could not be completed.', 'INTERNAL_ERROR', 500);
}
