<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Logging\Logger;
use App\Services\Exceptions\ValidationException;
use App\Services\NewsletterService;
use App\Support\RateLimiter;
use App\Support\View;

$logger = new Logger();
$newsletterService = new NewsletterService();

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$successMessage = null;
$errorMessage = null;
$unsubscribed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RateLimiter::enforce('newsletter_unsubscribe', null, 60, 60);
    $submittedToken = trim((string) ($_POST['token'] ?? ''));
    if ($submittedToken === '') {
        $errorMessage = 'Unsubscribe token is required.';
    } else {
        try {
            $result = $newsletterService->unsubscribe($submittedToken);
            $successMessage = $result['message'];
            $unsubscribed = true;
        } catch (ValidationException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $logger->error('Newsletter unsubscribe error: ' . $e->getMessage());
            $errorMessage = 'An error occurred while processing your unsubscribe request. Please try again.';
        }
    }
} elseif ($token !== '') {
    try {
        $result = $newsletterService->unsubscribe($token);
        $successMessage = $result['message'];
        $unsubscribed = true;
    } catch (ValidationException $e) {
        $errorMessage = $e->getMessage();
    } catch (\Throwable $e) {
        $logger->error('Newsletter unsubscribe error: ' . $e->getMessage());
        $errorMessage = 'An error occurred while processing your unsubscribe request. Please try again.';
    }
}

View::render(
    'unsubscribe',
    [
        'token' => $token,
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
        'unsubscribed' => $unsubscribed,
    ],
    'Newsletter Unsubscribe — AppTutors UK',
    'Manage your newsletter email preferences for AppTutors UK.'
);
