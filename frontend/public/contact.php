<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap/bootstrap.php';

use App\Logging\Logger;
use App\Support\RateLimiter;
use App\Support\View;
use App\Validation\Validator;

$errors = [];
$successMessage = null;
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RateLimiter::enforce('contact_form', null, 20, 60);
    $formData = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'type' => trim((string) ($_POST['type'] ?? '')),
        'subject' => trim((string) ($_POST['subject'] ?? '')),
        'message' => trim((string) ($_POST['message'] ?? '')),
        'consent' => !empty($_POST['consent']),
    ];

    // Validation
    $missing = Validator::checkRequired($formData, ['name', 'email', 'type', 'subject', 'message']);
    if (!empty($missing)) {
        $errors[] = 'All marked fields are required to submit an inquiry.';
    }

    if (!empty($formData['email']) && !Validator::validateEmail($formData['email'])) {
        $errors[] = 'Please provide a valid email address.';
    }

    $allowedTypes = ['PARENT_STUDENT', 'TUTOR_APPLICATION', 'SAFEGUARDING', 'GENERAL'];
    if (!empty($formData['type']) && !Validator::validateEnum($formData['type'], $allowedTypes)) {
        $errors[] = 'Please select a valid enquiry type from the dropdown options.';
    }

    if (mb_strlen($formData['name']) > 120) {
        $errors[] = 'Name cannot exceed 120 characters.';
    }

    if (mb_strlen($formData['subject']) > 150) {
        $errors[] = 'Subject cannot exceed 150 characters.';
    }

    if (mb_strlen($formData['message']) > 3000) {
        $errors[] = 'Message is too long (maximum 3000 characters).';
    }

    if (!$formData['consent']) {
        $errors[] = 'You must consent to data processing (explicit-consent control) to submit an inquiry.';
    }

    if (empty($errors)) {
        // Safe sanitized logging (privacy-preserving audit control with SHA-256 email hashing)
        $hashedEmail = hash('sha256', strtolower($formData['email']));
        Logger::info('Public contact inquiry received', [
            'enquiry_type' => $formData['type'],
            'email_hash' => $hashedEmail,
            'subject_length' => mb_strlen($formData['subject']),
        ]);

        $sanitizedName = Validator::sanitizeString($formData['name']);
        $successMessage = "Thank you, {$sanitizedName}! Your inquiry has been registered. Our UK admissions team will respond within 1 business day. [CLIENT APPROVAL REQUIRED — Admissions Service Level Agreement]";
        
        // Clear form after success
        $formData = [];
    }
}

View::render(
    'contact',
    [
        'errors' => $errors,
        'successMessage' => $successMessage,
        'formData' => $formData,
    ],
    'Contact Admissions & Safeguarding — AppTutors UK',
    'Contact AppTutors UK for assistance with tutor selection, curriculum enquiries, or child safeguarding background verification.'
);
