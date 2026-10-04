<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Database\Database;
use App\Logging\Logger;
use App\Support\RateLimiter;
use App\Support\Timezone;
use App\Support\View;
use App\Validation\Validator;

$successMessage = null;
$errorMessage = null;
$submittedEmail = '';
$submittedConsent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RateLimiter::enforce('newsletter_subscribe', null, 30, 60);
    $submittedEmail = trim((string) ($_POST['email'] ?? ''));
    $submittedConsent = !empty($_POST['consent']);

    try {
        $newsletterService = new \App\Services\NewsletterService();
        $newsletterService->subscribe($submittedEmail, $submittedConsent);
        $successMessage = 'Thank you for your interest! Your subscription request has been received. [OPEN CLIENT DECISION — Double Opt-In Verification Workflow]';
        $submittedEmail = '';
        $submittedConsent = false;
    } catch (\App\Services\Exceptions\ValidationException $e) {
        $errorMessage = $e->getMessage();
    } catch (\Throwable $e) {
        Logger::error('Newsletter subscription error', [
            'error' => $e->getMessage(),
        ]);
        $errorMessage = 'A temporary database error occurred. Please try again later.';
    }
}

View::render(
    'newsletter',
    [
        'successMessage' => $successMessage,
        'errorMessage' => $errorMessage,
        'submittedEmail' => $submittedEmail,
        'submittedConsent' => $submittedConsent,
    ],
    'Educational Newsletter & Revision Updates — AppTutors UK',
    'Subscribe to the AppTutors UK newsletter for exam board dates, syllabus revision checklists, and academic study tips.'
);
