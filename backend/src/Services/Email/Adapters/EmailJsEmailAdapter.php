<?php

declare(strict_types=1);

namespace App\Services\Email\Adapters;

use App\Logging\Logger;
use App\Services\Email\EmailProviderInterface;
use App\Services\Email\EmailResult;
use App\Support\Env;
use Throwable;

/**
 * Class EmailJsEmailAdapter
 *
 * Provider adapter integrating with EmailJS REST API (v1.0)
 * Allows dispatching transactional communications via configured EmailJS Service and Template.
 * Server-side REST API dispatch ensures private keys and credentials are never exposed to the client browser.
 */
class EmailJsEmailAdapter implements EmailProviderInterface
{
    private array $config;
    private Logger $logger;
    private string $serviceId;
    private string $templateId;
    private string $publicKey;
    private string $privateKey;
    private string $apiUrl;

    public function __construct(?array $config = null, ?Logger $logger = null)
    {
        $this->config = $config ?? [];
        $this->logger = $logger ?? new Logger();

        $emailJsConfig = $this->config['emailjs'] ?? $this->config;
        $this->serviceId = (string) ($emailJsConfig['service_id'] ?? Env::get('EMAILJS_SERVICE_ID', ''));
        $this->templateId = (string) ($emailJsConfig['template_id'] ?? Env::get('EMAILJS_TEMPLATE_ID', ''));
        $this->publicKey = (string) ($emailJsConfig['public_key'] ?? Env::get('EMAILJS_PUBLIC_KEY', ''));
        $this->privateKey = (string) ($emailJsConfig['private_key'] ?? Env::get('EMAILJS_PRIVATE_KEY', ''));
        $this->apiUrl = (string) ($emailJsConfig['api_url'] ?? Env::get('EMAILJS_API_URL', 'https://api.emailjs.com/api/v1.0/email/send'));
    }

    public function isConfigured(): bool
    {
        return !empty($this->serviceId) && !empty($this->templateId) && !empty($this->publicKey);
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        array $headers = []
    ): EmailResult {
        // Enforce that credentials must be present
        if (empty($this->serviceId) || empty($this->templateId) || empty($this->publicKey)) {
            $this->logger->warning('EmailJS transport invoked but required credentials (EMAILJS_SERVICE_ID, EMAILJS_TEMPLATE_ID, EMAILJS_PUBLIC_KEY) are not configured.');
            return EmailResult::failure(
                provider: $this->getName(),
                errorMessage: 'EmailJS credentials not configured in environment.'
            );
        }

        $messageId = 'emailjs_' . bin2hex(random_bytes(10));

        // Privacy: Mask email for logging
        $parts = explode('@', $toEmail);
        $maskedRecipient = count($parts) === 2
            ? substr($parts[0], 0, 2) . '***@' . $parts[1]
            : '***';

        $payload = [
            'service_id' => $this->serviceId,
            'template_id' => $this->templateId,
            'user_id' => $this->publicKey,
            'template_params' => [
                'to_email' => $toEmail,
                'to_name' => $toName,
                'subject' => $subject,
                'message_html' => $htmlBody,
                'message_text' => $textBody,
            ],
        ];

        if (!empty($this->privateKey)) {
            $payload['accessToken'] = $this->privateKey;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Offline / unit test bypass
        if ($this->serviceId === 'mock_service' || str_starts_with($this->apiUrl, 'mock://') || Env::get('APP_ENV') === 'testing') {
            $this->logger->info("EmailJS (mock) dispatched successfully to {$maskedRecipient}", ['message_id' => $messageId]);
            return EmailResult::success(
                provider: $this->getName(),
                messageId: $messageId,
                metadata: [
                    'recipient' => $toEmail,
                    'subject' => $subject,
                    'mock' => true,
                ]
            );
        }

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($this->apiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $jsonPayload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'User-Agent: AppTutors-EmailJS-Client/1.0',
                    ],
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                ]);

                $responseBody = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    $this->logger->error("EmailJS transport cURL failure: {$curlError}");
                    return EmailResult::failure(
                        provider: $this->getName(),
                        errorMessage: "EmailJS transport error: {$curlError}"
                    );
                }

                if ($httpCode >= 200 && $httpCode < 300) {
                    $this->logger->info("[EMAIL DISPATCHED VIA EMAILJS] To: {$maskedRecipient} | Subject: {$subject} | Message-ID: {$messageId}");
                    return EmailResult::success(
                        provider: $this->getName(),
                        messageId: $messageId,
                        metadata: ['recipient_masked' => $maskedRecipient, 'status_code' => $httpCode]
                    );
                }

                $this->logger->warning("EmailJS HTTP {$httpCode} error response: {$responseBody}");
                return EmailResult::failure(
                    provider: $this->getName(),
                    errorMessage: "EmailJS API returned HTTP {$httpCode}: {$responseBody}"
                );
            } else {
                // Fallback stream context
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\nUser-Agent: AppTutors-EmailJS-Client/1.0\r\n",
                        'content' => $jsonPayload,
                        'timeout' => 10,
                    ],
                ]);

                $responseBody = @file_get_contents($this->apiUrl, false, $context);
                if ($responseBody === false) {
                    return EmailResult::failure(
                        provider: $this->getName(),
                        errorMessage: 'Failed to communicate with EmailJS endpoint via stream context.'
                    );
                }

                return EmailResult::success(
                    provider: $this->getName(),
                    messageId: $messageId,
                    metadata: ['recipient_masked' => $maskedRecipient]
                );
            }
        } catch (Throwable $e) {
            $this->logger->error("EmailJS dispatch exception: " . $e->getMessage());
            return EmailResult::failure(
                provider: $this->getName(),
                errorMessage: 'EmailJS dispatch exception: ' . $e->getMessage()
            );
        }
    }

    public function getName(): string
    {
        return 'emailjs';
    }
}
