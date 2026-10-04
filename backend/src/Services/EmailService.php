<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Interface EmailService
 *
 * Provider-agnostic transactional email dispatch abstraction.
 * Defined in Master Project Document v2.0 and Architecture Section 12.
 */
interface EmailService
{
    /**
     * Send a templated transactional notification email.
     *
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient display name
     * @param string $subject Email subject line
     * @param string $templateName Name of template (e.g. 'booking_confirmed')
     * @param array $templateData Dynamic contextual data for template
     * @return bool True if accepted for delivery by provider, false otherwise
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $templateName,
        array $templateData = []
    ): bool;
}
