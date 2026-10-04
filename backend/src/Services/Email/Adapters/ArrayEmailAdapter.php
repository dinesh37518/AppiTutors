<?php

declare(strict_types=1);

namespace App\Services\Email\Adapters;

use App\Services\Email\EmailProviderInterface;
use App\Services\Email\EmailResult;

/**
 * Class ArrayEmailAdapter
 *
 * In-memory provider adapter for automated testing and local verification.
 * Captures all dispatched emails in memory without requiring external network connectivity.
 */
class ArrayEmailAdapter implements EmailProviderInterface
{
    private array $dispatchedEmails = [];
    private bool $shouldFail = false;
    private string $failureMessage = 'Simulated email delivery failure.';

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $headers = []
    ): EmailResult {
        if ($this->shouldFail) {
            return EmailResult::failure(
                provider: $this->getName(),
                errorMessage: $this->failureMessage,
                metadata: ['to_email' => $toEmail, 'subject' => $subject]
            );
        }

        $messageId = 'array_' . bin2hex(random_bytes(10));
        $record = [
            'id' => $messageId,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
            'headers' => $headers,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $this->dispatchedEmails[] = $record;

        return EmailResult::success(
            provider: $this->getName(),
            messageId: $messageId,
            metadata: ['recipient' => $toEmail, 'subject' => $subject]
        );
    }

    public function getName(): string
    {
        return 'array';
    }

    /**
     * Retrieve all dispatched email records.
     *
     * @return array
     */
    public function getDispatchedEmails(): array
    {
        return $this->dispatchedEmails;
    }

    /**
     * Get the most recently dispatched email record.
     *
     * @return array|null
     */
    public function getLastDispatched(): ?array
    {
        if (empty($this->dispatchedEmails)) {
            return null;
        }
        return $this->dispatchedEmails[count($this->dispatchedEmails) - 1];
    }

    /**
     * Check if an email was sent to a specific address.
     *
     * @param string $email
     * @return bool
     */
    public function hasDispatchedTo(string $email): bool
    {
        foreach ($this->dispatchedEmails as $record) {
            if (strcasecmp($record['to_email'], $email) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count total dispatched emails.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->dispatchedEmails);
    }

    /**
     * Clear all recorded emails.
     */
    public function clear(): void
    {
        $this->dispatchedEmails = [];
    }

    /**
     * Configure the adapter to simulate delivery failure.
     *
     * @param bool $shouldFail
     * @param string $message
     */
    public function setShouldFail(bool $shouldFail, string $message = 'Simulated email delivery failure.'): void
    {
        $this->shouldFail = $shouldFail;
        $this->failureMessage = $message;
    }
}
