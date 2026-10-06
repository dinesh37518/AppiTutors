<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Logging\Logger;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\Email\Adapters\ArrayEmailAdapter;
use App\Services\Email\Adapters\EmailJsEmailAdapter;
use App\Services\Email\Adapters\LogEmailAdapter;
use App\Services\Email\Adapters\NullEmailAdapter;
use App\Services\Email\Adapters\SmtpEmailAdapter;
use App\Services\Exceptions\ValidationException;
use Throwable;

/**
 * Class DefaultEmailService
 *
 * Core transactional email orchestrator implementing App\Services\EmailService.
 * Enforces email validation, header-injection defense, template rendering, and audit logging.
 */
class DefaultEmailService implements EmailService
{
    private EmailProviderInterface $provider;
    private Logger $logger;
    private ?AuditService $audit;
    private array $config;
    private string $templateBasePath;

    public function __construct(
        ?EmailProviderInterface $provider = null,
        ?Logger $logger = null,
        ?AuditService $audit = null,
        ?array $config = null,
        ?string $templateBasePath = null
    ) {
        $this->logger = $logger ?? new Logger();
        $this->audit = $audit;
        
        $cfgFile = file_exists(dirname(__DIR__, 2) . '/config/mail.php')
            ? dirname(__DIR__, 2) . '/config/mail.php'
            : (file_exists(dirname(__DIR__, 3) . '/config/mail.php') ? dirname(__DIR__, 3) . '/config/mail.php' : dirname(__DIR__, 4) . '/config/mail.php');
        $this->config = $config ?? (require $cfgFile);

        $defaultTemplatePath = is_dir(dirname(__DIR__, 2) . '/Views/emails')
            ? (dirname(__DIR__, 2) . '/Views/emails')
            : (is_dir(dirname(__DIR__, 3) . '/src/Views/emails') ? (dirname(__DIR__, 3) . '/src/Views/emails') : (dirname(__DIR__, 4) . '/src/Views/emails'));
        $this->templateBasePath = $templateBasePath ?? $defaultTemplatePath;

        $this->provider = $provider ?? $this->resolveProvider();
    }

    /**
     * Send a templated transactional notification email.
     *
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient display name
     * @param string $subject Email subject line
     * @param string $templateName Name of template file in src/Views/emails/
     * @param array $templateData Contextual variables for the template
     * @return bool True if accepted for delivery, false otherwise
     * @throws ValidationException If recipient is invalid or header injection is detected
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $templateName,
        array $templateData = []
    ): bool {
        // 1. Email Header Injection Defense (OWASP Protection)
        $this->assertNoHeaderInjection($toEmail, 'recipient email');
        $this->assertNoHeaderInjection($toName, 'recipient name');
        $this->assertNoHeaderInjection($subject, 'subject line');

        // 2. Strict Recipient Validation (RFC 822 / 5321)
        $cleanEmail = trim($toEmail);
        if (empty($cleanEmail) || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning("Rejected email dispatch: Invalid recipient address '{$toEmail}'");
            throw new ValidationException(
                'Invalid recipient email address.',
                'INVALID_EMAIL_ADDRESS',
                422,
                ['email' => 'Invalid email address syntax.']
            );
        }

        // 3. Template Path Traversal Protection
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $templateName)) {
            $this->logger->error("Security violation: Attempted path traversal in template name '{$templateName}'");
            throw new ValidationException('Invalid template identifier.', 'INVALID_TEMPLATE_NAME', 422);
        }

        $templateFile = $this->templateBasePath . '/' . $templateName . '.php';
        if (!file_exists($templateFile)) {
            $this->logger->error("Email template not found: '{$templateFile}'");
            return false;
        }

        // 4. Render HTML and Plain-Text Bodies
        try {
            $htmlBody = $this->renderTemplate($templateFile, $subject, $templateData);
            $textBody = $this->generatePlainText($htmlBody);
        } catch (Throwable $e) {
            $this->logger->error("Template rendering exception: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }

        // 5. Dispatch via Configured Provider Transport
        try {
            $result = $this->provider->send(
                toEmail: $cleanEmail,
                toName: trim($toName),
                subject: trim($subject),
                htmlBody: $htmlBody,
                textBody: $textBody
            );
        } catch (Throwable $e) {
            $this->logger->error("Provider transport exception for '{$cleanEmail}': " . $e->getMessage(), [
                'provider' => $this->provider->getName(),
                'template' => $templateName,
                'exception' => $e,
            ]);
            $result = EmailResult::failure($this->provider->getName(), $e->getMessage());
        }

        // 6. Audit & Application Logging
        $recipientHash = hash('sha256', strtolower($cleanEmail));

        if ($result->isSuccess()) {
            $this->logger->info("Email sent successfully: Template '{$templateName}' to '{$cleanEmail}' via [{$result->getProvider()}] (ID: {$result->getMessageId()})");

            if ($this->audit !== null) {
                try {
                    $this->audit->log(
                        action: 'EMAIL_SENT',
                        entityType: 'email',
                        entityId: null,
                        actorUserId: null,
                        metadata: [
                            'template' => $templateName,
                            'recipient_hash' => $recipientHash,
                            'provider' => $result->getProvider(),
                            'message_id' => $result->getMessageId(),
                        ]
                    );
                } catch (Throwable $e) {
                    $this->logger->warning('Failed to write email audit log: ' . $e->getMessage());
                }
            }

            return true;
        }

        // Log Failure
        $this->logger->error("Email delivery failed: Template '{$templateName}' to '{$cleanEmail}' via [{$result->getProvider()}]: {$result->getErrorMessage()}");

        if ($this->audit !== null) {
            try {
                $this->audit->log(
                    action: 'EMAIL_FAILED',
                    entityType: 'email',
                    entityId: null,
                    actorUserId: null,
                    metadata: [
                        'template' => $templateName,
                        'recipient_hash' => $recipientHash,
                        'provider' => $result->getProvider(),
                        'error' => $result->getErrorMessage(),
                    ]
                );
            } catch (Throwable $e) {
                $this->logger->warning('Failed to write email failure audit log: ' . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * Get the active provider adapter instance.
     *
     * @return EmailProviderInterface
     */
    public function getProvider(): EmailProviderInterface
    {
        return $this->provider;
    }

    /**
     * Set a custom provider adapter (useful for testing).
     *
     * @param EmailProviderInterface $provider
     */
    public function setProvider(EmailProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Guard against email header injection by detecting CRLF sequences.
     *
     * @param string $value
     * @param string $fieldName
     * @throws ValidationException
     */
    private function assertNoHeaderInjection(string $value, string $fieldName): void
    {
        if (preg_match('/[\r\n]/', $value)) {
            $this->logger->error("Security Alert: Header injection attempt detected in {$fieldName}: " . addcslashes($value, "\r\n"));
            throw new ValidationException(
                "Potential email header injection detected in {$fieldName}.",
                'EMAIL_HEADER_INJECTION_DETECTED',
                422,
                [$fieldName => 'Carriage return and newline characters are strictly forbidden.']
            );
        }
    }

    /**
     * Render the template within the master email layout.
     *
     * @param string $templatePath
     * @param string $subject
     * @param array $data
     * @return string
     */
    private function renderTemplate(string $templatePath, string $subject, array $data): string
    {
        // 1. Render template inner content
        extract($data, EXTR_SKIP);
        ob_start();
        include $templatePath;
        $content = ob_get_clean();

        // 2. Render within layout
        $layoutPath = $this->templateBasePath . '/layout.php';
        if (file_exists($layoutPath)) {
            ob_start();
            include $layoutPath;
            return ob_get_clean();
        }

        return (string) $content;
    }

    /**
     * Generate plain-text fallback from HTML content.
     *
     * @param string $html
     * @return string
     */
    private function generatePlainText(string $html): string
    {
        // Strip style and script tags and their content
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $text);

        // Replace common tags with formatted text
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);
        $text = preg_replace('/<li[^>]*>/i', " - ", $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Normalize excess whitespace
        $lines = array_map('trim', explode("\n", $text));
        $cleaned = [];
        $lastEmpty = false;

        foreach ($lines as $line) {
            if ($line === '') {
                if (!$lastEmpty) {
                    $cleaned[] = '';
                    $lastEmpty = true;
                }
            } else {
                $cleaned[] = $line;
                $lastEmpty = false;
            }
        }

        return trim(implode("\n", $cleaned));
    }

    /**
     * Resolve provider adapter according to configuration.
     *
     * @return EmailProviderInterface
     */
    private function resolveProvider(): EmailProviderInterface
    {
        if (isset($this->config['enabled']) && !$this->config['enabled']) {
            return new NullEmailAdapter();
        }

        $mailer = strtolower((string) ($this->config['mailer'] ?? 'array'));

        return match ($mailer) {
            'array', 'test', 'memory' => new ArrayEmailAdapter(),
            'log' => new LogEmailAdapter($this->logger),
            'null', 'disabled' => new NullEmailAdapter(),
            'smtp' => new SmtpEmailAdapter($this->config, $this->logger),
            'emailjs' => new EmailJsEmailAdapter($this->config, $this->logger),
            default => new ArrayEmailAdapter(),
        };
    }
}
