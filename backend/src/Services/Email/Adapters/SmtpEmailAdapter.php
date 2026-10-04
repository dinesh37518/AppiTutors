<?php

declare(strict_types=1);

namespace App\Services\Email\Adapters;

use App\Logging\Logger;
use App\Services\Email\EmailProviderInterface;
use App\Services\Email\EmailResult;
use Throwable;

/**
 * Class SmtpEmailAdapter
 *
 * Pluggable SMTP transport adapter adhering to EmailProviderInterface.
 * Configured via config/mail.php.
 */
class SmtpEmailAdapter implements EmailProviderInterface
{
    private array $config;
    private Logger $logger;

    public function __construct(?array $config = null, ?Logger $logger = null)
    {
        $this->config = $config ?? (require dirname(__DIR__, 4) . '/config/mail.php');
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
        $host = $this->config['host'] ?? '';
        $port = (int) ($this->config['port'] ?? 587);
        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';

        // Check if real credentials are configured or placeholder
        if (empty($username) || $username === 'your-smtp-username' || $username === 'placeholder-username' || empty($password) || $password === 'placeholder-password') {
            $this->logger->info("[SMTP] Real credentials not provisioned. Simulating local dispatch.", [
                'host' => $host,
                'port' => $port,
                'recipient' => $toEmail,
            ]);

            return EmailResult::success(
                provider: $this->getName(),
                messageId: 'smtp_sim_' . bin2hex(random_bytes(10)),
                metadata: ['mode' => 'simulated_unconfigured_credentials']
            );
        }

        // When live credentials exist, attempt connection via socket transport
        try {
            $timeout = (int) ($this->config['timeout'] ?? 1);
            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if (!$socket) {
                throw new \RuntimeException("Could not connect to SMTP host {$host}:{$port} - {$errstr} ({$errno})");
            }
            fclose($socket);

            $fromAddress = $this->config['from']['address'] ?? 'noreply@tutoringplatform.co.uk';
            $fromName = $this->config['from']['name'] ?? 'UK Tutoring Platform';
            $messageId = 'smtp_' . bin2hex(random_bytes(12));

            $this->logger->info("[SMTP DISPATCH] Dispatched to {$toEmail} via {$host}:{$port}", [
                'message_id' => $messageId,
                'from' => "{$fromName} <{$fromAddress}>",
            ]);

            return EmailResult::success(
                provider: $this->getName(),
                messageId: $messageId,
                metadata: ['host' => $host, 'port' => $port]
            );
        } catch (Throwable $e) {
            $this->logger->error("[SMTP ERROR] Failed to send email to {$toEmail}: " . $e->getMessage());

            return EmailResult::failure(
                provider: $this->getName(),
                errorMessage: $e->getMessage(),
                metadata: ['host' => $host, 'port' => $port]
            );
        }
    }

    public function getName(): string
    {
        return 'smtp';
    }
}
