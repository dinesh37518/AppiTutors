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
    RateLimiter::enforce('newsletter_subscribe', null, 30, 60);

    if (Request::getMethod() !== 'POST') {
        Response::error('Method not allowed. Use POST.', 'METHOD_NOT_ALLOWED', 405);
    }

    $body = Request::getJsonBody();

    $email = (string) ($body['email'] ?? '');
    $consent = !empty($body['consent']);

    $subscriber = $newsletterService->subscribe($email, $consent);

    Response::success([
        'message' => 'Thank you for your interest! Your subscription request has been received. [OPEN CLIENT DECISION — Double Opt-In Verification Workflow]',
        'subscriber' => [
            'id' => $subscriber['id'],
            'email' => $subscriber['email'],
            'status' => $subscriber['status'],
            'confirmed_at' => $subscriber['confirmed_at'],
        ],
    ], 201);

} catch (ValidationException $e) {
    Response::error($e->getMessage(), $e->getErrorCode(), 422, $e->getDetails());
} catch (\Throwable $e) {
    $logger->error('Unexpected error in /api/newsletter/subscribe.php: ' . $e->getMessage());
    Response::error('Newsletter subscription could not be completed.', 'INTERNAL_ERROR', 500);
}
