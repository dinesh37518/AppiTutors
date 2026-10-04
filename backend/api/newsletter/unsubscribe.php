<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/bootstrap.php';

use App\Logging\Logger;
use App\Services\Exceptions\ValidationException;
use App\Services\NewsletterService;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;

$logger = new Logger();
$newsletterService = new NewsletterService();

try {
    RateLimiter::enforce('newsletter_unsubscribe', null, 60, 60);

    $method = Request::getMethod();
    if (!in_array($method, ['GET', 'POST'], true)) {
        Response::error('Method not allowed. Use GET or POST.', 'METHOD_NOT_ALLOWED', 405);
    }

    $token = '';
    if ($method === 'POST') {
        $body = Request::getJsonBody();
        $token = (string) ($body['token'] ?? '');
    } else {
        $token = (string) ($_GET['token'] ?? '');
    }

    if (trim($token) === '') {
        Response::error('Unsubscribe token is required.', 'TOKEN_REQUIRED', 422);
    }

    $result = $newsletterService->unsubscribe($token);

    Response::success([
        'message' => $result['message'],
        'subscriber' => [
            'id' => $result['id'],
            'email' => $result['email'],
            'status' => $result['status'],
            'unsubscribed_at' => $result['unsubscribed_at'],
        ],
    ], 200);

} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), $e->getCode(), $e->getDetails());
} catch (\Throwable $e) {
    $logger->error('Unexpected error in /api/newsletter/unsubscribe.php: ' . $e->getMessage());
    Response::error('Unsubscribe request could not be processed.', 'INTERNAL_ERROR', 500);
}
