<?php

declare(strict_types=1);

namespace App\Services\Email\Adapters;

use App\Logging\Logger;
use App\Services\Email\EmailProviderInterface;
use App\Services\Email\EmailResult;

/**
 * Class LogEmailAdapter
 *
 * Provider adapter that writes outgoing notifications to the application logger.
 * Useful for local development and staging environments without live SMTP.
 */
class LogEmailAdapter implements EmailProviderInterface
{
    private Logger $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger();
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $headers = []
    ): EmailResult {
        $messageId = 'log_' . bin2hex(random_bytes(10));

        // Privacy: Mask email username for security logs
        $parts = explode('@', $toEmail);
        $maskedRecipient = count($parts) === 2
            ? substr($parts[0], 0, 2) . '***@' . $parts[1]
            : '***';

        $this->logger->info("[EMAIL DISPATCHED] To: {$maskedRecipient} ({$toName}) | Subject: {$subject} | Message-ID: {$messageId}", [
            'provider' => $this->getName(),
            'message_id' => $messageId,
            'recipient_masked' => $maskedRecipient,
            'subject' => $subject,
            'content_length_html' => strlen($htmlBody),
            'content_length_text' => strlen($textBody),
        ]);

        return EmailResult::success(
            provider: $this->getName(),
            messageId: $messageId,
            metadata: ['recipient_masked' => $maskedRecipient, 'subject' => $subject]
        );
    }

    public function getName(): string
    {
        return 'log';
    }
}
