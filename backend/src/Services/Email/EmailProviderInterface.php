<?php

declare(strict_types=1);

namespace App\Services\Email;

/**
 * Interface EmailProviderInterface
 *
 * Provider-agnostic adapter contract.
 * Allows decoupling the business services from any commercial email transport.
 */
interface EmailProviderInterface
{
    /**
     * Send rendered email content via the provider transport.
     *
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient display name
     * @param string $subject Email subject line
     * @param string $htmlBody Rendered HTML email content
     * @param string $textBody Plain-text fallback email content
     * @param array $headers Optional custom headers
     * @return EmailResult
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $headers = []
    ): EmailResult;

    /**
     * Get the provider's technical name / identifier.
     *
     * @return string
     */
    public function getName(): string;
}
