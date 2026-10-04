<?php

declare(strict_types=1);

namespace App\Services\Email\Adapters;

use App\Services\Email\EmailProviderInterface;
use App\Services\Email\EmailResult;

/**
 * Class NullEmailAdapter
 *
 * Blackhole adapter used when email notifications are globally disabled.
 */
class NullEmailAdapter implements EmailProviderInterface
{
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $headers = []
    ): EmailResult {
        return EmailResult::success(
            provider: $this->getName(),
            messageId: 'null_' . bin2hex(random_bytes(8)),
            metadata: ['status' => 'dropped_intentionally']
        );
    }

    public function getName(): string
    {
        return 'null';
    }
}
